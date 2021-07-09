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

namespace App\Ldaplibs\Extract;

use App\Commons\Consts;
use App\Commons\Creator;
use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\UserGraphAPI;
use App\Ldaplibs\Report\DetailReport;
use App\Ldaplibs\Report\SummaryReport;
use Adldap\Laravel\Facades\Adldap;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Microsoft\Graph\Model\User;

class DBExtractor
{
    /**
     * define const
     */
    private const PLUGINS_DIR = "App\\Commons\\Plugins\\";
    private const REMOVE_LICENSES = "removeLicenses";

    protected $setting;
    protected $regExpManagement;
    protected $settingManagement;
    protected $tableMaster;
    protected $provider;

    /**
     * DBExtractor constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->tableMaster = null;
        $this->provider = null;
        $this->regExpManagement = new RegExpsManager();
        $this->settingManagement = new SettingsManager();
    }

    /**
     * @param $nameTable
     * @param $settingConvention
     * @return array|mixed
     */
    public function getColumnsSelectForCSV($nameTable, $settingConvention)
    {
        $index = 0;
        $arraySelectColumns = [];
        $arrayAliasColumns = [];

        ksort($settingConvention);

        foreach ($settingConvention as $key => $value) {
            preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $value, $matches);
            if (!empty($matches)) {
                $arraySelectColumns[] = DB::raw("'$value' as \"$key\"");
                $arrayAliasColumns[] = $key;
                continue;
            }

            preg_match("/\((\w+)\.(.*)\)/", $value, $matches, PREG_OFFSET_CAPTURE, 0);

            if (strpos($value, "ELOQ") !== false) {
                continue;
            }

            if (count($matches) > 2 && is_array($matches[2])) {
                $columnName = $matches[2][0];
                $arrayAliasColumns[] = $columnName;
            } else {
                $defaultColumn = "default_$index";
                $index++;
                $columnName = DB::raw("'$value' as \"$defaultColumn\"");
                $arrayAliasColumns[] = $defaultColumn;
            }
            $arraySelectColumns[] = $columnName;
        }
        return [$arraySelectColumns, $arrayAliasColumns];
    }

    public function processExtract()
    {
        try {
            DB::beginTransaction();

            $results = null;
            $dataType = "csv";
            $setting = $this->setting;
            $table = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
            $extractedId = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_PROCESS_ID];
            $extractCondition = $setting[Consts::EXTRACTION_PROCESS_CONDITION];
            $settingManagement = new SettingsManager();

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);
            $formatConvention = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];

            $realFormatConvention = [];
            foreach ($formatConvention as $idx => $dbColumn) {
                $regColumnName = $this->regExpManagement->checkRegExpRecord($dbColumn);
                if (isset($regColumnName)) {
                    $realFormatConvention[] = "(" . $table . "." . $regColumnName . ")";
                } else {
                    $realFormatConvention[] = $dbColumn;
                }
            }

            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelectForCSV($table, $realFormatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];

            //Append 'ID' to selected Columns to query and process later
            $selectColumnsAndID = array_merge($selectColumns, [$primaryKey]);
            $joins = ($this->getJoinCondition($realFormatConvention, $settingManagement));
            foreach ($joins as $src => $des) {
                $selectColumns[] = $des;
            }

            $query = DB::table($table);
            $query = $query->select($selectColumnsAndID)
                ->where($whereData);
            $extractedSql = $query->toSql();
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                // Traceing
                $cmd = "Start";
                $message = "";
                $settingManagement->traceProcessInfo($dbt, $cmd, $message);

                //Start to extract
                $pathOutput = $setting[Consts::OUTPUT_PROCESS_CONVERSION]["output_conversion"];
                $settingOutput = $this->getContentOutputCSV($pathOutput);
                $this->processOutputDataExtract($settingOutput, $results, $aliasColumns, $table);

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    // update flag
                    $keyString = $item->{"$primaryKey"};
                    $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = "0");
                }
                // Traceing
                $cmd = "Extract";
                $message = sprintf("Extract %s Object Success.", $table);
                $settingManagement->traceProcessInfo($dbt, $cmd, $message);
            }
            DB::commit();
        } catch (Exception $exception) {
            echo ("\e[0;31;47m [$extractedId] $exception \e[0m \n");
            Log::debug($exception);

            $scimInfo = $this->settingManagement->makeScimInfo(
                "Extract", "unknown", ucfirst(strtolower($table)), "System", "Exception", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);

            // Traceing
            $cmd = "Faild";
            $message = sprintf("Extract %s Object Faild. %s", $table, $exception->getMessage());
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        }
    }

    /**
     * @param $extractCondition
     * @return mixed
     */
    public function extractCondition($extractCondition, $nameColumnUpdate)
    {
        $whereData = [];
        foreach ($extractCondition as $key => &$condition) {
            if (!is_array($condition)) {
                // Add/Sub EffectiveDate
                if (strpos((string)$condition, "TODAY()") !== false) {
                    $condition = $this->regExpManagement->getEffectiveDate($condition);
                    array_push($whereData, [$key, "<=", $condition]);
                    continue;
                }

                // Logical operation setting or Regular expressions?
                $match = $this->regExpManagement->hasLogicalOperation($condition);
                if (!empty($match)) {
                    $whereData = $this->regExpManagement->makeExpLOCondition($key, $match, $whereData);
                    continue;
                }
            }

            // make standard condition
            if ($condition === "TODAY() + 7") {
                $condition = Carbon::now()->addDay(7)->format("Y/m/d");
                array_push($whereData, [$key, "<=", $condition]);
            } elseif (is_array($condition)) {
                // JSON Columns(Use UpdateFlags)
                foreach ($condition as $keyJson => $valueJson) {
                    array_push($whereData, ["{$nameColumnUpdate}->$keyJson", "=", "{$valueJson}"]);
                }
                continue;
            } else {
                array_push($whereData, [$key, "=", $condition]);
            }
        }
        return $whereData;
    }

    /**
     * @param $nameTable
     * @param $settingConvention
     * @return array|mixed
     */
    public function getColumnsSelect($nameTable, $settingConvention)
    {
        $index = 0;
        $arraySelectColumns = [];
        $arrayAliasColumns = [];

        foreach ($settingConvention as $key => $value) {
            if (0 < strpos($value, "UpperID->")) {
                $value = explode("->", $value)[0];
                $value = $value . ")";
            }

            preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $value, $matches);
            if (!empty($matches)) {
                $arrayAliasColumns[] = DB::raw("'$value' as \"$key\"");
                $arraySelectColumns[] = $key;
                continue;
            }

            // process with regular expressions?
            // set real value, after mod
            $regColumnName = $this->regExpManagement->checkRegExpRecord($value);
            if (isset($regColumnName)) {
                $arrayAliasColumns[] = DB::raw("\"$regColumnName\" as \"$key\"");
                $arraySelectColumns[] = $key;
                continue;
            }

            preg_match("/\((\w+)\.(.*)\)/", $value, $matches, PREG_OFFSET_CAPTURE, 0);

            if (count($matches) > 2 && is_array($matches[2])) {
                $columnName = $matches[2][0];
                $arrayAliasColumns[] = DB::raw("\"$columnName\" as \"$key\"");
            } else {
                $isMache = preg_match("/\((.*)\)/", $value, $matches, PREG_OFFSET_CAPTURE, 0);
                if ($isMache) {
                    $value = $matches[1][0];
                }
                $columnName = $key;
                $arrayAliasColumns[] = DB::raw("'$value' as \"$key\"");
            }
            $arraySelectColumns[] = $columnName;
        }
        return [$arraySelectColumns, $arrayAliasColumns];
    }

    public function getJoinCondition($settingConvention, SettingsManager $settingManager = null)
    {
        $joinConditions = [];
        $pattern = "/\(\s*(?<exp1>[\w\.]+)\s*((,\s*(?<exp2>[^\)]+))?|\s*\->\s*(?<exp3>[\w\.]+))\s*\)/";

        $allTablesColumns = parse_ini_file($settingManager->iniMasterDBFile, false);
        $allDestinationColumns = array_values($allTablesColumns);

        foreach ($settingConvention as $key => $value) {
            $isFormat = preg_match($pattern, $value, $data);
            if ($isFormat) {
                if (isset($data["exp3"])) {
                    if (
                        in_array($data["exp1"], $allDestinationColumns)
                        and in_array($data["exp3"], $allDestinationColumns)
                    ) {
                        $joinConditions[$data["exp1"]] = $data["exp3"];
                    }
                }
            }
        }
        return $joinConditions;
    }

    /**
     * @param $arraySelectColumns
     * @return mixed
     */
    public function convertArrayColumnsIntoString($arraySelectColumns)
    {
        foreach ($arraySelectColumns as $key => $column) {
            $arraySelectColumns[$key] = "{$column} as {$column}";
        }
        return $arraySelectColumns;
    }

    /**
     * @param $path
     * @return array|bool
     */
    public function getContentOutputCSV($path)
    {
        $content = parse_ini_file(($path));
        return $content;
    }

    /**
     * @param $settingOutput
     * @param $results
     * @param $formatConvention
     * @param $table
     */
    public function processOutputDataExtract($settingOutput, $results, $selectColumns, $table)
    {
        try {
            $summaryReport = new SummaryReport(date(Consts::DATE_FORMAT_YMDHIS));
            $detailReport = new DetailReport();
            $reportId = $summaryReport->makeReportId();
            $detailReport->setReportId($reportId);

            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = "Start";
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $tempPath = $settingOutput[Consts::TEMP_PATH];
            $fileName = $settingOutput[Consts::FILE_NAME];

            $setting = $this->setting;
            $nameTable = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
            $nameProcess = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_PROCESS_ID];

            $groupDelimiter = null;
            if ($nameTable == "User") {
                $groupDelimiter = $settingOutput[Consts::GROUP_DELIMITER];
            }

            $roleMaps = $settingManagement->getRoleMapInName($nameTable);
            $formatConvention = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];

            ksort($formatConvention);
            mkDirectory($tempPath);

            if (is_file("{$tempPath}/$fileName")) {
                $fileName = $this->removeExt($fileName) . "_" . Carbon::now()->format("Ymd") . rand(100, 999) . ".csv";
            }
            $file = fopen(("{$tempPath}/{$fileName}"), "wb");

            // Traceing
            $cmd = "Count";
            $message = sprintf("Processing %s Object %d records", $nameTable, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $putCount = 0;

            $createCount = 0;
            $updateCount = 0;

            // create csv file
            foreach ($results as $data) {
                $detailReport->clear();
                $detailReport->setCrudType("C");
                $detailReport->setProcessedDatetime(date(Consts::DATE_FORMAT_YMDHIS));

                // Captude primary_key
                $cnvData = (array)$data;
                $uid = $cnvData["ID"];
                $detailReport->setKeyspiderId($uid);

                $data = array_only((array)$data, $selectColumns);

                $dataTmp = [];
                foreach ($formatConvention as $index => $column) {
                    $line = null;

                    if (
                        $column == "UserToGroup" ||
                        $column == "UserToOrganization" ||
                        $column == "UserToRole" ||
                        $column == "UserToPrivilege"
                    ) {

                        $aliasTable = str_replace("UserTo", "", $column);
                        $line = $this->getUserToEloquent($uid, $aliasTable, $groupDelimiter);
                        array_push($dataTmp, $line);
                        continue;
                    }

                    $regColumnName = $this->regExpManagement->checkRegExpRecord($column);

                    if (isset($regColumnName)) {
                        $itemValue = $data[$regColumnName];
                        $line = $this->regExpManagement->convertDataFollowSetting($column, $itemValue);
                        array_push($dataTmp, $line);

                        continue;
                    }

                    preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $column, $matches);
                    if (!empty($matches)) {
                        $dataTmp[$index] = $this->executeExtend($column, $matches, $uid);
                        continue;
                    }

                    if (strpos($column, 'ELOQ;') !== false) {
                        // Get Eloquent string
                        preg_match("/\(ELOQ;(.*)\)/", $column, $matches, PREG_OFFSET_CAPTURE, 0);
                        $line = $this->regExpManagement->eloquentItem($uid, $matches[1][0]);
                        array_push($dataTmp, $line);
                        continue;
                    } else {
                        if (strpos($column, "TODAY()") !== false) {
                            $line = $this->regExpManagement->getEffectiveDate($column);
                        } else {
                            $isMache = preg_match("/\((\w+)\.(.*)\)/", $column, $matches, PREG_OFFSET_CAPTURE, 0);
                            if ($isMache) {
                                $column = $matches[2][0];
                                $line = $data[$column];
                            } else {
                                $isMache = preg_match("/\((.*)\)/", $column, $matches, PREG_OFFSET_CAPTURE, 0);
                                if ($isMache) {
                                    $line = $matches[1][0];
                                } else {
                                    $line = $column;
                                }
                            }
                        }
                    }

                    $twColumn = $nameTable . "." . $column;
                    if (in_array($twColumn, $getEncryptedFields)) {
                        $line = $settingManagement->passwordDecrypt($line);
                    }

                    if (strpos($column, config("const.ROLE_FLAG")) !== false) {
                        $exp = explode("-", $column);
                        $line = $settingManagement->getRoleFlagInName($roleMaps, $exp[1], $line);
                    }
                    array_push($dataTmp, $line);
                }
                fputcsv($file, $dataTmp, ",");

                $detailReport->setDataDetail(json_encode($dataTmp, JSON_UNESCAPED_UNICODE));
                $detailReport->create("success");

                $putCount++;
            }

            $scimInfo = array(
                "provisoning" => sprintf("%s (%s)", "CSV", $nameProcess),
                "table" => ucfirst(strtolower($nameTable)),
                "casesHandle" => count($results),
                "createCount" => $putCount,
                "updateCount" => 0,
                "deleteCount" => 0,
            );
            $settingManagement->summaryLogger($scimInfo);

            $errorCount = count($results) - ($putCount);
            $summaryReport->create("OUT", "CSV", $table, count($results), $putCount, 0, 0, $errorCount);

            // Traceing
            $cmd = "Extract";
            $message = sprintf(
                "Extract %s Object Success. %d records affected.\n%s",
                $nameTable,
                $putCount,
                "Extracted to file: $tempPath/$fileName"
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
            fclose($file);
        } catch (Exception $exception) {
            echo ("Extract to failed: \e[0;31;46m[$exception]\e[0m\n");
            Log::debug($exception);

            $scimInfo = $this->settingManagement->makeScimInfo(
                "Extract", "unknown", ucfirst(strtolower($nameTable)), "System", "Exception", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);

            $detailReport->create("error");

            $faildCnt = count($results) - $putCount;
            $summaryReport->create("OUT", "CSV", $table, count($results), $putCount, 0, 0, $faildCnt);

            // Traceing
            $cmd = "Faild";
            $message = sprintf(
                "Extract %s Object Faild. %d records faild. %s",
                $nameTable,
                $faildCnt,
                $exception->getMessage()
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
            fclose($file);
        }
    }

    private function getUserToEloquent($uid, $aliasTable, $groupDelimiter)
    {
        $eloquents = strtolower($aliasTable) . "s";
        $retValue = "";

        $user = \App\User::find($uid);
        foreach ($user->{$eloquents} as $eloquent) {
            if (!empty($retValue)) {
                $retValue = $retValue . $groupDelimiter;
            }

            switch ($aliasTable) {
                case "Group":
                    $retValue = $retValue . $eloquent->displayName;
                    break;
                    // case "Role":
                    //     $this->exportRoleFromKeyspider($array);
                    //     break;
                    // case "Group":
                    //     $this->exportGroupFromKeyspider($array);
                    //     break;
                    // case "Organization":
                    //     $this->exportOrganizationUnitFromKeyspider($array);
                    //     break;
            }
        }
        return $retValue;
    }

    /**
     * @param $file_name
     * @return string|string[]|null
     */
    public function removeExt($file_name)
    {
        $file = preg_replace("/\\.[^.\\s]{3,4}$/", "", $file_name);
        return $file;
    }

    private function overlayItem($formatConvention, $item)
    {
        foreach ($formatConvention as $key => $value) {
            $regColumnName = $this->regExpManagement->checkRegExpRecord($value);
            if (isset($regColumnName)) {
                $itemValue = $item[$key];
                $item[$key] = $this->regExpManagement->convertDataFollowSetting($value, $itemValue);
            }
            if (strpos($value, "TODAY()") !== false) {
                $item[$key] = $this->regExpManagement->getEffectiveDate($value);
            }
            preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $value, $matches);
            if (!empty($matches)) {
                $item[$key] = $this->executeExtend($value, $matches, $item["ID"]);
            }
        }
        $item["UpdateDate"] = Carbon::now()->format("Y/m/d H:i:s");
        return $item;
    }

    private function executeExtend($value, $matches, $id) {
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
        $keys = [];
        $tableName;
        if (!empty($matches[3])) {
            $params = explode(",", $matches[3]);
            $selectColumns = [];
            foreach ($params as $param) {
                $value = explode(".", $param);
                $tableName = $value[0];
                array_push($selectColumns, $value[1]);
            }
            $queries = DB::table($tableName)
                ->select($selectColumns)
                ->where('ID', $id)->first();
            foreach ($selectColumns as $selectColumn) {
                array_push($parameters, $queries->$selectColumn);
            }
        }
        return $clazz->$methodName($parameters);
    }

    public function processExtractToAD()
    {
        try {
            $summaryReport = new SummaryReport(date(Consts::DATE_FORMAT_YMDHIS));
            $detailReport = new DetailReport();
            $reportId = $summaryReport->makeReportId();
            $detailReport->setReportId($reportId);

            $setting = $this->setting;

            $table = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
            $extractedId = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_PROCESS_ID];
            $externalIdName = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTERNAL_ID];

            $extractCondition = $setting[Consts::EXTRACTION_PROCESS_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . " Provisioning Start --> Azure Active Directory";
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];

            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, $externalIdName, "DeleteFlag"]);

            if ($table == "User") {
                $allRoleFlags = $settingManagement->getRoleFlags();
                $selectColumnsAndID = array_merge($selectColumnsAndID, $allRoleFlags);

                $selectColumnsAndID = array_merge($selectColumnsAndID, ['LockFlag']);

                $getOfficeLicenseFields = $settingManagement->getOfficeLicenseFields();
                if (!empty($getOfficeLicenseFields)) {
                    $selectColumnsAndID = array_merge($selectColumnsAndID, [$getOfficeLicenseFields]);
                }
            }

            $joins = ($this->getJoinCondition($formatConvention, $settingManagement));
            foreach ($joins as $src => $des) {
                $selectColumns[] = $des;
            }

            $query = DB::table($table);
            $query = $query->select($selectColumnsAndID)
                ->where($whereData);
            $extractedSql = $query->toSql();
            $results = $query->get()->toArray();

            // Traceing
            $cmd = "Count --> Azure Active Directory";
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $detailReport->clear();
                $detailReport->setProcessedDatetime(date(Consts::DATE_FORMAT_YMDHIS));

                $userGraph = new UserGraphAPI($externalIdName);

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    // Check POST or OTHER. `empty` mean CREATE.
                    $isSoftwareDelete = false;
                    if (empty($item['externalID'])) {
                        // POST
                        if (!array_key_exists('accountEnabled', $item)) {
                            // $item['accountEnabled'] = true;
                            $item['accountEnabled'] = 0;
                        }
                    } else {
                        // PATCH
                        if ( array_key_exists(self::REMOVE_LICENSES, $item) ) {
                            // This mean Softwaredelete
                            if ((int)$item['DeleteFlag'] === 1) {
                                // $item['accountEnabled'] = false;
                                $item['accountEnabled'] = 1;
                                $isSoftwareDelete = true;
                            }
                        }
                    }

                    $item = json_decode(json_encode($item));
                    $detailReport->setKeyspiderId($item->ID);
                    $detailReport->setDataDetail(json_encode($item, JSON_UNESCAPED_UNICODE));

                    DB::beginTransaction();
                    // check resource is existed on AD or not
                    //TODO: need to change because userPrincipalName is not existed in group.
                    $uPN = $item->userPrincipalName ?? null;
                    if (($item->$externalIdName) && ($userGraph->getResourceDetails($item->$externalIdName, $table, $uPN))) {
                        if ($item->DeleteFlag == 1) {
                            //Delete resource
                            Log::info("Delete $table [$uPN] on AzureAD");
                            $detailReport->setExternalId($item->$externalIdName);
                            $detailReport->setCrudType("D");

                            if ($isSoftwareDelete) {
                                $userUpdated = $userGraph->updateResource((array)$item, $table);
                                if ($userUpdated == null) {
                                    $detailReport->create("error");

                                    DB::commit();
                                    continue;
                                }
                                $userGraph->removeLicenseDetail($item->$externalIdName, $table);
                                $$detailReport->create("success");

                                $deleteCount++;
                            } else {
                                $userGraph->removeLicenseDetail($item->$externalIdName, $table);
                                $userDeleted = $userGraph->deleteResource($item->$externalIdName, $table);
                                if ($userDeleted != null) {
                                    $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE]);
                                    $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                                    $updateQuery->update([$externalIdName => null]);

                                    if ($table == "Group") {
                                        $updateQuery = DB::table("UserToGroup");
                                        $updateQuery->where("Group_ID", $item->{"$primaryKey"});
                                        $updateQuery->delete();
                                    }
                                    $detailReport->create("success");

                                    $deleteCount++;
                                }
                            }
                        } else {
                            $detailReport->setCrudType("U");
                            $userUpdated = $userGraph->updateResource((array)$item, $table);
                            if ($userUpdated == null) {
                                $detailReport->setExternalId($item->$externalIdName);
                                $detailReport->create("error");
                                DB::commit();

                                continue;
                            }
                            $detailReport->setExternalId($item->$externalIdName);
                            $detailReport->create("success");

                            $updateCount++;
                        }
                        $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                    } //Not found resource, create it!
                    else {
                        $detailReport->setCrudType("C");
                        if ($item->DeleteFlag == 1) {
                            $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                            Log::info("User [$uPN] has DeleteFlag=1 and not existed on AzureAD, do nothing!");

                            $nowTbl = ucfirst(strtolower($table));
                            $kspId = $item->{"$primaryKey"};
                            Log::info("$nowTbl [$kspId] has DeleteFlag = 1 and not existed on AzureAD, do nothing!");

                            $scimInfo = array(
                                'provisoning' => 'AzureAD',
                                'scimMethod' => 'create',
                                'table' => $nowTbl,
                                'itemId' => $kspId,
                                'itemName' => 'Throw of creating',
                                'message' => "DeleteFlag = 1 and not existed on AzureAD, do nothing!",
                            );
                            $this->settingManagement->faildLogger($scimInfo);


                            $detailReport->create("error");

                            DB::commit();
                            continue;
                        }

                        $userOnAD = $userGraph->createResource((array)$item, $table);
                        if ($userOnAD == null) {
                            $detailReport->create("error");
                            DB::commit();
                            continue;
                        }
                        // TODO: create user on AD, update UpdateFlags and externalID.
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                        $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE]);
                        $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                        $updateQuery->update([$externalIdName => $userOnAD->getID()]);

                        $detailReport->setExternalId($userOnAD->getID());
                        $detailReport->create("success");

                        $createCount++;
                        var_dump($userOnAD);
                    }
                    DB::commit();
                }
            }

            $scimInfo = array(
                "provisoning" => "Azure Active Directory",
                "table" => ucfirst(strtolower($table)),
                "casesHandle" => count($results),
                "createCount" => $createCount,
                "updateCount" => $updateCount,
                "deleteCount" => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            $errorCount = count($results) - ($createCount + $updateCount + $deleteCount);
            $summaryReport->create("OUT", "Azure Active Directory", $table, count($results),
                $createCount, $updateCount, $deleteCount, $errorCount);

            // Traceing
            $cmd = $table . " Provisioning done --> Azure Active Directory";
            $summarys = "create = $createCount, update = $updateCount, delete = $deleteCount";
            if (count($results) == 0) {
                $summarys = "";
            }
            $message = sprintf(
                "Provisioning %s Object Success. %d objects affected.\n%s",
                $table,
                $updateCount + $createCount + $deleteCount,
                $summarys
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        } catch (Exception $exception) {
            echo ("\e[0;31;47m [$extractedId] $exception \e[0m \n");
            Log::debug($exception);

            $scimInfo = $this->settingManagement->makeScimInfo(
                "Azure Active Directory", "unknown", ucfirst(strtolower($table)), "System", "Exception", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);

            $detailReport->create("error");

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $summaryReport->create("OUT", "Azure Active Directory", $table, count($results),
                $createCount, $updateCount, $deleteCount, $faildCnt);

            $cmd = $table . " Provisioning Faild --> Azure Active Directory";
            $message = sprintf(
                "Provisioning %s Object Faild. %d objects faild.\n%s\n%s",
                $table,
                $faildCnt,
                "create = $createCount, update = $updateCount, delete = $deleteCount",
                $exception->getMessage()
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        }
    }

    public function processExtractToSCIM($scimLib)
    {
        try {
            $summaryReport = new SummaryReport(date(Consts::DATE_FORMAT_YMDHIS));
            $detailReport = new DetailReport();
            $reportId = $summaryReport->makeReportId();
            $detailReport->setReportId($reportId);

            $setting = $this->setting;

            $table = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
            $extractedId = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_PROCESS_ID];
            $externalIdName = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTERNAL_ID];

            $extractCondition = $setting[Consts::EXTRACTION_PROCESS_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . " Provisioning Start --> " . $scimLib->getServiceName();
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];

            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, $externalIdName, "DeleteFlag"]);

            if ($table == "User") {
                $allRoleFlags = $settingManagement->getRoleFlags();
                $selectColumnsAndID = array_merge($selectColumnsAndID, $allRoleFlags);
            }

            $joins = ($this->getJoinCondition($formatConvention, $settingManagement));
            foreach ($joins as $src => $des) {
                $selectColumns[] = $des;
            }

            $query = DB::table($table);
            $query = $query->select($selectColumnsAndID)
                ->where($whereData);
            $extractedSql = $query->toSql();
            $results = $query->get()->toArray();

            // Traceing
            $cmd = "Count --> " . $scimLib->getServiceName();
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib->initialize($setting, $externalIdName);

                foreach ($results as $key => $item) {
                    $detailReport->clear();
                    $detailReport->setProcessedDatetime(date(Consts::DATE_FORMAT_YMDHIS));

                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    $detailReport->setKeyspiderId($item["ID"]);
                    $detailReport->setDataDetail(json_encode($item, JSON_UNESCAPED_UNICODE));

                    foreach ($formatConvention as $key => $value) {
                        $item[$key] = $this->regExpManagement->getUpperValue($item, $key, $value, "default");
                    }

                    if ($item["DeleteFlag"] == "1") {
                        $detailReport->setCrudType("D");

                        $ext_id = "1";
                        if (!empty($item[$externalIdName])) {
                            $detailReportInfo["externalId"] = $item[$externalIdName];
                            $ext_id = $scimLib->deleteResource($table, $item);
                        }
                        if ($ext_id != null) {
                            if ($ext_id == "1") $ext_id = null;
                            DB::beginTransaction();
                            $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE]);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(["UpdateDate" => Carbon::now()->format("Y/m/d H:i:s")]);
                            $updateQuery->update([$externalIdName => $ext_id]);
                            $deleteCount++;
                            DB::commit();
                        }
                        $detailReport->create("success");

                        continue;
                    }

                    $ext_id = null;
                    if (!empty($item[$externalIdName])) {
                        $detailReport->setExternalId($item[$externalIdName]);
                        $detailReport->setCrudType("U");

                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        if ($ext_id != null) {
                            $updateCount++;
                        }
                    } else {
                        $detailReport->setCrudType("C");

                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        if ($ext_id != null) {
                            $createCount++;
                        }
                    }

                    if ($ext_id !== null) {
                        $scimLib->passwordResource($table, $item, $ext_id);
                        $scimLib->statusResource($table, $item, $ext_id);
                        $scimLib->userGroup($table, $item, $ext_id);
                        $scimLib->userRole($table, $item, $ext_id);
                        DB::beginTransaction();
                        $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE]);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(["UpdateDate" => Carbon::now()->format("Y/m/d H:i:s")]);
                        $updateQuery->update([$externalIdName => $ext_id]);
                        DB::commit();

                        $detailReport->create("success");
                    } else {
                        $detailReport->create("error");
                    }
                }
            }

            $scimInfo = array(
                "provisoning" => $scimLib->getServiceName(),
                "table" => ucfirst(strtolower($table)),
                "casesHandle" => count($results),
                "createCount" => $createCount,
                "updateCount" => $updateCount,
                "deleteCount" => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            $errorCount = count($results) - ($createCount + $updateCount + $deleteCount);
            $summaryReport->create(
                "OUT", "SCIM(" . $scimLib->getServiceName() . ")", $table, count($results),
                $createCount, $updateCount, $deleteCount, $errorCount
            );

            // Traceing
            $cmd = $table . " Provisioning done --> " . $scimLib->getServiceName();
            $summarys = "create = $createCount, update = $updateCount, delete = $deleteCount";
            if (count($results) == 0) {
                $summarys = "";
            }
            $message = sprintf(
                "Provisioning %s Object Success. %d objects affected.\n%s",
                $table,
                $updateCount + $createCount + $deleteCount,
                $summarys
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        } catch (Exception $exception) {
            echo ("\e[0;31;47m [$extractedId] $exception \e[0m \n");
            Log::debug($exception);

            $scimInfo = array(
                "provisoning" => $scimLib->getServiceName(),
                "scimMethod" => "unknown",
                "table" => ucfirst(strtolower($table)),
                "itemId" => "System",
                "itemName" => "Exception",
                "message" => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $detailReport->create("error");

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $summaryReport->create(
                "OUT", "SCIM(" . $scimLib->getServiceName() . ")", $table, count($results),
                $createCount, $updateCount, $deleteCount, $faildCnt
            );

            $cmd = $table . " Provisioning Faild --> " . $scimLib->getServiceName();
            $message = sprintf(
                "Provisioning %s Object Faild. %d objects faild.\n%s\n%s",
                $table,
                $faildCnt,
                "create = $createCount, update = $updateCount, delete = $deleteCount",
                $exception->getMessage()
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        }
    }

    public function processExtractToRDB()
    {
        try {
            $summaryReport = new SummaryReport(date(Consts::DATE_FORMAT_YMDHIS));
            $detailReport = new DetailReport();
            $reportId = $summaryReport->makeReportId();
            $detailReport->setReportId($reportId);

            $setting = $this->setting;

            $extractiontable = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
            $extractionProcessID = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_PROCESS_ID];
            $extractCondition = $setting[Consts::EXTRACTION_PROCESS_CONDITION];
            $connectionType = $setting[Consts::RDB_CONNECTING_CONFIGURATION][Consts::CONNECTION_TYPE];
            $exportTable = $setting[Consts::RDB_CONNECTING_CONFIGURATION][Consts::EXPORT_TABLE];
            $primaryColumn = $setting[Consts::RDB_CONNECTING_CONFIGURATION][Consts::PRIMARY_COLUMN];
            $externalId = $setting[Consts::RDB_CONNECTING_CONFIGURATION][Consts::EXTERNAL_ID];
            $rdbDeleteType = config("database.connections")[$connectionType][Consts::DELETE_TYPE] ?? Null;

            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($extractiontable);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);
            $deleteFlagName = $settingManagement->getDeleteFlagColumnName($extractiontable);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $extractiontable . " Extracting start --> RDB";
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();

            $allSelectedColumns = $this->getColumnsSelect($extractiontable, $formatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];

            $getEncryptedFields = $settingManagement->getEncryptedFields();
            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, $externalId, $deleteFlagName]);
            $joins = ($this->getJoinCondition($formatConvention, $settingManagement));

            foreach ($joins as $src => $des) {
                $selectColumns[] = $des;
            }

            $querytable = DB::table($extractiontable);
            $query = $querytable->select($selectColumnsAndID)->where($whereData);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = "Count --> RDB";
            $message = sprintf("Extracting %s Object %d records", $extractiontable, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                foreach ($results as $key => $item) {
                    $detailReport->clear();
                    $detailReport->setProcessedDatetime(date(Consts::DATE_FORMAT_YMDHIS));

                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);
                    $exportDate = array_intersect_key($item, $formatConvention);

                    $detailReport->setKeyspiderId($item["ID"]);
                    $detailReport->setDataDetail(json_encode($exportDate, JSON_UNESCAPED_UNICODE));

                    $scimInfo = array(
                        'provisoning' => 'RDB',
                        'scimMethod' => '',
                        'table' => ucfirst(strtolower($extractiontable)),
                        'itemId' => $item['ID'],
                        'itemName' => '',
                        'message' => '',
                    );

                    $getEncryptedFields = $settingManagement->getEncryptedFields();
                    foreach ($exportDate as $kv => $iv) {
                        $twColumn = "$extractiontable.$kv";
                        if (in_array($twColumn, $getEncryptedFields)) {
                            $exportDate[$kv] = $settingManagement->passwordDecrypt($iv);
                        }
                    }

                    DB::beginTransaction();
                    $rdbTable = DB::connection($connectionType)->table($exportTable);

                    if ($item[$deleteFlagName] == '1') {
                        $detailReport->setCrudType("D");
                        if (!empty($item[$externalId])) {
                            $detailReportInfo["externalId"] = $item[$externalId];
                            $deleteData = $rdbTable->where($primaryColumn, $item[$externalId]);
                            if ($rdbDeleteType == "logical") {
                                // Logical Delete
                                $deleteData->update($exportDate);
                                $deleteCount++;
                                $scimInfo['scimMethod'] = "delete";

                            } elseif ($rdbDeleteType == "physical") {
                                // Physical Delete
                                $deleteData->delete();
                                $item[$externalId] = null;
                                $deleteCount++;
                                $scimInfo['scimMethod'] = "delete";
                            } else {
                                Log::error("Delete for $primaryKey = $item[$primaryKey] failed. Please set [logical] or [physical] in database.php.");
                                $scimInfo['message'] = "Delete for $primaryKey = $item[$primaryKey] failed. Please set [logical] or [physical] in database.php.";
                                $this->settingManagement->faildLogger($scimInfo);

                                $detailReport->create("error");

                                continue;
                            }
                        }
                        $settingManagement->setUpdateFlags($extractionProcessID, $item["$primaryKey"], $extractiontable, $value = 0);
                        $updateQuery = DB::table($extractiontable)->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(["UpdateDate" => $item["UpdateDate"]]);
                        $updateQuery->update([$externalId => $item[$externalId]]);
                        DB::commit();
                        $this->settingManagement->detailLogger($scimInfo);

                        $detailReport->create("success");

                        continue;
                    }

                    $rdbId = $item[$externalId];
                    if (!empty($item[$externalId])) {
                        $detailReport->setExternalId($item[$externalId]);
                        $detailReport->setCrudType("U");

                        // UPDATE
                        $rdbTable->where($primaryColumn, $item[$externalId])->update($exportDate);
                        $updateCount++;
                        $scimInfo['scimMethod'] = "update";

                    } else {
                        $detailReport->setCrudType("C");

                        // CREATE
                        $rdbId = $rdbTable->insertGetId($exportDate, $primaryColumn);
                        $createCount++;
                        $scimInfo['scimMethod'] = "create";
                    }
                    $settingManagement->setUpdateFlags($extractionProcessID, $item["$primaryKey"], $extractiontable, $value = 0);
                    $updateQuery = DB::table($extractiontable)->where($primaryKey, $item["$primaryKey"]);
                    $updateQuery->update(["UpdateDate" => $item["UpdateDate"]]);
                    $updateQuery->update([$externalId => $rdbId]);
                    DB::commit();

                    $this->settingManagement->detailLogger($scimInfo);

                    $detailReport->create("success");
                }
            }

            // Traceing
            $cmd = $extractiontable . ' Extracting done --> RDB';
            $summarys = "create = $createCount, update = $updateCount, delete = $deleteCount";
            if (count($results) == 0) {
                $summarys = "";
            }
            $message = sprintf(
                "Extracting %s Object Success. %d objects affected.\n%s",
                $extractiontable,
                $updateCount + $createCount + $deleteCount,
                $summarys
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $scimInfo = array(
                'provisoning' => 'RDB',
                'table' => ucfirst(strtolower($extractiontable)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            $errorCount = count($results) - ($createCount + $updateCount + $deleteCount);
            $summaryReport->create("OUT", "RDB", $extractiontable, count($results),
                $createCount, $updateCount, $deleteCount, $errorCount);

        } catch (\Exception $exception) {
            DB::rollback();
            echo ("\e[0;31;47m [$extractionProcessID] $exception \e[0m \n");
            Log::debug($exception);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $summaryReport->create("OUT", "RDB", $extractiontable, count($results), $createCount,
                $updateCount, $deleteCount, $faildCnt);

            $cmd = $extractiontable . ' Extracting Faild --> RDB';
            $message = sprintf(
                "Extracting %s Object Faild. %d objects faild.\n%s\n%s",
                $extractiontable,
                $faildCnt,
                "create = $createCount, update = $updateCount, delete = $deleteCount",
                $exception->getMessage()
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);

            $detailReport->create("error");
        }
    }

    public function processExtractToLDAP($worker)
    {
        try {
            $summaryReport = new SummaryReport(date(Consts::DATE_FORMAT_YMDHIS));
            $worker->detailReport = new DetailReport();
            $reportId = $summaryReport->makeReportId();
            $worker->detailReport->setReportId($reportId);

            $setting = $this->setting;
            $worker->initialize($setting);

            $tableMaster = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
            $extractCondition = $setting[Consts::EXTRACTION_PROCESS_CONDITION];
            $nameColumnUpdate = "UpdateFlags";

            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $whereData = $worker->extractCondition2($extractCondition, $nameColumnUpdate);
            $worker->setTableMaster($tableMaster);

            $query = DB::table($tableMaster);
            $query = $query->where($whereData);
            $extractedSql = $query->toSql();

            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                Log::info("Export to AD from " . $tableMaster . " entry(".count($results).")");
                echo "Export to AD from " . $tableMaster . " entry(".count($results).")\n";

                // If a successful connection is made to your server, the provider will be returned.
                $worker->setProvider($worker->configureLDAPServer()->connect());

                foreach ($results as $data) {
                    $worker->detailReport->clear();
                    $worker->detailReport->setProcessedDatetime(date(Consts::DATE_FORMAT_YMDHIS));
                    $worker->detailReport->setKeyspiderId($data->ID);

                    $array = json_decode(json_encode($data), true);
                    $worker->detailReport->setDataDetail(json_encode(((array) $data), JSON_UNESCAPED_UNICODE));

                    $tmpCrud = [
                        "C" => $worker->getCreateCount(),
                        "U" => $worker->getUpdateCount(),
                        "D" => $worker->getDeleteCount(),
                    ];

                    // Skip because 'cn' cannot be created
                    if (empty($array["Name"])) {
                        if (empty($array["displayName"])) {
                            $worker->setUpdateFlags2($array["ID"]);
                            continue;
                        }
                    }

                    switch ($worker->getTableMaster()) {
                    case "User":
                        $worker->exportUserFromKeyspider($array);
                        break;
                    // case "Role":
                    //     $worker->exportRoleFromKeyspider($array);
                    //     break;
                    case "Group":
                        $worker->exportGroupFromKeyspider($array);
                        break;
                    case "Organization":
                        $worker->exportOrganizationUnitFromKeyspider($array);
                        break;
                    }
                }
            }

            $errorCount = count($results) - 
                          ($worker->getCreateCount() + $worker->getUpdateCount() + $worker->getDeleteCount());
            $summaryReport->create(
                "OUT", "LDAP", $tableMaster, count($results), $worker->getCreateCount(),
                $worker->getUpdateCount(), $worker->getDeleteCount(), $errorCount
            );
        } catch (Exception $exception) {
            Log::error($exception);

            $worker->detailReport->create("error");

            $errorCount = count($results) - 
                          ($worker->getCreateCount() + $worker->getUpdateCount() + $worker->getDeleteCount());
            $summaryReport->create(
                "OUT", "LDAP", $tableMaster, count($results), $worker->getCreateCount(),
                $worker->getUpdateCount(), $worker->getDeleteCount(), $errorCount
            );
        }
    }

}
