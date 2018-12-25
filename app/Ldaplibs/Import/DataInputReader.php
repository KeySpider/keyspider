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
    public function createTable($nameTable, $columns);
    public function getDataFromOneFile($file, $options, $columns, $nameTable, $pathProcessed);
}
