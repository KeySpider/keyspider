<?php

namespace App\Http\Controllers;

use App\Ldaplibs\Import\SCIMReader;
use Illuminate\Http\Request;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DataInputReader;
use App\Http\Models\User;

class ImportController extends Controller
{
    public function showFormUpload()
    {
        $csv = new CSVReader();
        $csv->test();

        $scim = new SCIMReader();

        return view('imports.form_upload');
    }

    /**
     * Reader CSV file
     * @author Le Ba Ngu <ngulb@tech.est-rouge.com>
     *
     */
    public function reader()
    {
        // reader csv file
        // insert data into user (LDAP-Test)
        $data = [
            'name'     => 'ngulb',
            'email'    => 'lebangu'.random_int(1,10000).'@gmail.com',
            'password' => '123123'
        ];

        User::create($data);

        $execution_at = '* * * * *';
        return $execution_at;
    }
}
