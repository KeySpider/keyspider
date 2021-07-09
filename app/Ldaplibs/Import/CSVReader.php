<?php

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Ldaplibs\Import;

use App\Commons\Consts;
use App\Commons\Creator;
use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Report\DetailReport;
use App\Ldaplibs\Report\SummaryReport;
use Carbon\Carbon;
use http\Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Class CSVReader
 *
 * @package App\Ldaplibs\Import
 */
class CSVReader
{
    protected $setting;
    protected $settingManagement;
    const PLUGINS_DIR = "App\\Commons\\Plugins\\";

    /**
     * CSVReader constructor.
     * @param SettingsManager $setting
     */
    public function __construct(SettingsManager $setting)
    {
        $this->setting = $setting;
        $this->settingManagement = new SettingsManager();
    }

    /** Get name table from setting file
     *
     * @param array $setting
     *
     * @return string
     */
    public function getNameTableFromSetting($setting)
    {
        $nameTable = $setting[Consts::IMPORT_PROCESS_BASIC_CONFIGURATION]["TableNameInDB"];
        $nameTable = "\"{$nameTable}\"";
        return $nameTable;
    }

    /**
     * @param $setting
     * @return mixed
     */
    public function getNameTableBase($setting)
    {
        return $setting[Consts::IMPORT_PROCESS_BASIC_CONFIGURATION]["TableNameInDB"];
    }

    /**
     * Get all column from setting file
     *
     * @param array $setting
     *
     * @return array
     */
    public function getAllColumnFromSetting($setting)
    {
        $pattern = "/[\'^£$%&*()}{@#~?><>,|=_+¬-]/";
        $fields = [];
        foreach ($setting[Consts::IMPORT_PROCESS_FORMAT_CONVERSION] as $key => $item) {
            if ($key !== "" && preg_match($pattern, $key) !== 1) {
                $newstring = substr($key, -3);
                $fields[] = "{$newstring}";
            }
        }
        return $fields;
    }

    /**
     * Create table from setting file
     *
     * @param $nameTable
     * @param array $columns
     *
     * @return void
     */
    public function createTable($nameTable, $columns = [])
    {
        foreach ($columns as $key => $col) {
            if (!Schema::hasColumn($nameTable, $col)) {
                Schema::table($nameTable, function (Blueprint $table) use ($col) {
                    $table->string($col)->nullable();
                });
            }
        }
    }

