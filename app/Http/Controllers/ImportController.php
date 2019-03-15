<?php

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Http\Controllers;

use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Illuminate\Support\Facades\Artisan;

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
