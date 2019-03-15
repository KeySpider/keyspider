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

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

if (!function_exists('clean')) {
    /**
     * @param $string
     * @return string|string[]|null
     */
    function clean($string)
    {
        return str_replace('$', '', $string); // Replaces all spaces with hyphens.
    }
}

if (!function_exists('arrays_are_similar')) {
    /**
     * Determine if two associative arrays are similar
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * @param array $a
     * @param array $b
     * @return bool
     */
    function arrays_are_similar($a, $b)
    {
        // if the indexes don't match, return immediately
        if (count(array_diff_assoc($a, $b))) {
            return false;
        }
        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach ($a as $k => $v) {
            if ($v !== $b[$k]) {
                return false;
            }
        }
        // we have identical indexes, and no unequal values
        return true;
    }
}

if (!function_exists('array_diff_assoc_recursive')) {
    /**
     * @param $array1
     * @param $array2
     * @return int
     */
    function array_diff_assoc_recursive($array1, $array2)
    {
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key])) {
                    $difference[$key] = $value;
                } elseif (!is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                    if ($new_diff != false) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!isset($array2[$key]) || $array2[$key] != $value) {
                $difference[$key] = $value;
            }
        }
        return !isset($difference) ? 0 : $difference;
    }
}

if (!function_exists('is_exits_columns')) {
    function is_exits_columns($table, $data)
    {
        $flag = true;

        foreach ($data as $key => $item) {
            if (!Schema::hasColumn($table, $key)) {
                $flag = false;
            }
        }

        return $flag;
    }
}
