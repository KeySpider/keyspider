<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 11:39 PM
 */

namespace App\Ldaplibs\Import;


use App\Ldaplibs\SettingsManager;

class DBImporter
{
    public function import(){
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

//        $file_name is the file list container
//        [{'file_name':'1.csv', 'file_type':'csv'}]
        return [];
    }
}

class DataInputReaderFactory{
    public static function build_DataInputReader($file_list, $rule){
        return array();//array of DataInputReader objects.
    }
}