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
    protected $table;
    protected $columns;
    protected $data;

    public function __construct($table, $columns, $data)
    {
        $this->table = $table;
        $this->columns = $columns;
        $this->data = $data;
    }

    /**
     * import
     */
    public function import_test()
    {
        $file_list = $this->get_file_list_from_json($file_name='');
        $queue = new ImportQueueManager($file_list);
        $settings = new SettingsManager();
        $rule = $settings->get_rule_of_import();
        $data_input_reader_list = DataInputReaderFactory::build_DataInputReader($file_list,$rule);

        foreach ($data_input_reader_list as $object){
            $queue->push($object);
        }
        $queue->process();
    }

    private function get_file_list_from_json($file_name)
    {
        return [];
    }

    public function import()
    {
         // bulk insert
        foreach ($this->data as $key2 => $item2) {
            $tmp = [];
            foreach ($item2 as $key3 => $item3) {
                array_push($tmp, "'{$item3}'");
            }

            $tmp = implode(",", $tmp);

            // insert
            $is_insert_db = DB::statement("
                INSERT INTO {$this->table}({$this->columns}) values ({$tmp});
            ");
            if ($is_insert_db) {
                return true;
            }
            return false;
        }
    }
}

class DataInputReaderFactory{
    public static function build_DataInputReader($file_list, $rule){
        return array();//array of DataInputReader objects.
    }
}
