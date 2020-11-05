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
use App\Ldaplibs\SCIM\SCIMToSalesforce;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\UserGraphAPI;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Microsoft\Graph\Model\User;

use MacsiDigital\Zoom\Exceptions\FileTooLargeException;
use MacsiDigital\Zoom\Exceptions\ValidationException;
use MacsiDigital\Zoom\Support\Model;
use MacsiDigital\Zoom\Facades\Zoom;

class DBExtractor
{
    /**
     * define const
     */
    const EXTRACTION_CONDITION = 'Extraction Condition';
    const EXTRACTION_CONFIGURATION = "Extraction Process Basic Configuration";
    const OUTPUT_PROCESS_CONVERSION = "Output Process Conversion";
    const EXTRACTION_PROCESS_FORMAT_CONVERSION = "Extraction Process Format Conversion";
    const SCIM_CONFIG = 'SCIM Authentication Configuration';

    protected $setting;
    protected $regExpManagement;

    /**
     * DBExtractor constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->regExpManagement = new RegExpsManager();

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

        foreach ($settingConvention as $key => $value) {
            $n = strpos($value, $nameTable);
            // preg_match('/(\w+)\.(\w+)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
            preg_match('/\((\w+)\.(.*)\)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);

            if (strpos($value,'ELOQ') !== false) {
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
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelectForCSV($table, $formatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later
//            if (!in_array($primaryKey, $selectColumns))
//                $selectColumns[] = $primaryKey;
            $selectColumnsAndID = array_merge($selectColumns, [$primaryKey]);
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

            if ($results) {
                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    // update flag
                    $keyString = $item->{"$primaryKey"};
                    // $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = 0);
                    $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = '0');
                }
                //Start to extract
                $pathOutput = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
                $settingOutput = $this->getContentOutputCSV($pathOutput);
                echo("\e[0;31;46m- [$extractedId] Extracting table <$table> \e[0m to output conversion [$pathOutput]\n");
                $this->processOutputDataExtract($settingOutput, $results, $aliasColumns, $table);
            }

            DB::commit();
        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
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
                if (strpos((string)$condition, 'TODAY()') !== false) {
                    $condition = $this->regExpManagement->getEffectiveDate($condition);
                    array_push($whereData, [$key, '<=', $condition]);
                    continue;
                }
            }

            // condition
            if ($condition === 'TODAY() + 7') {
                $condition = Carbon::now()->addDay(7)->format('Y/m/d');
                array_push($whereData, [$key, '<=', $condition]);
            } elseif (is_array($condition)) {
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
            $n = strpos($value, $nameTable);
            // preg_match('/(\w+)\.(\w+)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
            preg_match('/\((\w+)\.(.*)\)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
         
            if (count($matches) > 2 && is_array($matches[2])) {
                $columnName = $matches[2][0];
                $arrayAliasColumns[] = DB::raw("\"$columnName\" as \"$key\"");;
            } else {
                $defaultColumn = "default_$index";
                $index++;
                $columnName = DB::raw("'$value' as \"$defaultColumn\"");
                $arrayAliasColumns[] = $defaultColumn;
            }
            $arraySelectColumns[] = $columnName;
        }

//        $selectColumns = $this->convertArrayColumnsIntoString($arraySelectColumns);

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
                    if (in_array($data['exp1'], $allDestinationColumns)
                        and in_array($data['exp3'], $allDestinationColumns)) {
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

            $tempPath = $settingOutput['TempPath'];
            $fileName = $settingOutput['FileName'];

            $setting = $this->setting;
            $nameTable = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];            

            $roleMaps = $settingManagement->getRoleMapInName($nameTable);
            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];

            mkDirectory($tempPath);

            if (is_file("{$tempPath}/$fileName")) {
                $fileName = $this->removeExt($fileName) . '_' . Carbon::now()->format('Ymd') . rand(100, 999) . '.csv';
            }
            $file = fopen(("{$tempPath}/{$fileName}"), 'wb');
            // create csv file
            foreach ($results as $data) {
                // Captude primary_key
                $cnvData = (array)$data;
                $uid = $cnvData['ID'];

                $data = array_only((array)$data, $selectColumns);

                $dataTmp = [];
                // foreach ($selectColumns as $index => $column) {
                foreach ($formatConvention as $index => $column) {
                    $line = null;
                    if (strpos($column, 'ELOQ;') !== false) {
                        // Get Eloquent string
                        preg_match('/\(ELOQ;(.*)\)/', $column, $matches, PREG_OFFSET_CAPTURE, 0);
                        $line = $this->regExpManagement->eloquentItem($uid, $matches[1][0]);
                        array_push($dataTmp, $line);
                        continue;
                    } else {
                        preg_match('/\((\w+)\.(.*)\)/', $column, $matches, PREG_OFFSET_CAPTURE, 0);
                        $column = $matches[2][0];
                        $line = $data[$column];
                    }

                    // $line = $data[$column];
                    $twColumn = $nameTable . '.' . $column;
                    // if (in_array($column, $getEncryptedFields)) {
                    if (in_array($twColumn, $getEncryptedFields)) {
                        $line = $settingManagement->passwordDecrypt($line);
                    }
                    if ( strpos($column, config('const.ROLE_FLAG')) !== false ) {
                        $exp = explode('-', $column);
                        $line = $settingManagement->getRoleFlagInName($roleMaps, $exp[1], $line);
                    }
                    array_push($dataTmp, $line);
                }
                fputcsv($file, $dataTmp, ',');
            }
            Log::info("Extracted to file: $fileName into $tempPath");
            echo("  Extracted to file: \e[0;31;46m[$tempPath/$fileName]\e[0m\n");

            fclose($file);
        } catch (Exception $e) {
            echo("Extract to failed: \e[0;31;46m[$e]\e[0m\n");
            Log::error($e);
        }
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

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);
            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, 'externalID', 'DeleteFlag']);

            if ($table == 'User') {
                // $selectColumnsAndID = array_merge($selectColumnsAndID, ['RoleFlag-0', 'RoleFlag-1', 'RoleFlag-2', 'RoleFlag-3', 'RoleFlag-4']);
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
//                ->where("{$nameColumnUpdate}->{$extractedId}", 1);
            $extractedSql = $query->toSql();
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                $userGraph = new UserGraphAPI();
                //Set updateFlags for key ('ID')
                //{"processID":0}

                foreach ($results as $key => $item) {

                    $item = (array)$item;

                    foreach ($item as $kv => $iv) {
                        $twColumn = "Azure.$kv";
                        if (in_array($twColumn, $getEncryptedFields)) {
                            $item[$kv] = $settingManagement->passwordDecrypt($iv);
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
                            Log::info("Delete user [$uPN] on AzureAD");
                            $userGraph->deleteResource($item->externalID, $table);
                        } else {
                            $userUpdated = $userGraph->updateResource((array)$item, $table);
                            if ($userUpdated == null) {
                                DB::commit();
                                continue;
                            }
                        }
                        $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                    } //Not found resource, create it!
                    else {
                        if ($item->DeleteFlag == 1) {
                            Log::info("User [$uPN] has DeleteFlag=1 and not existed on AzureAD, do nothing!");
                            DB::commit();
                            continue;
                        }
                        $userOnAD = $userGraph->createResource((array)$item, $table);
                        if ($userOnAD == null) continue;
                        // TODO: create user on AD, update UpdateFlags and externalID.
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item->{"$primaryKey"});
                        $updateQuery->update(['externalID' => $userOnAD->getID()]);
                        var_dump($userOnAD);
                    }
                    DB::commit();
                }
            }


        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
        }
    }

    public function processExtractToSF()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

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
//                ->where("{$nameColumnUpdate}->{$extractedId}", 1);
            $extractedSql = $query->toSql();
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                // $scimLib = new UserGraphAPI();
                $scimLib = new SCIMToSalesforce();

                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    $item = (array)$item;
                    foreach ($item as $kv => $iv) {
                        $twColumn = "SalesForce.$kv";
                        if (in_array($twColumn, $getEncryptedFields)) {
                            $item[$kv] = $settingManagement->passwordDecrypt($iv);
                        }
                    }

                    DB::beginTransaction();
                    // check resource is existed on AD or not
                    //TODO: need to change because userPrincipalName is not existed in group.
                    if (($item["externalSFID"]) && ($scimLib->getResourceDetails($item["externalSFID"], $table))) {
                        if ($item["DeleteFlag"] == 1) {
                            //Delete resource

                            $item['IsActive'] = 1;
                            $userUpdated = $scimLib->updateResource($table, $item);
                        } else {
                            $userUpdated = $scimLib->updateResource($table, (array)$item);
                            if ($userUpdated == null) {
                                DB::commit();
                                continue;
                            }
                        }
                        $keyString = $item["$primaryKey"];
                        $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = 0);
                    } //Not found resource, create it!
                    else {
                        if ($item["DeleteFlag"] == 1) {
                            DB::commit();
                            continue;
                        }
                        $userOnSF = $scimLib->createResource($table, (array)$item);
                        if ($userOnSF == null) {
                            echo "\Not found response of creating: \n";
                            var_dump($item);
                            continue;
                        }
                        // TODO: create user on AD, update UpdateFlags and externalID.
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['externalSFID' => $userOnSF]);
                        var_dump($userOnSF);
                    }
                    DB::commit();
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
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

            if ($results) {

                foreach ($results as $key => $item) {

                    $item = (array)$item;
                    $item = $this->decryptZoomItem($item);

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalZOOMID'])) {
                            $user = Zoom::user()->find($item['email']);
                            Log::info('Zoom Delete -> ' . $item['last_name'] . ' ' . $item['first_name']);
                            $user->delete();
                            // $this->sendDeleteUser($item['externalZOOMID']);
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['externalZOOMID' => null]);
                            DB::commit();
                            continue;
                        } else {
                            // $item['status'] = 'inactive';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalZOOMID'])) {
                        // Update
                        Log::info('Zoom Update -> ' . $item['last_name'] . ' ' . $item['first_name']);
                        try {
                        $user = Zoom::user()->find($item['email']);
                        $ext_id = $user->id;
                        $result = $user->update([
                            'first_name' => $item['first_name'],
                                'last_name' => $item['last_name'],
                                // 'email' => $item['email'],
                                'phone_country' => 'JP',
                                'phone_number' => $item['phone_number'],
                                'timezone' => 'Asia/Tokyo',
                                'job_title' => $item['job_title'],
                                'type' => 1,
                                'verified' => 0,
                                'language' => 'jp-JP',
                                'status' => 'active',
                            ]);

                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(),'Only paid') !== false) {
                                // これは正常系なので無視する
                            } else {
                                Log::error($exception);
                            }
                        }
                    } else {
                        // Create
                        Log::info('Zoom Create -> ' . $item['last_name'] . ' ' . $item['first_name']);
                        $user = Zoom::user()->create([
                            'first_name' => $item['first_name'],
                            'last_name' => $item['last_name'],
                            'email' => $item['email'],
                            'timezone' => 'Asia/Tokyo',
                            'verified' => 0,
                            'language' => 'jp-JP',
                            'status' => 'active'
                        ]);
                        $ext_id = $user->id;
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['externalZOOMID' => $ext_id]);
                        DB::commit();
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
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
//                ->where("{$nameColumnUpdate}->{$extractedId}", 1);
            $extractedSql = $query->toSql();
            // Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {

                foreach ($results as $key => $item) {

                    $item = (array)$item;

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalSlackID'])) {
                            $ext_id = $this->sendDeleteUser_Slack($item['externalSlackID']);
                            if (!empty($ext_id)) {
                                DB::beginTransaction();
                                $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                                $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                                $updateQuery->where($primaryKey, $item["$primaryKey"]);
                                $updateQuery->update(['externalSlackID' => null]);
                                DB::commit();
                            }
                            continue;
                        } else {
                            $item['locked'] = '1';
                        }
                    }

                    $ext_id = null;
                    if ($table == 'User') {
                        if (!empty($item['externalSlackID'])) {
                            // Update
                            $tmpl = $this->replaceCreateUser_Slack($item);
                            $ext_id = $this->sendReplaceUser_Slack($item['externalSlackID'], $tmpl);
                        } else {
                            $tmpl = $this->replaceCreateUser_Slack($item);
                            $ext_id = $this->sendCreateUser_Slack($tmpl);
                        }
                    } else {
                        $tmpl = $this->replaceCreateOrganization_Slack($item);
                        if (!empty($item['externalSlackID'])) {
                            // Update
                            $ext_id = $this->sendReplaceOrganization_Slack($item['externalSlackID'], $tmpl);
                        } else {
                            $ext_id = $this->sendCreateOrganization_Slack($tmpl);
                        }
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['externalSlackID' => $ext_id]);
                        DB::commit();
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
        }
    }

    private function replaceCreateOrganization_Slack($item)
    {
        $tmp = Config::get('scim-slack.createGroup');
        $tmp = str_replace("(Organization.DisplayName)", $item['displayName'], $tmp);
        $tmp = str_replace("(Organization.externalID)", $item['externalSlackID'], $tmp);
        return $tmp;
    }

    private function replaceCreateUser_Slack($item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmp = Config::get('scim-slack.createUser');
        $isActive = 'true';

        $macnt = explode('@', $item['mail']);
        if (strlen($macnt[0]) > 20) {
            $item['userName'] = substr($macnt[0], -21);
        } else {
            $item['userName'] = $macnt[0];
        }

        foreach ($item as $key => $value) {
            if ($key === 'locked') {
                if ( $value == '1') $isActive = 'false';
                $tmp = str_replace("(User.DeleteFlag)", $isActive, $tmp);
                continue;
            }

            $twColumn = "Slack.$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(User.$key)", $value, $tmp);
        }

        return $tmp;
    }

    public function processExtractToTL()
    {
        try {
            $setting = $this->setting;
            $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractedId = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($table);
            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $primaryKey = $settingManagement->getTableKey();
            $allSelectedColumns = $this->getColumnsSelect($table, $formatConvention);

            $selectColumns = $allSelectedColumns[0];
            $aliasColumns = $allSelectedColumns[1];
            //Append 'ID' to selected Columns to query and process later

            $selectColumnsAndID = array_merge($aliasColumns, ['externalTLID', 'DeleteFlag']);
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

            if ($results) {

                foreach ($results as $key => $item) {

                    $item = (array)$item;

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalTLID'])) {
                            $this->sendDeleteUser($item['externalTLID']);
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['externalTLID' => null]);
                            DB::commit();
                            continue;
                        } else {
                            $item['locked'] = '1';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalTLID'])) {
                        // Update
                        $tmpl = $this->replaceCreateUser_TL($item);
                        $ext_id = $this->sendReplaceUser($item['externalTLID'], $tmpl);
                    } else {
                        $tmpl = $this->replaceCreateUser_TL($item);
                        $ext_id = $this->sendCreateUser($tmpl);
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['externalTLID' => $ext_id]);
                        DB::commit();
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
        }
    }

    private function decryptZoomItem($item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        foreach ($item as $key => $value) {
            $twColumn = "ZOOM.$key";

            if (in_array($twColumn, $getEncryptedFields)) {
                $item[$key] = $settingManagement->passwordDecrypt($value);
            }
        }
        return $item;
    }

    private function replaceCreateUser_TL($item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmp = Config::get('scim-trustlogin.createUser');
        $isActive = 'true';

        foreach ($item as $key => $value) {
            if ($key === 'locked') {
                if ( $value == '1') $isActive = 'false';
                $tmp = str_replace("(User.DeleteFlag)", $isActive, $tmp);
                continue;
            }

            $twColumn = "User.$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(User.$key)", $value, $tmp);
        }
        return $tmp;
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

            if ($results) {

                foreach ($results as $key => $item) {

                    $item = (array)$item;

                    if ($item['DeleteFlag'] == '1') {
                        if (!empty($item['externalBOXID'])) {
                            $this->sendDeleteUser($item['externalBOXID']);
                            DB::beginTransaction();
                            $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                            $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                            $updateQuery->where($primaryKey, $item["$primaryKey"]);
                            $updateQuery->update(['externalBOXID' => null]);
                            DB::commit();
                            continue;
                        } else {
                            $item['status'] = 'inactive';
                        }
                    }

                    $ext_id = null;
                    if (!empty($item['externalBOXID'])) {
                        // Update
                        $tmpl = $this->replaceCreateUser_BOX($table, $item);
                        $ext_id = $this->sendReplaceUser($item['externalBOXID'], $tmpl);
                    } else {
                        // Create
                        $tmpl = $this->replaceCreateUser_BOX($table, $item);
                        $ext_id = $this->sendCreateUser($tmpl);
                    }

                    if ($ext_id !== null) {
                        DB::beginTransaction();
                        $userOnDB = $settingManagement->setUpdateFlags($extractedId, $item["$primaryKey"], $table, $value = 0);
                        $updateQuery = DB::table($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']);
                        $updateQuery->where($primaryKey, $item["$primaryKey"]);
                        $updateQuery->update(['externalBOXID' => $ext_id]);
                        DB::commit();
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            echo("\e[0;31;47m [$extractedId] $exception \e[0m \n");
        }
    }

    private function replaceCreateUser_BOX($table, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmp = Config::get('scim-box.createUser');
        if ($table === 'Role') {
            $tmp = Config::get('scim-box.createGroup');
        }

        $isActive = 'active';
        foreach ($item as $key => $value) {
            if ($key === 'state') {
                $address = sprintf("%s, %s, %s", 
                    $item['state'], $item['city'], $item['streetAddress']);
                $tmp = str_replace("(User.joinAddress)", $address, $tmp);
                continue;

            }

            if ($key === 'locked') {
                if ( $value == '1') $isActive = 'inactive';
                $tmp = str_replace("(User.DeleteFlag)", $isActive, $tmp);
                continue;
            }

            $twColumn = $table . ".$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(" . $table . ".$key)", $value, $tmp);
        }
        return $tmp;
    }

    private function sendCreateUser($data) {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);
            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Create faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function sendCreateUser_Slack($data) {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Create faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
    
            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Create faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function sendCreateOrganization_Slack($data) {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Create faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
    
            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Create faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function sendDeleteUser($externalID){
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "accept: */*"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
    }

    private function sendDeleteUser_slack($externalID){
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "accept: */*"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        $responce = json_decode($tuData, true);
        $info = curl_getinfo($tuCurl);

        if (empty($responce)) {
            if(!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
            } else {
                Log::debug('Curl error: ' . curl_error($tuCurl));
            }
            curl_close($tuCurl);
            return $responce['id'];
        }
        
        if (array_key_exists("Errors", $responce)) {
            $curl_status = $responce['Errors']['description'];
            Log::error('Delete faild ststus = ' . $curl_status);
            Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
            curl_close($tuCurl);
            return null;
        }
    }

    private function sendReplaceUser($externalID, $data) {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);
            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Replace faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function sendReplaceUser_Slack($externalID, $data) {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Replace faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }

            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Replace faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function sendReplaceOrganization_Slack($externalID, $data) {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Replace faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }

            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Replace faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

}
