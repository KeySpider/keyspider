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
use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\UserGraphAPI;
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
    const PLUGINS_DIR = "App\\Commons\\Plugins\\";
    const RDB_CONFIGRATION = "Extraction RDB Connecting Configration";
    const DB_CONNECTION = "database.connections";

    // see set attribute value detail
    // https://support.microsoft.com/ja-jp/help/305144/how-to-use-useraccountcontrol-to-manipulate-user-account-properties
    private const NORMAL_ACCOUNT = 544;
    private const DISABLE_ACCOUNT = 514;
    // private const HOLDING_ACCOUNT = 66082;
    private const HOLDING_ACCOUNT = 66050;

    private const GROUP_TYPE_365 = 2;
    private const GROUP_TYPE_SECURITY = -2147483646;

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
            $table = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"];
            $extractedId = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionProcessID"];
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

            $scimInfo = array(
                "provisoning" => "Extract",
                "scimMethod" => "unknown",
                "table" => ucfirst(strtolower($table)),
                "itemId" => "System",
                "itemName" => "Exception",
                "message" => $exception->getMessage(),
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

            $tempPath = $settingOutput["TempPath"];
            $fileName = $settingOutput["FileName"];

            $setting = $this->setting;
            $nameTable = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"];
            $nameProcess = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionProcessID"];            

            $groupDelimiter = null;
            if ($nameTable == "User") {
                $groupDelimiter = $settingOutput["GroupDelimiter"];
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
                // Captude primary_key
                $cnvData = (array)$data;
                $uid = $cnvData["ID"];
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

            $scimInfo = array(
                "provisoning" => "Extract",
                "scimMethod" => "unknown",
                "table" => ucfirst(strtolower($nameTable)),
                "itemId" => "System",
                "itemName" => "Exception",
                "message" => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            // Traceing
            $faildCnt = count($results) - $putCount;
            $cmd = "Faild";
            $message = sprintf(
                "Extract %s Object Faild. %d records faild. %s",
                $nameTable,
                $faildCnt,
                $e->getMessage()
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
        $item['UpdateDate'] = Carbon::now()->format('Y/m/d H:i:s');
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
            $setting = $this->setting;
            $table = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"];
            $extractedId = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionProcessID"];

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
            $cmd = $table . " Provisioning start --> Azure Active Directory";
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $getEncryptedFields = $settingManagement->getEncryptedFields();
            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, "externalID", "DeleteFlag"]);

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
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = "Count --> Azure Active Directory";
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            // Linkage LockFlag
            $linkageLockFlag = array_key_exists('accountEnabled', $formatConvention);

            if ($results) {
                $userGraph = new UserGraphAPI();

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    if ($table == 'User') {
                        if ($linkageLockFlag) {
                            $eaLocked = True;
                            if ((int)$item['LockFlag'] == 1) {
                                $eaLocked = False;
                            }
                            $item['accountEnabled'] = $eaLocked;
                        } else {
                            unset($item['accountEnabled']);
                        }
                    }
                    
                    $item = json_decode(json_encode($item));

                    DB::beginTransaction();
                    // check resource is existed on AD or not
                    //TODO: need to change because userPrincipalName is not existed in group.
                    $uPN = $item->userPrincipalName ?? null;
                    if (($item->externalID) && ($userGraph->getResourceDetails($item->externalID, $table, $uPN))) {
                        if ($item->DeleteFlag == 1) {
                            //Delete resource
                            Log::info("Delete $table [$uPN] on AzureAD");

                            $userGraph->removeLicenseDetail($item->externalID);
                            $userDeleted = $userGraph->deleteResource($item->externalID, $table);
                            if ($userDeleted != null) {
                            $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"]);
                            $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                            $updateQuery->update(["externalID" => null]);

                            if ($table == "Group") {
                                $updateQuery = DB::table("UserToGroup");
                                $updateQuery->where("Group_ID", $item->{"$primaryKey"});
                                $updateQuery->delete();
                            }
                            $deleteCount++;
                            }
                        } else {
                            $userUpdated = $userGraph->updateResource((array)$item, $table);
                            if ($userUpdated == null) {
                                DB::commit();
                                continue;
                            }
                            $updateCount++;
                        }
                        $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                    } //Not found resource, create it!
                    else {
                        if ($item->DeleteFlag == 1) {
                            $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                            Log::info("User [$uPN] has DeleteFlag=1 and not existed on AzureAD, do nothing!");

                            $nowTbl = ucfirst(strtolower($table));
                            $kspId = $item->{"$primaryKey"};
                            Log::info("$nowTbl [$kspId] has DeleteFlag = 1 and not existed on TrustLogin, do nothing!");

                            $scimInfo = array(
                                'provisoning' => 'TrustLogin',
                                'scimMethod' => 'create',
                                'table' => $nowTbl,
                                'itemId' => $kspId,
                                'itemName' => 'Throw of creating',
                                'message' => "DeleteFlag = 1 and not existed on TrustLogn, do nothing!",
                            );
                            $this->settingManagement->faildLogger($scimInfo);

                            DB::commit();
                            continue;
                        }
                        // Error occurrd without accountEnabled
                        $userItem = (array)$item;
                        if (!array_key_exists('accountEnabled', $userItem)) {
                            $userItem['accountEnabled']  = True;
                        }

                        $userOnAD = $userGraph->createResource($userItem, $table);
                        if ($userOnAD == null) {
                            DB::commit();
                            continue;
                        }
                        // TODO: create user on AD, update UpdateFlags and externalID.
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                        $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"]);
                        $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                        $updateQuery->update(["externalID" => $userOnAD->getID()]);
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

            $scimInfo = array(
                "provisoning" => "Azure Active Directory",
                "scimMethod" => "unknown",
                "table" => ucfirst(strtolower($table)),
                "itemId" => "System",
                "itemName" => "Exception",
                "message" => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
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
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    foreach ($formatConvention as $key => $value) {
                        $item[$key] = $this->regExpManagement->getUpperValue($item, $key, $value, "default");
                    }

                    if ($item["DeleteFlag"] == "1") {
                        $ext_id = "1";
                        if (!empty($item[$externalIdName])) {
                            $ext_id = $scimLib->deleteResource($table, $item);
                        }
                        if ($ext_id != null) {
                            if ($ext_id == "1") $ext_id = null;
                            DB::beginTransaction();
                            $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"]);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(["UpdateDate" => Carbon::now()->format("Y/m/d H:i:s")]);
                            $updateQuery->update([$externalIdName => $ext_id]);
                            $deleteCount++;
                            DB::commit();
                        }
                        continue;
                    }

                    $ext_id = null;
                    if (!empty($item[$externalIdName])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        if ($ext_id != null) {
                        $updateCount++;
                        }
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        if ($ext_id != null) {
                        $createCount++;
                    }
                    }

                    if ($ext_id !== null) {
                        $scimLib->passwordResource($table, $item, $ext_id);
                        $scimLib->userGroup($table, $item, $ext_id);
                        $scimLib->userRole($table, $item, $ext_id);
                        DB::beginTransaction();
                        $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"]);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(["UpdateDate" => Carbon::now()->format("Y/m/d H:i:s")]);
                        $updateQuery->update([$externalIdName => $ext_id]);
                        DB::commit();
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

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
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
            $setting = $this->setting;
            $extractiontable = $setting[self::EXTRACTION_CONFIGURATION]["ExtractionTable"];
            $extractionProcessID = $setting[self::EXTRACTION_CONFIGURATION]["ExtractionProcessID"];
            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $connectionType = $setting[self::RDB_CONFIGRATION]["ConnectionType"];
            $exportTable = $setting[self::RDB_CONFIGRATION]["ExportTable"];
            $primaryColumn = $setting[self::RDB_CONFIGRATION]["PrimaryColumn"];
            $externalId = $setting[self::RDB_CONFIGRATION]["ExternalId"];
            $rdbDeleteType = config(self::DB_CONNECTION)[$connectionType]["deleteType"] ?? Null;

            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($extractiontable);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);
            $deleteFlagName = $settingManagement->getDeleteFlagColumnName($extractiontable);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $extractiontable . " Provisioning start --> RDB";
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
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
            $message = sprintf("Processing %s Object %d records", $extractiontable, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);
                    $exportDate = array_intersect_key($item, $formatConvention);

                    DB::beginTransaction();
                    $rdbTable = DB::connection($connectionType)->table($exportTable);

                    if ($item[$deleteFlagName] == '1') {
                        if (!empty($item[$externalId])) {
                            $deleteData = $rdbTable->where($primaryColumn, $item[$externalId]);
                            if ($rdbDeleteType == "logical") {
                                // Logical Delete
                                $deleteData->update($exportDate);
                                $deleteCount++;
                            } elseif ($rdbDeleteType == "physical") {
                                // Physical Delete
                                $deleteData->delete();
                                $item[$externalId] = "";
                                $deleteCount++;
                            } else {
                                Log::error("Delete for $primaryKey = $item[$primaryKey] failed. Please set [logical] or [physical] in database.php.");
                                continue;
                            }
                        }
                        $settingManagement->setUpdateFlags($extractionProcessID, $item["$primaryKey"], $extractiontable, $value = 0);
                        $updateQuery = DB::table($extractiontable)->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(["UpdateDate" => $item["UpdateDate"]]);
                        $updateQuery->update([$externalId => $item[$externalId]]);
                        DB::commit();
                        continue;
                    }

                    $rdbId = $item[$externalId];
                    if (!empty($item[$externalId])) {
                        // UPDATE
                        $rdbTable->where($primaryColumn, $item[$externalId])->update($exportDate);
                        $updateCount++;
                    } else {
                        // CREATE
                        $rdbId = $rdbTable->insertGetId($exportDate, $primaryColumn);
                        $createCount++;
                    }
                    $settingManagement->setUpdateFlags($extractionProcessID, $item["$primaryKey"], $extractiontable, $value = 0);
                    $updateQuery = DB::table($extractiontable)->where($primaryKey, $item["$primaryKey"]);
                    $updateQuery->update(["UpdateDate" => $item["UpdateDate"]]);
                    $updateQuery->update([$externalId => $rdbId]);
                    DB::commit();
                }
            }

            // Traceing
            $cmd = $extractiontable . ' Provisioning done --> RDB';
            $summarys = "create = $createCount, update = $updateCount, delete = $deleteCount";
            if (count($results) == 0) {
                $summarys = "";
            }
            $message = sprintf(
                "Provisioning %s Object Success. %d objects affected.\n%s",
                $extractiontable,
                $updateCount + $createCount + $deleteCount,
                $summarys
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        } catch (Exception $exception) {
            DB::rollback();
            echo ("\e[0;31;47m [$extractionProcessID] $exception \e[0m \n");
            Log::debug($exception);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $extractiontable . ' Provisioning Faild --> RDB';
            $message = sprintf(
                "Provisioning %s Object Faild. %d objects faild.\n%s\n%s",
                $extractiontable,
                $faildCnt,
                "create = $createCount, update = $updateCount, delete = $deleteCount",
                $exception->getMessage()
            );
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);
        }
    }
    public function processExtractToLDAP()
    {
        try {
            $setting = $this->setting;
            $tableMaster = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"];
            $extractCondition = $setting[Consts::EXTRACTION_PROCESS_CONDITION];
            $nameColumnUpdate = "UpdateFlags";

            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            // If a successful connection is made to your server, the provider will be returned.
            $this->provider = $this->configureLDAPServer()->connect();

            $whereData = $this->extractCondition2($extractCondition, $nameColumnUpdate);
            $this->tableMaster = $tableMaster;

            $query = DB::table($tableMaster);
            $query = $query->where($whereData);
            $extractedSql = $query->toSql();

            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                Log::info("Export to AD from " . $tableMaster . " entry(".count($results).")");
                echo "Export to AD from " . $tableMaster . " entry(".count($results).")\n";

                foreach ($results as $data) {
                    $array = json_decode(json_encode($data), true);
                    // Skip because 'cn' cannot be created
                    if (empty($array["Name"])) {
                        if (empty($array["displayName"])) {
                        $this->setUpdateFlags2($array["ID"]);
                        continue;
}

                    switch ($this->tableMaster) {
                    case "User":
                        $this->exportUserFromKeyspider($array);
                        break;
                    // case "Role":
                    //     $this->exportRoleFromKeyspider($array);
                    //     break;
                    case "Group":
                        $this->exportGroupFromKeyspider($array);
                        break;
                    case "Organization":
                        $this->exportOrganizationUnitFromKeyspider($array);
                        break;
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * Add a connection provider to Adldap.
     *
     */
    private function configureLDAPServer()
    {
        $setting = $this->setting;
        $ldapHosts =    $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapHosts"];
        $ldapPort =     $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapPort"];
        $LdapBaseDn =   $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];
        $LdapUsername = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapUsername"];
        $LdapPassword = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapPassword"];
        $UseSSL =       $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["UseSSL"];
        $UseTLS =       $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["UseTLS"];

        $adLdap = new \Adldap\Adldap();

        $config = [  
//            "hosts"    => [ $ldapHosts ],
            "hosts"    => explode(",", $ldapHosts),
            "port"     => $ldapPort,
            "base_dn"  => $LdapBaseDn,
            "username" => $LdapUsername,
            "password" => $LdapPassword,
            "use_ssl"  => ($UseSSL == true),
            "use_tls"  => ($UseTLS == true),
        ];

        // Add a connection provider to Adldap.
        $adLdap->addProvider($config);

        return $adLdap;
    }

    public function extractCondition2($extractCondition, $nameColumnUpdate)
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

    private function conversionArrayValue($array)
    {
        $setting = $this->setting;
        $conversions = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];

        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $regExpManagement = new RegExpsManager();

        $prefixArray = $this->array_key_prefix($array, $this->tableMaster);
        
        $fields = [];

        foreach ($conversions as $key => $nameTable) {
            $explodeItem = explode(".", $nameTable);
            $item = $explodeItem[count($explodeItem) -1];

            if ($explodeItem[0] == "(".$this->tableMaster){
                $item = $nameTable;
            }

            if ($item === "admin") {
                $fields[$key] = "admin";
            } elseif ($item === "TODAY()") {
                $fields[$key] = Carbon::now()->format("Y/m/d");
            } elseif ($item === "0") {
                $fields[$key] = "0";
            } else {
                // Set ini file const value
                $itemValue = $item;
                // Swap const value and record value
                if (array_key_exists($nameTable, $prefixArray)) {
                    $itemValue = $array[$item];
                }

                // Need a decrypt?
                if (in_array($nameTable, $getEncryptedFields)) {
                    $itemValue = $settingManagement->passwordDecrypt($itemValue);
                }
                // process with regular expressions?
                $columnName = $regExpManagement->checkRegExpRecord($itemValue);

                if (isset($columnName)) {
                    // $slow = strtolower($columnName);
                    // if (array_key_exists($slow, $array)) {
                    if (array_key_exists($columnName, $array)) {
                        $recValue = $array[$columnName];
                        $fields[$key] = $regExpManagement->convertDataFollowSetting($itemValue, $recValue);
                    }
                } else {
                    $fields[$key] = $itemValue;
                }
            }
        }
        return $fields;
    }

    private function array_key_prefix(array $array, $prefix = "")
    {
        $result = array();
        foreach ($array as $key => $value) {
            $result[$prefix . "." . $key] = $value;
        }
        return $result;
    }

    public function exportUserFromKeyspider($user)
    {
        try {
            $setting = $this->setting;
            $ldapSearchPath =  $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapSearchPath"];
            $ldapSearchColum = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapSearchColum"];
            $ldapBasePath =    $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];
            $useSSL =          $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["UseSSL"];
            $defaultPassword = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["DefaultPassword"];

            $ldapBaseDn = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];

            // Finding a record.
            try {
                $entry = $this->provider->search()
                    ->whereEquals($ldapSearchPath, $user[$ldapSearchColum])->firstOrFail();
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info("Record wasn't found! : $ldapSearchPath = $user[$ldapSearchColum]");
                $entry = false;
            }

            $ldapUser = $this->conversionArrayValue($user);

            $is_success = false;
            if ($entry) {
                // Update or Delete LDAP entry. Setting a model's attribute.
                // disble user
                if ($user["DeleteFlag"] == "1") {
                    $ldapUser["userAccountControl"] = self::DISABLE_ACCOUNT;
                }
                $is_success = $entry->update($ldapUser);
            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                // $ldapUser["userAccountControl"] = self::HOLDING_ACCOUNT;
                // $ldapUser["userAccountControl"] = self::NORMAL_ACCOUNT;
                // $ldapUser["pwdLastSet"] = 0;

                $entry =  $this->provider->make()->user();
                foreach ($ldapUser as $key => $value) {
                    if ($key == "dn") {
                        $entry->setAttribute($key, "${ldapSearchPath}=${value},${ldapBaseDn}");
                    } else if ($key == "objectclass") {
                        $entry->setAttribute($key, explode(",", $value));
                    } else {
                        $entry->setAttribute($key, $value);
                    }
                }
                if ($useSSL) {
                    $entry->setPassword($defaultPassword);
                }
                $is_success = $entry->save();

//                if ($is_success) {
//                    $is_success = $entry->update($ldapUser);    
//                }
            }

            // remove & add memberOf
            $removeGroups = $entry->getGroups();
            foreach ($removeGroups as $group) {
                $entry->removeGroup($group);
            }

            $addGroups = $this->getMemberOfLDAP($user["ID"]);
            foreach ($addGroups as $index => $group) {
                $fmt = sprintf("CN=%s,%s", $group, $ldapBaseDn);
                $entry->addGroup($fmt);
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($user["ID"]);
            }
        } catch (\Adldap\Auth\BindException $e) {
            Log::error($e);
            // There was an issue binding / connecting to the server.
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    private function getMemberOfLDAP($uid)
    {
        $table = "UserToGroup";
        $queries = DB::table($table)
                    ->select("Group_ID")
                    ->where("User_ID", $uid)
                    ->where("DeleteFlag", "0")->get();

        $groupIds = [];
        foreach ($queries as $key => $value) {
            $groupIds[] = $value->Group_ID;
        }

        $table = "Group";
        $queries = DB::table($table)
                    ->select("displayName")
                    ->whereIn("ID", $groupIds)
                    ->get();

        $addGroupIDs = [];

        foreach ($queries as $key => $value) {
            $cnv = (array)$value;
            foreach ($cnv as $key => $value) {
                $addGroupIDs[] = $value;
            }    
        }
        return $addGroupIDs; 

    }


    public function exportGroupFromKeyspider($data)
    {
        try {
            $setting = $this->setting;
            $ldapBasePath = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];

            $ldapGroup = $this->conversionArrayValue($data);

            $ldapGroup["groupType"] = self::GROUP_TYPE_365;
            if (!empty($data["groupTypes"])) {
                if ($data["groupTypes"] == "Security") {
                    $ldapGroup["groupType"] = self::GROUP_TYPE_SECURITY;
                }
            }

            // Finding a record.
            try {
                $group = $this->provider->search()->groups()->find($data["displayName"]);
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info(sprintf("Group wasn't found! : %s = %s", $ldapBasePath, $data["displayName"]));
                $group = false;
            }

            $is_success = false;
            if ($group) {
               // Update or Delete LDAP entry. Setting a model's attribute.
                // $is_success = $group->update($ldapGroup);
                try {
                    $is_success = $group->update($ldapGroup);
                } catch (Exception $exception) {
                    Log::info($ldapGroup);
                    Log::error($exception);
                    $is_success = false;
                }

                // disble user
                if ($data["DeleteFlag"] == "1") {
                    $group->delete();
                }

            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                $group =  $this->provider->make()->group([
                    "cn"     => $ldapGroup["cn"],
                ]);

                $is_success = $group->save();
                if ($is_success) {
                    try {
                        $is_success = $group->update($ldapGroup);
                    } catch (Exception $exception) {
                        Log::info($ldapGroup);
                        Log::error($exception);
                        $is_success = false;
                    }
                }
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($data["ID"]);
            }
        } catch (\Adldap\Auth\BindException $e) {
            Log::error($e);
            // There was an issue binding / connecting to the server.
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    public function exportOrganizationUnitFromKeyspider($data)
    {
        try {
            $setting = $this->setting;
            $ldapBasePath = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];
            $ldapOu = $this->conversionArrayValue($data);

            // Finding a record.
            try {
                $ou = $this->provider->search()->ous()->find($data["Name"]);
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info(sprintf("Organization wasn't found! : [%s] = [%s]", $ldapBasePath, $data["Name"]));
                $ou = false;
            }

            $is_success = false;
            if ($ou) {
               // Update or Delete LDAP entry. Setting a model's attribute.
                $is_success = $ou->update($ldapOu);
                // disble ou
                if ($data["DeleteFlag"] == "1") {
                    $ou->delete();
                }

            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                $rdn = sprintf("ou=%s,%s", $ldapOu["cn"], $ldapBasePath);
                $ou =  $this->provider->make()->ou([
                    "cn"     => $ldapOu["cn"],
                    "dn"     => $rdn,
                ]);
                $is_success = $ou->save();
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($data["ID"], true);
            }
        } catch (\Adldap\Auth\BindException $e) {
            Log::error($e);
            // There was an issue binding / connecting to the server.
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    private function resetTransferredFlag($id, $ouFlag = false)
    {
        $this->setUpdateFlags2($id);
    }

    private function setUpdateFlags2($id)
    {
        $setting = $this->setting;
        $itemTable = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionTable"];
        $addFlagName = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["ExtractionProcessID"];

        try {
            DB::beginTransaction();
            $userRecord = DB::table($itemTable)->where("ID", $id)->first();

            $userRecord = (array)DB::table($itemTable)
                ->where("ID", $id)
                ->get(["UpdateFlags"])->toArray()[0];
    
            $updateFlags = json_decode($userRecord["UpdateFlags"], true);
            $updateFlags[$addFlagName] = "0";
            $setValues["UpdateFlags"] = json_encode($updateFlags);
    
            DB::table($itemTable)->where("ID", $id)
                ->update($setValues);
            DB::commit();
    
        } catch (Exception $e) {
            DB::rollback();
            Log::error($e);
        }
    }

}
