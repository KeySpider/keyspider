<?php

namespace App\Http\Controllers;

use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Illuminate\Support\Facades\Artisan;

/*
 * This class is for testing perpose only.
 * */
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
//        $this->readImportSettings();
//        $this->readExtractSettings();
//        $queue = new ImportQueueManager();
//        var_dump(QueueManager::getQueueSettings());
//        $import = new DBImporterJob(null, null);
        Artisan::call('schedule:run', array());
    }

    /**
     * Read extract setting
     */
    public function readExtractSettings()
    {

        echo '<pre>';
        $export_settings = new ExtractSettingsManager();
        $user_rule = $export_settings->getRuleOfDataExtract();
        print (json_encode($user_rule, JSON_PRETTY_PRINT));
        echo '</pre>';
    }

    /**
     * Read import setting
     */
    public function readImportSettings()
    {
        echo '<pre>';
        $import_settings = new ImportSettingsManager();
        $user_rule = $import_settings->getScheduleImportExecution();

        echo '<p><h2>.INI to .JSON adapter:</h2></p>';
        print (json_encode($user_rule, JSON_PRETTY_PRINT));
        echo '</pre>';
        Artisan::call('command:import', array());
    }
}
