<?php

namespace App\Http\Controllers;

use App\Jobs\DBImporterJob;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Import\CSVReader;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showFormUpload()
    {
        return view('imports.form_upload');
    }

    /**
     * read setting
     */
    public function readSettings()
    {
        $this->read_extract_settings();
    }

    public function read_extract_settings(): void
    {
        echo '<pre>';
        $import_settings = new SettingsManager();
           $user_rule = $import_settings->getIniOutputContent('UserInfoOutput4CSV.ini');
        echo '<p><h2>.INI to .JSON adapter:</h2></p>';
        print (json_encode($user_rule, JSON_PRETTY_PRINT));
        echo '</pre>';
    }

    public function read_import_settings(): void
    {
        echo '<pre>';
        $import_settings = new SettingsManager();
        $user_rule = $import_settings->getScheduleImportExecution();

        echo '<p><h2>.INI to .JSON adapter:</h2></p>';
        print (json_encode($user_rule, JSON_PRETTY_PRINT));
        echo '</pre>';

        $this->do_import_by_queue();
    }

    private function do_import_by_queue(): void
    {
        $csv_reader = new CSVReader(new SettingsManager());
        $list_file_csv = $csv_reader->getListFileCsvSetting();

        foreach ($list_file_csv as $item) {
            $setting = $item['setting'];
            $list_file = $item['file_csv'];

            foreach ($list_file as $file) {
                Log::info('pushing to queue!');
                $db_importer = new DBImporterJob($setting, $file);
                dispatch($db_importer);
            }
        }
    }
}
