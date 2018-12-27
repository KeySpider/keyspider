<?php
/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
