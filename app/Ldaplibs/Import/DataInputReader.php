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
    public function getListFileCsvSetting();
    public function getNameTableFromSetting($setting);
    public function getAllColumnFromSetting($setting);
    public function createTable($name_table, $columns);
    public function getDataFromOneFile($file, $options = []);
}