    /**
     * Get data from one csv file
     *
     * @param $fileCSV
     * @param array $options
     *
     * @param $columns
     * @param $nameTable
     * @param $processedFilePath
     * @return void
     */
    public function getDataFromOneFile($fileCSV, $setting)
    {
        try {
            $summaryReport = new SummaryReport(date(Consts::DATE_FORMAT_YMDHIS));
            $detailReport = new DetailReport();
            $reportId = $summaryReport->makeReportId();
            $detailReport->setReportId($reportId);

            DB::beginTransaction();

            $regExpManagement = new RegExpsManager();

            // get name table base
            $nameTable = $this->getNameTableBase($setting);
            $columns = $this->getAllColumnFromSetting($setting);

            $primaryColumn = $setting[Consts::IMPORT_PROCESS_BASIC_CONFIGURATION][Consts::PRIMARY_COLUMN];
            $externalId = $setting[Consts::IMPORT_PROCESS_BASIC_CONFIGURATION][Consts::EXTERNAL_ID];

            $conversion = $setting[Consts::IMPORT_PROCESS_FORMAT_CONVERSION];

            if (strpos($nameTable, "UserTo") === false) { 
                $conversion[$nameTable . "." . $externalId] = $primaryColumn;
            }

            $params = ['CONVERSATION' => $conversion];

            $processedFilePath = $setting[Consts::IMPORT_PROCESS_BASIC_CONFIGURATION][Consts::PROCESSED_FILE_PATH];
            mkDirectory($processedFilePath);

            $getEncryptedFields = $settingManagement->getEncryptedFields();
            $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($nameTable);

            $roleMaps = $this->settingManagement->getRoleMapInName($nameTable);

            // Configuration file validation
            if (!$this->settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            // get data from csv file
            $csv = Reader::createFromPath($fileCSV);
            $stmt = new Statement();
            $records = $stmt->process($csv);

            if (count($records) == 0) {
                return;                
            }

            // Traceing
            $cmd = "Start";
            $message = "";
            $this->settingManagement->traceProcessInfo($dbt, $cmd, $message);

            // Traceing
            $cmd = "Count --> CSV File";
            $message = sprintf("Processing %s Object %d records", $nameTable, count($records));
            $this->settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;

            foreach ($records as $key => $record) {
                $getDataAfterConvert = $this->getDataAfterProcess($record, $params);
                $detailReport->setProcessedDatetime(date(Consts::DATE_FORMAT_YMDHIS));
                $detailReport->setDataDetail(json_encode($record, JSON_UNESCAPED_UNICODE));

                $itemId  = "";
                if (array_key_exists($externalId, $getDataAfterConvert)) {
                    $itemId = $getDataAfterConvert[$externalId] ;
                }
                $detailReport->setExternalId($itemId);

                $scimInfo = $this->settingManagement->makeScimInfo(
                    "CSVImport", "import", ucfirst(strtolower($nameTable)), $itemId, "", ""
                );

                if (
                    $nameTable == "UserToGroup" ||
                    $nameTable == "UserToOrganization" ||
                    $nameTable == "UserToRole"
                ) {

                    $aliasTable = str_replace("UserTo", "", $nameTable);

                    DB::table($nameTable)
                        ->where("User_ID", $getDataAfterConvert["User_ID"])
                        ->where($aliasTable . "_ID", $getDataAfterConvert[$aliasTable . "_ID"])
                        // ->where("DeleteFlag", "0")
                        ->delete();

                    // set UpdateFlags
                    $updateFlagsJson = $this->settingManagement->makeUpdateFlagsJson($aliasTable);
                    $updateFlagsColumnName = $this->settingManagement->getUpdateFlagsColumnName($aliasTable);
                    $getDataAfterConvert[$updateFlagsColumnName] = $updateFlagsJson;

                    $getDataAfterConvert[Consts::TABLE_ID] = (new Creator())->makeIdBasedOnMicrotime($nameTable);
                    DB::table($nameTable)->insert($getDataAfterConvert);

                    $regExpManagement->updateUserUpdateFlags($getDataAfterConvert["User_ID"]);

                    $detailReport->setCrudType("C");
                    $detailReport->setKeyspiderId($getDataAfterConvert["ID"]);
                    $detailReport->create("success");

                    $createCount++;
                    continue;
                }

                $roleFlags = [];
                if ($nameTable == "User") {
                    $getDataAfterConvert = $this->settingManagement->resetRoleFlagX($getDataAfterConvert);
                    foreach ($getDataAfterConvert as $cl => $item) {
                        if (strpos($cl, config("const.ROLE_ID")) !== false) {
                            $num = $this->settingManagement->getRoleFlagX($item, $roleMaps);
                            if ($num !== null) {
                                $keyValue = sprintf("RoleFlag-%d", $num);
                                $roleFlags[$keyValue] = "1";
                            }
                        }
                    }
                }
                $getDataAfterConvert = array_merge($getDataAfterConvert, $roleFlags);

                if (!empty($getEncryptedFields)) {
                    foreach ($getDataAfterConvert as $cl => $item) {
                        $tableColumn = $nameTable . "." . $cl;
                        if (in_array($tableColumn, $getEncryptedFields)) {
                            $getDataAfterConvert[$cl] = $this->settingManagement->passwordEncrypt($item);
                        }
                    }
                }

                // set UpdateFlags
                $updateFlagsJson = $this->settingManagement->makeUpdateFlagsJson($nameTable);
                $updateFlagsColumnName = $this->settingManagement->getUpdateFlagsColumnName($nameTable);
                $getDataAfterConvert[$updateFlagsColumnName] = $updateFlagsJson;

                $externalIdValue = $getDataAfterConvert[$externalId];
                $data = DB::table($nameTable)->where($externalId, $externalIdValue)->first();
                if ($data) {
                    $scimInfo['scimMethod'] = 'update';
                    $detailReport->setCrudType("U");
                    $detailReport->setKeyspiderId($data->ID);
                    DB::table($nameTable)->where($externalId, $getDataAfterConvert[$externalId])
                        ->update($getDataAfterConvert);
                    $scimInfo['scimMethod'] = 'update';
                    $updateCount++;
                } else {
                    $getDataAfterConvert[Consts::TABLE_ID] = (new Creator())->makeIdBasedOnMicrotime($nameTable);
                    $scimInfo['scimMethod'] = 'create';
                    $detailReport->setCrudType("C");
                    $detailReport->setKeyspiderId($getDataAfterConvert[Consts::TABLE_ID]);
                    DB::table($nameTable)->insert($getDataAfterConvert);
                    $scimInfo['scimMethod'] = 'create';
                    $createCount++;
                }
                $this->settingManagement->detailLogger($scimInfo);
                $detailReport->create("success");
            }

            // move file
            $now = Carbon::now()->format("Ymdhis") . random_int(1000, 9999);
            $fileName = $nameTable . "_{$now}.csv";
            moveFile($fileCSV, $processedFilePath . "/" . $fileName);

            if (
                $nameTable != "UserToGroup" &&
                $nameTable != "UserToOrganization" &&
                $nameTable != "UserToRole"
            ) {

                $deleteColumn = $this->settingManagement->getDeleteFlagColumnName($nameTable);
                DB::table($nameTable)->whereNull($deleteColumn)->update(["{$deleteColumn}" => "0"]);
            } else {
                // cleanup dupe recorde
                $this->sanitizeRecord($nameTable);
            }

            $scimInfo = array(
                'provisoning' => 'CSVImport',
                'table' => ucfirst(strtolower($nameTable)),
                'casesHandle' => count($records),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => 0,
            );
            $this->settingManagement->summaryLogger($scimInfo);

            DB::commit();

            $errorCount = count($records) - ($createCount + $updateCount);
            $summaryReport->create("IN", "CSV", $nameTable, count($records), $createCount, $updateCount, 0, $errorCount);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo = array(
                'provisoning' => 'CSVImport',
                'scimMethod' => 'import',
                'table' => ucfirst(strtolower($nameTable)),
                'itemId' => '',
                'itemName' => '',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);
            $detailReport->create("error");

            $errorCount = count($records) - ($createCount + $updateCount);
            $summaryReport->create("IN", "CSV", $nameTable, count($records), $createCount, $updateCount, 0, $errorCount);
        }

        DB::commit();

        // move file
        $now = Carbon::now()->format("Ymdhis") . random_int(1000, 9999);
        $fileName = $nameTable . "_{$now}.csv";
        moveFile($fileCSV, $processedFilePath . "/" . $fileName);
    }

    private function sanitizeRecord($nameTable)
    {
        $colmn = null;
        switch ($nameTable) {
            case "UserToGroup":
                $colmn = "Group_ID";
                break;
            case "UserToOrganization":
                $colmn = "Organization_ID";
                break;
            case "UserToRole":
                $colmn = "Role_ID";
                break;
        }
        $sqlStr = 'DELETE FROM "' . $nameTable . '" t1'
            . ' WHERE EXISTS(SELECT *'
            . ' FROM   "' . $nameTable . '" t2'
            . ' WHERE  t2."User_ID" = t1."User_ID"'
            . '   AND    t2."' . $colmn . '" = t1."' . $colmn . '"'
            . '   AND    t2.ctid > t1.ctid );';

        DB::statement($sqlStr);
    }


    /**
     * Get data after process
     *
     * @param $dataLine
     * @param array $options
     *
     * @return array
     */
    protected function getDataAfterProcess($dataLine, $options = []): array
    {
        $fields = [];
        $data = [];
        $conversions = $options["CONVERSATION"];

        $regExpManagement = new RegExpsManager();

        foreach ($conversions as $key => $item) {
            if ($key === "" || preg_match("/[\'^£$%&*()}{@#~?><>,|=_+¬-]/", $key) === 1) {
                // unset($conversions[$key]);
            }

            if (isset($conversions[$key])) {
                $explodeKey = explode(".", $key);
                $key = $explodeKey[1];
                $fields[$key] = $item;
            }
        }

        foreach ($fields as $col => $pattern) {
            preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $pattern, $matches);
            if (!empty($matches)) {
                $data[$col] = $this->executeImportExtend($pattern, $matches, $dataLine);
                continue;
            }

            if ($pattern === "admin") {
                $data[$col] = "admin";
            } elseif ($pattern === "TODAY()") {
                $data[$col] = Carbon::now()->format("Y/m/d");
            } elseif ($pattern === "0") {
                $data[$col] = "0";
            } else {
                // process with regular expressions?
                $columnName = $regExpManagement->checkRegExpRecord($pattern);

                if (isset($columnName)) {
                    $recValue = $dataLine[strtolower($columnName) - 1];
                    $data[$col] = $regExpManagement->convertDataFollowSetting($pattern, $recValue);
                } else {
                    $data[$col] = $this->convertDataFollowSetting($pattern, $dataLine);
                }
            }
        }
        return $data;
    }

