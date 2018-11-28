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
    public function get_data_from_all_file_csv($list_file_csv = []);
    public function get_data_from_one_file_csv($file_csv);
    public function insert_all_data_DB($big_data);
}
