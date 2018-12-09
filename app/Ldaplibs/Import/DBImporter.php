<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 11:39 PM
 */

namespace App\Ldaplibs\Import;

use App\Ldaplibs\SettingsManager;
use DB;

class DBImporter
{
    protected $setting;
    protected $file_name;
    protected $csv_reader;

    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Bacic Configuration";

    public function __construct($setting, $file_name)
    {
        $this->setting = $setting;
        $this->file_name = $file_name;
        $this->csv_reader = new CSVReader(new SettingsManager());
    }


    private function get_file_list_from_json($file_name)
    {
        return [];
    }

    public function import()
    {
        $name_table = $this->csv_reader->getNameTableFromSetting($this->setting);
        $columns = $this->csv_reader->getAllColumnFromSetting($this->setting);

        $this->csv_reader->createTable($name_table, $columns);

        $params = [
            'CONVERSATION' => $this->setting[self::CONVERSION],
        ];
        $data = $this->csv_reader->getDataFromOneFile($this->file_name, $params);
        $columns = implode(",", $columns);

        foreach ($data as $key2 => $item2) {
            $tmp = [];
            foreach ($item2 as $key3 => $item3) {
                array_push($tmp, "'{$item3}'");
            }

            $tmp = implode(",", $tmp);

            // insert
            DB::statement("
                INSERT INTO {$name_table}({$columns}) values ({$tmp});
            ");
        }
    }
}
