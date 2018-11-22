<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 6:50 PM
 */

namespace App\Ldaplibs\Import;
use App\Ldaplibs\Import\DataInputReader;

class CSVReader implements DataInputReader
{
    public function test(){
        print_r("Hello from CSV Reader");
    }

    public function read_data($file_path)
    {
        // TODO: Implement read_data() method.
    }

    public function verify_data($data)
    {
        // TODO: Implement verify_data() method.
    }

    public function get_format_data($data)
    {
        // TODO: Implement format() method.
    }
}