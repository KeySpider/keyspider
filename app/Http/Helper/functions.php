<?php
/**
 * Created by PhpStorm.
 * User: ert
 * Date: 2018-12-21
 * Time: 15:54
 */

use Illuminate\Support\Facades\DB;

/**
 * function test
 */
if (!function_exists('test')) {
    function test() {
        dd('ok');
    }
}

/**
 * Check exists table name
 */
if (!function_exists('isTableNameInDatabase')) {
    /**
     * @param $tableName
     * @return bool
     */
    function isTableNameInDatabase($tableName) {
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
