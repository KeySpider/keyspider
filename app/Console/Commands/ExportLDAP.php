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

namespace App\Console\Commands;

use App\Ldaplibs\Export\LDAPExportor;
use App\Ldaplibs\Export\ExportLDAPSettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportLDAP extends Command
{
    public const CONFIGURATION = 'LDAP Import Process Basic Configuration';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:export_ldap';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reader setting import LDAP and process it';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        // Setup schedule for Extract
        $exportSettingManager = new ExportLDAPSettingsManager();
        $exportSetting = $exportSettingManager->getRuleOfDataExport();
        $arrayOfSetting = [];
        foreach ($exportSetting as $ex) {
            $arrayOfSetting = array_merge($arrayOfSetting, $ex);
        }
        if ($exportSetting) {
            foreach ($exportSetting as $timeExecutionString => $settingOfTimeExecution) {
                $this->exportDataForTimeExecution($settingOfTimeExecution);
            }
        } else {
            Log::error('Can not run export schedule, getting error from config ini files');
        }
        return null;
    }

    /**
     * Export Data For Execution
     *
     * @param array $settings
     */
    public function exportDataForTimeExecution($settings)
    {
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $exportor = new LDAPExportor($setting);
            $exportor->processExportLDAP4User();
        }
    }
}
