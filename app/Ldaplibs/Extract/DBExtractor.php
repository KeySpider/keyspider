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
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DBExtractor
{
    /**
     * define const
     */
    const EXTRACTION_CONDITION                 = 'Extraction Condition';
    const EXTRACTION_CONFIGURATION             = "Extraction Process Basic Configuration";
    const OUTPUT_PROCESS_CONVERSION            = "Output Process Conversion";
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

            $setting = $this->setting;
            $extractTable = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $table = $this->switchTable($extractTable);

            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $whereData = $this->extractCondition($extractCondition);

            $checkColumns = [];
            foreach ($whereData as $item) {
                array_push($checkColumns, substr($item[0], -3));
            }

            $formatConvention = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];
            $selectColumns = $this->getColumnsSelect($table, $formatConvention);

            $settingManagement = new SettingsManager();
            $nameColumnUpdate = $settingManagement->getNameColumnUpdated($table);
            $keyTable = $settingManagement->getTableKey($table);

            $results = null;

            if ($table === "AAA") {
                // join
                if (Schema::hasColumn($table, $checkColumns[0], $checkColumns[1])) {
                    $results = DB::table($table)
                        ->select($selectColumns)
                        ->where($whereData)
                        ->where("{$nameColumnUpdate}->csv->isUpdated", 1)
                        ->leftJoin('BBB', 'AAA.004', 'BBB.001')
                        ->leftJoin('CCC', 'AAA.005', 'CCC.001')
                        ->get();

                    foreach ($results as $key => $item) {
                        DB::table($table)
                            ->where("{$keyTable}", $item->{"{$table}.001"})
                            ->update(["{$nameColumnUpdate}->csv->isUpdated" => 0]);
                    }
                }
            } else {
                if (Schema::hasColumn($table, $checkColumns[0], $checkColumns[1])) {
                    $results = DB::table($table)
                        ->select($selectColumns)
                        ->where($whereData)
                        ->where("{$nameColumnUpdate}->csv->isUpdated", 1)
                        ->get();

                    foreach ($results as $key => $item) {
                        DB::table($table)
                            ->where("{$keyTable}", $item->{'001'})
                            ->update(["{$nameColumnUpdate}->csv->isUpdated" => 0]);
                    }
                }
            }

            if ($results) {
                if (!empty($results->toArray())) {
                    $pathOutput = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
                    $settingOutput = $this->getContentOutputCSV($pathOutput);
                    $this->processOutputDataExtract($settingOutput, $results, $formatConvention, $table);
                }
            }

            DB::commit();
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * @param $extractCondition
     * @return mixed
     */
    public function extractCondition($extractCondition)
    {
        $whereData = [];
        foreach ($extractCondition as $key => &$condition) {
            // condition
            if ($condition === 'TODAY() + 7') {
                $condition = Carbon::now()->addDay(7)->format('Y/m/d');
                array_push($whereData, [$key, '<=', $condition]);
            } else {
                array_push($whereData, [$key,'=', $condition]);
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
        $selectColumns = [
            "{$nameTable}.*"
        ];

        $arraySelectColumns = [
            "{$nameTable}.001"
        ];

        if ($nameTable === "AAA") {
            $pattern = "/\(\s*(?<exp1>[\w\.]+)\s*((,\s*(?<exp2>[^\)]+))?|\s*\->\s*(?<exp3>[\w\.]+))\s*\)/";

            foreach ($settingConvention as $key => $value) {
                $isFormat = preg_match($pattern, $value, $data);
                if ($isFormat) {
                    if (!in_array($data['exp1'], $arraySelectColumns)) {
                        array_push($arraySelectColumns, $data['exp1']);
                    }

                    if (isset($data['exp3'])) {
                        if (!in_array($data['exp3'], $arraySelectColumns)) {
                            array_push($arraySelectColumns, $data['exp3']);
                        }
                    }
                }
            }

            $selectColumns = $this->convertArrayColumnsIntoString($arraySelectColumns);
        }

        return $selectColumns;
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
    public function processOutputDataExtract($settingOutput, $results, $formatConvention, $table)
    {
        try {
            $pattern = "/\(\s*(?<exp1>[\w\.]+)\s*((,\s*(?<exp2>[^\)]+))?|\s*\->\s*(?<exp3>[\w\.]+))\s*\)/";

            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $tempPath = $settingOutput['TempPath'];
            $fileName = $settingOutput['FileName'];

            mkDirectory($tempPath);

            if (is_file("{$tempPath}/$fileName")) {
                $fileName = $this->removeExt($fileName) . '_' . Carbon::now()->format('Ymd') . rand(100, 999) . '.csv';
            }
            Log::info("Export to file: $fileName into $tempPath");
            $file = fopen(("{$tempPath}/{$fileName}"), 'wb');

            // create csv file
            foreach ($results as $data) {
                $dataTmp = [];
                foreach ($data as $column => $line) {
                    if ($table === 'AAA') {
                        if (in_array($column, $getEncryptedFields)) {
                            $line = $settingManagement->passwordDecrypt($line);
                        }
                    } else {
                        if (in_array($table . '.' . $column, $getEncryptedFields)) {
                            $line = $settingManagement->passwordDecrypt($line);
                        }
                    }
                    foreach ($formatConvention as $format) {
                        $isPattern = preg_match($pattern, $format, $item);

                        if ($isPattern) {
                            $columnTmp = null;

                            if (isset($item['exp3'])) {
                                $columnTmp = $item['exp3'];
                            } else {
                                $columnTmp = $item['exp1'];
                            }

                            if ($table === 'AAA') {
                                if ($columnTmp === $column) {
                                    array_push($dataTmp, $line);
                                }
                            } else {
                                $arrayColumn = explode('.', $columnTmp);
                                if ($arrayColumn[1] === $column) {
                                    array_push($dataTmp, $line);
                                }
                            }
                        }
                    }
                }
                fputcsv($file, $dataTmp, ',');
            }
            fclose($file);
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * Switch table by name table
     *
     * @param $extractTable
     * @return string|null
     */
    public function switchTable($extractTable)
    {
        switch ($extractTable) {
            case 'Role':
                return 'CCC';
                break;
            case 'User':
                return 'AAA';
                break;
            case 'Organization':
                return 'BBB';
                break;
            default:
                return null;
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
}
