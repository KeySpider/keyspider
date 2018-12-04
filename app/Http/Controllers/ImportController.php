<?php

namespace App\Http\Controllers;

use App\Jobs\DBImporterJob;
use App\Jobs\QueueJobTesting;
use App\Ldaplibs\Import\SCIMReader;
use App\Ldaplibs\SettingsManager;
use Illuminate\Http\Request;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DataInputReader;
use App\Http\Models\User;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public function showFormUpload()
    {
//        $csv = new CSVReader();
//        $csv->test();
//        $scim = new SCIMReader();


        return view('imports.form_upload');
    }

    /*public function readSettings()
    {
        echo '<pre>';
        $import_settings = new SettingsManager();
//        $user_rule = $import_settings->get_rule_of_import();
        $user_rule = $import_settings->get_schedule_import_execution();

        echo '<p><h2>.INI to .JSON adapter:</h2></p>';
        print (json_encode($user_rule, JSON_PRETTY_PRINT));
        echo '</pre>';

        $csv_reader = new CSVReader(new SettingsManager());
        $list_file_csv = $csv_reader->get_list_file_csv_setting();

        foreach ($list_file_csv as $item) {
            $setting = $item['setting'];
            $list_file = $item['file_csv'];

            foreach ($list_file as $file) {
                $db_importer = new DBImporterJob($setting, $file);
                dispatch($db_importer);
            }
        }

    }*/

    public function readSettings()
    {
        echo '<pre>';
        $import_settings = new SettingsManager();
//        $user_rule = $import_settings->get_rule_of_import();
        $user_rule = $import_settings->get_rule_of_data_extract();

        echo '<p><h2>.INI to .JSON adapter:</h2></p>';
        print (json_encode($user_rule, JSON_PRETTY_PRINT));
        echo '</pre>';
    }
}