    private function executeImportExtend($value, $matches, $dataLine) {
        $className = self::PLUGINS_DIR . "$matches[1]";
        if (!class_exists($className)) {
            return $value;
        }
        $clazz = new $className;
        if (!method_exists($clazz, $matches[2])) {
            return $value;
        }

        $methodName = $matches[2];
        $parameters = [];
        if (!empty($matches[3])) {
            $params = explode(",", $matches[3]);
            foreach ($params as $columnIndex) {
                array_push($parameters, $dataLine[$columnIndex-1]);
            }
        }
        return $clazz->$methodName($parameters);
    }

    /**
     * Covert data follow from setting
     *
     * @param string $pattern
     * @param array $data
     *
     * @return mixed|string
     */
    protected function convertDataFollowSetting($pattern, $data)
    {
        $stt = null;
        $group = null;
        $regx = null;

        $success = preg_match("/\(\s*(?<exp1>\d+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/", $pattern, $match);

        if ($success) {
            $stt = (int)$match["exp1"];
            $regx = $match["exp2"];
            $group = $match["exp3"];
        }

        foreach ($data as $key => $item) {
            if ($stt === $key + 1) {
                if ($regx === "") {
                    return $item;
                }

                if ($regx === "\s") {
                    return str_replace(" ", "", $item);
                }

                if ($regx === "\w") {
                    return strtolower($item);
                }
                $check = preg_match("/{$regx}/", $item, $str);

                if ($check) {
                    return $this->processGroup($str, $group);
                }
            } else {
                if (!preg_match("/\((\d+)\)/", $pattern, $matchs)) {
                    return $pattern;
                }
            }
        }
    }

    /**
     * Process group from pattern
     *
     * @param string $str
     * @param array $group
     *
     * @return string
     */
    protected function processGroup($str, $group): string
    {
        if ($group === '$1') {
            return $str[1];
        }

        if ($group === '$2') {
            return $str[2];
        }

        if ($group === '$3') {
            return $str[3];
        }

        if ($group === '$1/$2/$3') {
            return "{$str[1]}/{$str[2]}/{$str[3]}";
        }

        return "";
    }
}
