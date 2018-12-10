<?php

namespace App\Ldaplibs\Delivery;

use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;

class DBExtractor
{
    /**
     * define const
     */
    const EXTRACTION_CONDITION                 = 'Extraction Condition';
    const EXTRACTION_CONFIGURATION             = "Extraction Process Bacic Configuration";
    const OUTPUT_PROCESS_CONVERSION            = "Output Process Conversion";
    const Extraction_Process_Format_Conversion = "Extraction Process Format Conversion";

    protected $setting;

    /**
     * DBExtractor constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
    }

    /**
     * process extract
     */
    public function process()
    {
        // $success = preg_match('/\(\s*(?<exp1>\d+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/', $pattern, $match);
        try {
            sleep(2);
            $setting = $this->setting;

            $outputProcessConvention = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
            $extractTable            = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractCondition        = $setting[self::EXTRACTION_CONDITION];
            $formatConvention        = $setting[self::Extraction_Process_Format_Conversion];

            // get data by condition setting file
            $table = $this->switchTable($extractTable);
            $table   = "\"{$table}\"";
            $queries = [];

            foreach ($extractCondition as $column => $where) {
                if ($this->checkExitsString($where)) {
                    $where = Carbon::now()->addDay($this->checkExitsString($where))->format('Y/m/d');
                    $query = "\"{$column}\" <= '{$where}'";
                } else {
                    $query = "\"{$column}\" = '{$where}'";
                }
                array_push($queries, $query);
            }

            // select * from "AAA" where "AAA.002" <= 'TODAY() + 7' and "AAA.015" = '0';
            $queries = implode(' AND ', $queries);

            $sql = "SELECT * FROM {$table} WHERE {$queries} ";

            $result = DB::select($sql);
            $results = json_decode(json_encode($result), true);
            $nRecords = sizeof($results);
            Log::info("Number of records to be extracted: $nRecords");

            if (!empty($results)) {
                $infoOutputIni = parse_ini_file(storage_path($outputProcessConvention));

                $fileName = $infoOutputIni['FileName'];
                $tempPath = $infoOutputIni['TempPath'];

                if (file_exists(storage_path("{$tempPath}"))) {
                    $fileName = $this->removeExt($fileName).'_'.rand(100, 900).'.csv';
                }

                Log::info("Export to file: $fileName");

                $file = fopen(storage_path("{$tempPath}/{$fileName}"), 'wb');

                // create csv file
                foreach ($results as $data) {
                    $tmp = [];
                    foreach ($data as $column => $line) {
//                        array_push($tmp, $line);
                        foreach ($formatConvention as $format) {
                            if ($format === "({$column})") {
                                array_push($tmp, $line);
                            }
                        }
                    }
                    fputcsv($file, $tmp, ',');
                }
                fclose($file);
            } else {
                Log::channel('export')->info("Data is empty");
            }
        } catch (Exception $e) {
            Log::channel('export')->error($e);
        }
    }

    /**
     * Extract data by condition in setting file
     *
     * @param array $condition
     * @param string $table
     *
     * @author ngulb@tech.est-rouge.com
     *
     * @return array
     */
    public function extractDataByCondition($condition = [], $table)
    {
        try {
            $table   = "\"{$table}\"";
            $queries = [];

            foreach ($condition as $column => $where) {
                if ($this->checkExitsString($where)) {
                    $where = Carbon::now()->addDay($this->checkExitsString($where))->format('Y/m/d');
                    $query = "\"{$column}\" <= '{$where}'";
                } else {
                    $query = "\"{$column}\" = '{$where}'";
                }
                array_push($queries, $query);
            }

            // select * from "AAA" where "AAA.002" <= 'TODAY() + 7' and "AAA.015" = '0';
            $queries = implode(' AND ', $queries);

            $sql = "SELECT * FROM {$table} WHERE {$queries} ";
            $result = DB::select($sql);
            $resultArray = json_decode(json_encode($result), true);
            return $resultArray[0];
        } catch (Exception $e) {
            Log::channel('export')->error($e);
        }
    }

    /**
     * Check exits string
     *
     * @param string $str
     *
     * @author ngulb@tech.est-rouge.com
     *
     * @return bool
     */
    public function checkExitsString($str)
    {
        if (strpos($str, 'TODAY()') !== false) {
            return (int)substr($str, -1);
        }
        return false;

        return false;
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
