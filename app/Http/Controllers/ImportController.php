<?php

namespace App\Http\Controllers;

use App\Ldaplibs\Import\SCIMReader;
use App\Ldaplibs\SettingsManager;
use Illuminate\Http\Request;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DataInputReader;
use App\Http\Models\User;

class ImportController extends Controller
{
    public function showFormUpload()
    {
//        $csv = new CSVReader();
//        $csv->test();
//        $scim = new SCIMReader();

        return view('imports.form_upload');
    }

    public function readSettings()
    {
        $import_settings = new SettingsManager();
        $import_settings->get_rule_of_import();
    }
}
