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

    public function readSettings(){
        echo '<pre>';
        $import_settings = new SettingsManager();
        $user_rule = $import_settings->get_rule_of_import();

        echo '<p><h2>.INI to .JSON adapter:</h2></p>';
        print ($user_rule);
        echo '</pre>';
    }
}
