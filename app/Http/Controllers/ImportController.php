<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ldaplibs\Import\CSVReader;

class ImportController extends Controller
{
    public function showFormUpload()
    {
        $reader = new CSVReader();
        $reader->test();
        return view('imports.form_upload');
    }
}
