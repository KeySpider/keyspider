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

use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\UserGraphAPI;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Microsoft\Graph\Model\User;

class DBExtractor
{
    /**
     * define const
     */
    const EXTRACTION_CONDITION = 'Extraction Condition';
    const EXTRACTION_CONFIGURATION = "Extraction Process Basic Configuration";
    const OUTPUT_PROCESS_CONVERSION = "Output Process Conversion";
    const EXTRACTION_PROCESS_FORMAT_CONVERSION = "Extraction Process Format Conversion";

    protected $setting;

    /**
     * DBExtractor constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
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
//            $table = $this->switchTable($extractTable);

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
            Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                //Set updateFlags for key ('ID')
                //{"processID":0}
                foreach ($results as $key => $item) {
                    // update flag
                    $keyString = $item->{"$primaryKey"};
                    $settingManagement->setUpdateFlags($extractedId, $keyString, $table, $value = 0);
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
            preg_match('/(\w+)\.(\w+)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
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

            mkDirectory($tempPath);

            if (is_file("{$tempPath}/$fileName")) {
                $fileName = $this->removeExt($fileName) . '_' . Carbon::now()->format('Ymd') . rand(100, 999) . '.csv';
            }
            $file = fopen(("{$tempPath}/{$fileName}"), 'wb');

            // create csv file
            foreach ($results as $data) {
                $data = array_only((array)$data, $selectColumns);
                $dataTmp = [];
                foreach ($data as $column => $line) {
                    if (in_array($column, $getEncryptedFields)) {
                        $line = $settingManagement->passwordDecrypt($line);
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


//            $results = null;
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
            $selectColumnsAndID = array_merge($aliasColumns, [$primaryKey, 'externalID', 'DeleteFlag']);

            $joins = ($this->getJoinCondition($formatConvention, $settingManagement));
            foreach ($joins as $src => $des) {
                $selectColumns[] = $des;
            }

            $query = DB::table($table);
            $query = $query->select($selectColumnsAndID)
                ->where($whereData);
//                ->where("{$nameColumnUpdate}->{$extractedId}", 1);
            $extractedSql = $query->toSql();
            Log::info($extractedSql);
            $results = $query->get()->toArray();

            if ($results) {
                $userGraph = new UserGraphAPI();
                //Set updateFlags for key ('ID')
                //{"processID":0}

                foreach ($results as $key => $item) {
                    DB::beginTransaction();
                    // check resource is existed on AD or not
                    if (($item->externalID) && ($userGraph->getResourceDetails($item->externalID, $table, $item['userPrincipalName']))) {
                        $userUpdated = $userGraph->updateUser((array)$item);
                        if ($userUpdated == null) continue;
                        $settingManagement->setUpdateFlags($extractedId, $item->{"$primaryKey"}, $table, $value = 0);
                    }
                    //Not found resource, create it!
                    else {
                        $userOnAD = $userGraph->createResource((array)$item, $table);
                        if ($userOnAD == null) continue;
//                    TODO: create user on AD, update UpdateFlags and externalID.
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
}
