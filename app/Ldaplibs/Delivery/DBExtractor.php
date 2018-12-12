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
        // \(\s*(?<exp1>[\w\.]+)\s*((,\s*(?<exp2>[^\)]+))?|\s*\->\s*(?<exp3>[\w\.]+))\s*\)

        /**
         * select "AAA.003","AAA.004","AAA.005", "AAA.009","AAA.008",
            "BBB.001","BBB.004",
            "CCC.001", "CCC.003"
            from "AAA"
            left join "BBB" on "AAA.004" = "BBB.001"
            left join "CCC" on "AAA.005" = "CCC.001";
         */

        try {
            $setting                 = $this->setting;
            $outputProcessConvention = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
            $extractTable            = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractCondition        = $setting[self::EXTRACTION_CONDITION];
            $formatConvention        = $setting[self::Extraction_Process_Format_Conversion];

            // get sql query by condition in setting file
            $table = $this->switchTable($extractTable);
            $sql = $this->getSQLQueryByExtractCondition("\"{$table}\"", $extractCondition, $formatConvention);

            // get data by query
            $results = $this->getDataByQuery($sql);

            // extract csv file into temp
            if (!empty($results)) {
                $infoOutputIni = parse_ini_file(($outputProcessConvention));

                $fileName = $infoOutputIni['FileName'];
                $tempPath = $infoOutputIni['TempPath'];

                if (is_file("{$tempPath}/$fileName")) {
                    $fileName = $this->removeExt($fileName).'_'.Carbon::now()->format('Ymd').rand(100,999).'.csv';
                }

                Log::info("Export to file: $fileName");

                $file = fopen(("{$tempPath}/{$fileName}"), 'wb');

                // create csv file
                foreach ($results as $data) {
                    $tmp = [];
                    foreach ($data as $column => $line) {
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
     * Get sql query by condition
     *
     * @param $table
     * @param $extractCondition
     * @return string
     */
    public function getSQLQueryByExtractCondition($table, $extractCondition, $formatConvention)
    {
        $queries = [];
        $selectColumn = [];

        $pattern = "/\(\s*(?<exp1>[\w\.]+)\s*((,\s*(?<exp2>[^\)]+))?|\s*\->\s*(?<exp3>[\w\.]+))\s*\)/";

        foreach ($extractCondition as $column => $where) {
            if ($this->checkExitsString($where)) {
                $where = Carbon::now()->addDay($this->checkExitsString($where))->format('Y/m/d');
                $query = "\"{$column}\" <= '{$where}'";
            } else {
                $query = "\"{$column}\" = '{$where}'";
            }
            array_push($selectColumn, "\"{$column}\"");
            array_push($queries, $query);
        }

        foreach ($formatConvention as $key => $value) {
            $isFormat = preg_match($pattern, $value, $data);
            if ($isFormat) {
                $cl = "\"{$data['exp1']}\"";
                array_push($selectColumn, $cl);
            }
        }

        $queries = empty($queries) ? '' : "WHERE ".implode(' AND ', $queries);
        $selectColumn = empty($selectColumn) ? "*" : implode(',', $selectColumn);


        $sql = "SELECT {$selectColumn} FROM {$table} {$queries}";

//        if ($table === "\"AAA\"") {
//            foreach ($formatConvention as $key => $item) {
//                $isCheckPattern = preg_match($pattern, $item, $data);
//                if ($isCheckPattern) {
//                    if (isset($data['exp3'])) {
//
//                        $joinTable = $this->getJoinTable();
//                        $str = "left join \"BBB\" on \"{$data['exp1']}\" = \"BBB.001\" ";
//                    }
//                }
//            }
//            die;
//        }

        return $sql;
    }

    /**
     * @param $sql
     * @return mixed
     */
    public function getDataByQuery($sql)
    {
        $result = DB::select($sql);
        $results = json_decode(json_encode($result), true);
        $nRecords = sizeof($results);
        Log::info("Number of records to be extracted: $nRecords");
        return $results;
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
