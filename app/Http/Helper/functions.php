<?php
/**
 * Created by PhpStorm.
 * User: ert
 * Date: 2018-12-21
 * Time: 15:54
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * function test
 */
if (!function_exists('test')) {
    function test()
    {
        dd('ok');
    }
}

if (!function_exists('isTableNameInDatabase')) {
    /**
     * @param string $tableName
     * @return bool
     */
    function isTableNameInDatabase($tableName)
    {
        $query = "SELECT EXISTS (
           SELECT 1
           FROM   information_schema.tables 
           WHERE  table_schema = 'public'
           AND    table_name = '{$tableName}'
        );";
        $result = DB::select($query);

        if (!empty($result)) {
            $flag = $result[0]->exists;
        } else {
            $flag = false;
        }

        return $flag;
    }
}

if (!function_exists('removeExt')) {
    /**
     * @param string $fileName
     * @return string|string[]|null
     */
    function removeExt($fileName)
    {
        $pattern = "/\\.[^.\\s]{3,4}$/";
        $file = preg_replace($pattern, '', $fileName);
        return $file;
    }
}

if (!function_exists('mkDirectory')) {
    /**
     * @param string $path
     * @return string|string[]|null
     */
    function mkDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

if (!function_exists('moveFile')) {
    /**
     * @param $from
     * @param $to
     * @return string|string[]|null
     */
    function moveFile($from, $to)
    {
        if (is_file($from)) {
            File::move($from, $to);
        }
    }
}
