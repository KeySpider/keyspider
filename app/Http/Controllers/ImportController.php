<?php

namespace App\Http\Controllers;

use App\Ldaplibs\Import\SCIMReader;
use Illuminate\Http\Request;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DataInputReader;

class ImportController extends Controller
{
    public function showFormUpload()
    {
        $csv = new CSVReader();
        $csv->test();

        $scim = new SCIMReader();

        return view('imports.form_upload');
    }
}
