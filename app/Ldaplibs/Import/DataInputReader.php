<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 11:18 PM
 */

namespace App\Ldaplibs\Import;


interface DataInputReader
{
    public function process();
    public function get_name_table_from_setting($setting);
    public function get_all_column_from_setting($setting);
    public function create_table($name_table, $columns);
    public function scan_file($path, $options = []);
    public function get_data_from_all_file($list_file = [], $options = []);
    public function get_data_from_one_file($file, $options = []);
    public function insert_all_data_DB($big_data, $setting);
}
