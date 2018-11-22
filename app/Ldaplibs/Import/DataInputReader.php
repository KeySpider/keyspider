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
    public function read_data($file_path);
    public function verify_data($data);
    public function get_format_data($data);
}