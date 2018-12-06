<?php

namespace App\Ldaplibs\Delivery;

use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use DB;

class DBExtractor
{
    /**
     * define const
     */
    const EXTRACT_FORMAT_CONVENTION = "Extraction Process Format Conversion";
    const OUTPUT_PROCESS_CONVERSION = "Output Process Conversion";

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
        return $resultArray;
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
     * Extract CSV file from setting
     *
     * @param array $data
     * @param array $setting
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function extractCSVBySetting($data = [], $setting = [])
    {
        $extractFormatConvention = $setting[self::EXTRACT_FORMAT_CONVENTION];
        $outputProcessConvention = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
        dd($outputProcessConvention);

        dd(parse_ini_file("OrganizationInfoOutput4CSV.ini", true));

        $settingManagement = new SettingsManager();
        $infoDelivery = $settingManagement->get_ini_output_content("OrganizationInfoOutput4CSV.ini");
        dd($infoDelivery);

        dump($setting);
        dump($extractFormatConvention);
        dd($data[0]);
    }
}
