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

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SCIM\Box\SCIMToBox;
use App\Ldaplibs\SCIM\GoogleWorkspace\SCIMToGoogleWorkspace;
use App\Ldaplibs\SCIM\OneLogin\SCIMToOneLogin;
use App\Ldaplibs\SCIM\Salesforce\SCIMToSalesforce;
use App\Ldaplibs\SCIM\Slack\SCIMToSlack;
use App\Ldaplibs\SCIM\TrustLogin\SCIMToTrustLogin;
use App\Ldaplibs\SCIM\Zoom\SCIMToZoom;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\UserGraphAPI;
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
    const EXTRACTION_CONDITION = "Extraction Condition";
    const EXTRACTION_CONFIGURATION = "Extraction Process Basic Configuration";
    const OUTPUT_PROCESS_CONVERSION = "Output Process Conversion";
    const EXTRACTION_PROCESS_FORMAT_CONVERSION = "Extraction Process Format Conversion";
    const PLUGINS_DIR = "App\\Commons\\Plugins\\";
    const RDB_CONFIGRATION = "Extraction RDB Connecting Configration";
    const DB_CONNECTION = "database.connections";

    protected $setting;
    protected $regExpManagement;
    protected $settingManagement;

    /**
     * DBExtractor constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
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

            preg_match('/\((\w+)\.(.*)\)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);

            if (strpos($value, 'ELOQ') !== false) {
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
            $dataType = 'csv';
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];
            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);
            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];

            $realFormatConvention = [];
            foreach ($formatConvention as $idx => $dbColumn) {
                $regColumnName = $this->regExpManagement->checkRegExpRecord($dbColumn);
                if (isset($regColumnName)) {
                    $realFormatConvention[] = '(' . $table . '.' . $regColumnName . ')';
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
                $cmd = 'Start';
                $message = "";
                $settingManagement->traceProcessInfo($dbt, $cmd, $message);

                //Start to extract
                $pathOutput = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
                $settingOutput = $this->getContentOutputCSV($pathOutput);
                $this->processOutputDataExtract($settingOutput, $results, $aliasColumns, $table);

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    // update flag
                    $keyString = $item->{"$primaryKey"};
                    $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = '0');
                }
                // Traceing
                $cmd = 'Extract';
                $message = sprintf("Extract %s Object Success.", $table);
                $settingManagement->traceProcessInfo($dbt, $cmd, $message);
            }
            DB::commit();
        } catch (Exception $exception) {
            echo ("\e[0;31;47m [$extractedId] $exception \e[0m \n");
            Log::debug($exception);

            $scimInfo = array(
                'provisoning' => 'Extract',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            // Traceing
            $cmd = 'Faild';
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
                if (strpos((string)$condition, 'TODAY()') !== false) {
                    $condition = $this->regExpManagement->getEffectiveDate($condition);
                    array_push($whereData, [$key, '<=', $condition]);
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
            if ($condition === 'TODAY() + 7') {
                $condition = Carbon::now()->addDay(7)->format('Y/m/d');
                array_push($whereData, [$key, '<=', $condition]);
            } elseif (is_array($condition)) {
                // JSON Columns(Use UpdateFlags)
                foreach ($condition as $keyJson => $valueJson) {
                    array_push($whereData, ["{$nameColumnUpdate}->$keyJson", '=', "{$valueJson}"]);
                }
                continue;
            } else {
                array_push($whereData, [$key, '=', $condition]);
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
            if (0 < strpos($value, 'UpperID->')) {
                $value = explode('->', $value)[0];
                $value = $value . ')';
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

            preg_match('/\((\w+)\.(.*)\)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);

            if (count($matches) > 2 && is_array($matches[2])) {
                $columnName = $matches[2][0];
                $arrayAliasColumns[] = DB::raw("\"$columnName\" as \"$key\"");
            } else {
                $isMache = preg_match('/\((.*)\)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
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
                if (isset($data['exp3'])) {
                    if (
                        in_array($data['exp1'], $allDestinationColumns)
                        and in_array($data['exp3'], $allDestinationColumns)
                    ) {
                        $joinConditions[$data['exp1']] = $data['exp3'];
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
            $cmd = 'Start';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $tempPath = $settingOutput['TempPath'];
            $fileName = $settingOutput['FileName'];

            $setting = $this->setting;
            $nameTable = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $nameProcess = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];            

            $groupDelimiter = null;
            if ($nameTable == 'User') {
                $groupDelimiter = $settingOutput['GroupDelimiter'];
            }

            $roleMaps = $settingManagement->getRoleMapInName($nameTable);
            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];

            ksort($formatConvention);
            mkDirectory($tempPath);

            if (is_file("{$tempPath}/$fileName")) {
                $fileName = $this->removeExt($fileName) . '_' . Carbon::now()->format('Ymd') . rand(100, 999) . '.csv';
            }
            $file = fopen(("{$tempPath}/{$fileName}"), 'wb');

            // Traceing
            $cmd = 'Count';
            $message = sprintf("Processing %s Object %d records", $nameTable, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $putCount = 0;

            $createCount = 0;
            $updateCount = 0;

            // create csv file
            foreach ($results as $data) {
                // Captude primary_key
                $cnvData = (array)$data;
                $uid = $cnvData['ID'];
                $data = array_only((array)$data, $selectColumns);

                $dataTmp = [];
                foreach ($formatConvention as $index => $column) {
                    $line = null;

                    if (
                        $column == 'UserToGroup' ||
                        $column == 'UserToOrganization' ||
                        $column == 'UserToRole' ||
                        $column == 'UserToPrivilege'
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
                        preg_match('/\(ELOQ;(.*)\)/', $column, $matches, PREG_OFFSET_CAPTURE, 0);
                        $line = $this->regExpManagement->eloquentItem($uid, $matches[1][0]);
                        array_push($dataTmp, $line);
                        continue;
                    } else {
                        if (strpos($column, 'TODAY()') !== false) {
                            $line = $this->regExpManagement->getEffectiveDate($column);
                        } else {
                            $isMache = preg_match('/\((\w+)\.(.*)\)/', $column, $matches, PREG_OFFSET_CAPTURE, 0);
                            if ($isMache) {
                                $column = $matches[2][0];
                                $line = $data[$column];
                            } else {
                                $isMache = preg_match('/\((.*)\)/', $column, $matches, PREG_OFFSET_CAPTURE, 0);
                                if ($isMache) {
                                    $line = $matches[1][0];
                                } else {
                                    $line = $column;
                                }
                            }
                        }
                    }

                    $twColumn = $nameTable . '.' . $column;
                    if (in_array($twColumn, $getEncryptedFields)) {
                        $line = $settingManagement->passwordDecrypt($line);
                    }

                    if (strpos($column, config('const.ROLE_FLAG')) !== false) {
                        $exp = explode('-', $column);
                        $line = $settingManagement->getRoleFlagInName($roleMaps, $exp[1], $line);
                    }
                    array_push($dataTmp, $line);
                }
                fputcsv($file, $dataTmp, ',');
                $putCount++;
            }

            $scimInfo = array(
                'provisoning' => sprintf("%s (%s)", 'CSV', $nameProcess),
                'table' => ucfirst(strtolower($nameTable)),
                'casesHandle' => count($results),
                'createCount' => $putCount,
                'updateCount' => 0,
                'deleteCount' => 0,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = 'Extract';
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
                'provisoning' => 'Extract',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($nameTable)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            // Traceing
            $faildCnt = count($results) - $putCount;
            $cmd = 'Faild';
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
                case 'Group':
                    $retValue = $retValue . $eloquent->displayName;
                    break;
                    // case 'Role':
                    //     $this->exportRoleFromKeyspider($array);
                    //     break;
                    // case 'Group':
                    //     $this->exportGroupFromKeyspider($array);
                    //     break;
                    // case 'Organization':
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
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
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
            if (strpos($value, 'TODAY()') !== false) {
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
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning start --> Azure Active Directory';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $getEncryptedFields = $settingManagement->getEncryptedFields();
            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, 'externalID', 'DeleteFlag']);

            if ($table == 'User') {
                $allRoleFlags = $settingManagement->getRoleFlags();
                $selectColumnsAndID = array_merge($selectColumnsAndID, $allRoleFlags);

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
            $cmd = 'Count --> Azure Active Directory';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $userGraph = new UserGraphAPI();

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);
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

                            $userGraph->deleteResource($item->externalID, $table);

                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                            $updateQuery->update(['externalID' => null]);

                            if ($table == 'Group') {
                                $updateQuery = DB::table('UserToGroup');
                                $updateQuery->where('Group_ID', $item->{"$primaryKey"});
                                $updateQuery->delete();
                            }
                            $deleteCount++;
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
                            DB::commit();
                            continue;
                        }
                        $userOnAD = $userGraph->createResource((array)$item, $table);
                        if ($userOnAD == null) {
                            DB::commit();
                            continue;
                        }
                        // TODO: create user on AD, update UpdateFlags and externalID.
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                        $updateQuery->update(['externalID' => $userOnAD->getID()]);
                        $createCount++;
                        var_dump($userOnAD);
                    }
                    DB::commit();
                }
            }

            $scimInfo = array(
                'provisoning' => 'Azure Active Directory',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> Azure Active Directory';
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
                'provisoning' => 'Azure Active Directory',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> Azure Active Directory';
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

    public function processExtractToSF()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            // Set table name to $scimInfo
            $scimInfo['table'] = $table;

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> SalesForce';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, 'externalSFID', 'DeleteFlag']);
            if ($table == 'User') {
                // $selectColumnsAndID = array_merge($selectColumnsAndID, ['RoleFlag-0', 'RoleFlag-1', 'RoleFlag-2', 'RoleFlag-3', 'RoleFlag-4']);
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
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = 'Count --> SalseForce';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToSalesforce();

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    // check resource is existed on AD or not
                    //TODO: need to change because userPrincipalName is not existed in group.
                    if (($item["externalSFID"]) && ($scimLib->getResourceDetails($item["externalSFID"], $table))) {
                        if ($item["DeleteFlag"] == 1) {
                            //Delete resource
                            $item['IsActive'] = 1;
                            $userUpdated = $scimLib->deleteResource($table, (array)$item);
                            $deleteCount++;
                        } else {
                            $userUpdated = $scimLib->updateResource($table, (array)$item);
                            if ($userUpdated == null) {
                                continue;
                            }
                            $scimLib->passwordResource($table, (array)$item);
                            $updateCount++;
                        }
                        $keyString = $item["$primaryKey"];
                        $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = 0);
                    } //Not found resource, create it!
                    else {
                        if ($item["DeleteFlag"] == 1) {
                            continue;
                        }

                        $userOnSF = $scimLib->createResource($table, (array)$item);

                        if ($userOnSF == null) {
                            echo "\Not found response of creating: \n";
                            var_dump($item);
                            continue;
                        } else {
                            $item['externalSFID'] = $userOnSF;
                        }

                        $scimLib->passwordResource($table, (array)$item);
                        // TODO: create user on AD, update UpdateFlags and externalID.
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalSFID' => $userOnSF]);
                        DB::commit();
                        $createCount++;
                        var_dump($userOnSF);
                    }
                }
            }

            $scimInfo = array(
                'provisoning' => 'SalesForce',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> SalesForce';
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
                'provisoning' => 'SalesForce',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> SalesForce';
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

    public function processExtractToZOOM()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> ZOOM';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $selectColumnsAndID = array_merge($aliasColumns, ['externalZOOMID', 'DeleteFlag']);

            if ($table == 'User') {
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
            //                ->where("{$nameColumnUpdate}->{$extractedId}", 1);
            $extractedSql = $query->toSql();
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = 'Count --> ZOOM';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToZoom($setting);

                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalZOOMID'])) {
                            $scimLib->deleteResource($table, $item);
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                            $updateQuery->update(['externalZOOMID' => null]);
                            DB::commit();
                            $deleteCount++;
                            continue;
                        } else {
                            // $item['status'] = 'inactive';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalZOOMID'])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        $ext_id = $item['externalZOOMID'];
                        $updateCount++;
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        $createCount++;
                    }

                    if ($table == 'User') {
                        $scimLib->addGroupMemebers($table, $item, $ext_id);
                        $scimLib->addRoleMemebers($table, $item, $ext_id);
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalZOOMID' => $ext_id]);
                        DB::commit();
                    }
                }
            }

            $scimInfo = array(
                'provisoning' => 'ZOOM',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> ZOOM';
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
                'provisoning' => 'ZOOM',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> ZOOM';
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

    public function processExtractToSlack()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> Slack';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $selectColumnsAndID = array_merge($aliasColumns, ['externalSlackID', 'DeleteFlag']);

            if ($table == 'User') {
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
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = 'Count --> Slack';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToSlack($setting);

                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalSlackID'])) {
                            $ext_id = $scimLib->deleteResource($table, $item);
                            if ($ext_id !== null) {
                                DB::beginTransaction();
                                $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                                $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                                $updateQuery->where($primaryKey, $item["$primaryKey"]);
                                $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                                $updateQuery->update(['externalSlackID' => null]);
                                DB::commit();
                                $deleteCount++;
                            }
                            continue;
                        } else {
                            $item['locked'] = '1';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalSlackID'])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        $updateCount++;
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        $createCount++;
                    }
                    $scimLib->updateGroupMemebers($table, $item, $ext_id);

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalSlackID' => $ext_id]);
                        DB::commit();
                    }
                }
            }

            $scimInfo = array(
                'provisoning' => 'Slack',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> Slack';
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
                'provisoning' => 'Slack',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> Slack';
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

    public function processExtractToTL()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            // Set table name to $scimInfo
            $scimInfo['table'] = $table;

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> TrustLogin';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];

            //Append 'ID' to selected Columns to query and process later
            if ($table == 'User') {
                $selectColumnsAndID = array_merge($aliasColumns, ['externalTLID', 'DeleteFlag']);
            } else {
                $selectColumnsAndID = $aliasColumns;
            }

            if ($table == 'User') {
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
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = 'Count --> TrustLogin';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToTrustLogin($setting);

                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalTLID'])) {
                            $ext_id = $scimLib->deleteResource($table, $item);
                            if ($ext_id !== null) {
                                DB::beginTransaction();
                                $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                                $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                                $updateQuery->where($primaryKey, $item["$primaryKey"]);
                                $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                                $updateQuery->update(['externalTLID' => null]);
                                $deleteCount++;
                                DB::commit();
                            }
                            continue;
                        } else {
                            $item['locked'] = '1';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalTLID'])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        if ($ext_id !== null) {
                            $updateCount++;
                        }
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        if ($ext_id !== null) {
                            $createCount++;
                        }
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalTLID' => $ext_id]);
                        DB::commit();
                    }
                }
            }
            $scimInfo = array(
                'provisoning' => 'TrustLogin',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> TrustLogin';
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
                'provisoning' => 'TrustLogin',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> TrustLogin';
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

    public function processExtractToBOX()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> BOX';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $selectColumnsAndID = array_merge($aliasColumns, ['externalBOXID', 'DeleteFlag']);

            if ($table == 'User') {
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
            //                ->where("{$nameColumnUpdate}->{$extractedId}", 1);
            $extractedSql = $query->toSql();
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            // Traceing
            $cmd = 'Count --> BOX';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToBox($setting);

                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalBOXID'])) {
                            $scimLib->deleteResource($table, $item);
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                            $updateQuery->update(['externalBOXID' => null]);
                            $deleteCount++;
                            DB::commit();
                            continue;
                        } else {
                            $item['status'] = 'inactive';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalBOXID'])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        if ($table == "User" && !empty($ext_id)) {
                            $scimLib->addMemberToGroups($item, $ext_id);
                        }
                        $updateCount++;
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        if ($table == "User" && !empty($ext_id)) {
                            $scimLib->addMemberToGroups($item, $ext_id);
                        }
                        $createCount++;
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalBOXID' => $ext_id]);
                        DB::commit();
                    }
                }
            }

            $scimInfo = array(
                'provisoning' => 'BOX',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> BOX';
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
                'provisoning' => 'BOX',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> BOX';
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

    public function processExtractToGW()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }
            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> Google Workspace';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $selectColumnsAndID = array_merge($aliasColumns, ['externalGWID', 'DeleteFlag', 'ID']);
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
            $cmd = 'Count --> Google Workspace';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToGoogleWorkspace($setting);

                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    foreach ($formatConvention as $key => $value) {
                        $item[$key] = $this->regExpManagement->getUpperValue($item, $key, $value, 'default');
                    }

                    if ($item['DeleteFlag'] == '1') {
                        $deleted = '1';
                        if (!empty($item['externalGWID'])) {
                            $deleted = $scimLib->deleteResource($table, $item);
                        }
                        if ($deleted != null) {
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                            $updateQuery->update(['externalGWID' => null]);
                            $deleteCount++;
                            DB::commit();
                        }
                        continue;
                    }

                    $ext_id = null;
                    if (!empty($item['externalGWID'])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        $updateCount++;
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        $createCount++;
                    }

                    if ($ext_id !== null) {
                        $scimLib->userGroup($table, $item, $ext_id);
                        $scimLib->userRole($table, $item['ID'], $ext_id);
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalGWID' => $ext_id]);
                        DB::commit();
                    }
                }
            }

            $scimInfo = array(
                'provisoning' => 'Google Workspace',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> Google Workspace';
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
                'provisoning' => 'Google Workspace',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> Google Workspace';
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

    public function processExtractToOL()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            // Configuration file validation
            if (!$settingManagement->isExtractSettingsFileValid()) {
                return;
            }

            // Traceing
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $cmd = $table . ' Provisioning Start --> OneLogin';
            $message = "";
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $selectColumnsAndID = array_merge($aliasColumns, ['externalOLID', 'DeleteFlag', 'ID']);
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
            $cmd = 'Count --> OneLogin';
            $message = sprintf("Processing %s Object %d records", $table, count($results));
            $settingManagement->traceProcessInfo($dbt, $cmd, $message);

            $createCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            if ($results) {
                $scimLib = new SCIMToOneLogin($setting);

                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    $item = $this->overlayItem($formatConvention, $item);

                    foreach ($formatConvention as $key => $value) {
                        $item[$key] = $this->regExpManagement->getUpperValue($item, $key, $value, 'default');
                    }

                    if ($item['DeleteFlag'] == '1') {
                        $deleted = '1';
                        if (!empty($item['externalOLID'])) {
                            $deleted = $scimLib->deleteResource($table, $item);
                        }
                        if ($deleted != null) {
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                            $updateQuery->update(['externalOLID' => null]);
                            $deleteCount++;
                            DB::commit();
                        }
                        continue;
                    }

                    $ext_id = null;
                    if (!empty($item['externalOLID'])) {
                        // Update
                        $ext_id = $scimLib->updateResource($table, $item);
                        $updateCount++;
                    } else {
                        // Create
                        $ext_id = $scimLib->createResource($table, $item);
                        $createCount++;
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['UpdateDate' => Carbon::now()->format('Y/m/d H:i:s')]);
                        $updateQuery->update(['externalOLID' => $ext_id]);
                        DB::commit();
                    }
                }
            }

            $scimInfo = array(
                'provisoning' => 'OneLogin',
                'table' => ucfirst(strtolower($table)),
                'casesHandle' => count($results),
                'createCount' => $createCount,
                'updateCount' => $updateCount,
                'deleteCount' => $deleteCount,
            );
            $settingManagement->summaryLogger($scimInfo);

            // Traceing
            $cmd = $table . ' Provisioning done --> OneLogin';
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
                'provisoning' => 'OneLogin',
                'scimMethod' => 'unknown',
                'table' => ucfirst(strtolower($table)),
                'itemId' => 'System',
                'itemName' => 'Exception',
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            $faildCnt = count($results) - ($updateCount + $createCount + $deleteCount);
            $cmd = $table . ' Provisioning Faild --> OneLogin';
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
            }

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
}
