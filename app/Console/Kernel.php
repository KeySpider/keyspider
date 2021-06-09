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

namespace App\Console;

use App\Commons\Consts;
use App\Ldaplibs\SettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    // [Master DB Configuration] と [SCIM Input Process Configuration] は 実行時間を定義しないため "none" をマッピングします。
    private const OPERATIONS = array(
        Consts::MASTER_DB_CONFIGURATION             =>  "none",
        Consts::CSV_IMPORT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ImportScheduler",
        Consts::RDB_IMPORT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ImportRDBScheduler",
        Consts::SCIM_IMPORT_PROCESS_CONFIGURATION   =>  "none",
        Consts::AD_EXTRACT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ExportADScheduler",
        Consts::BOX_EXTRACT_PROCESS_CONFIGURATION   =>  "App\Console\Scheduler\ExportBoxScheduler",
        Consts::CSV_EXTRACT_PROCESS_CONFIGURATION   =>  "App\Console\Scheduler\ExportScheduler",
        Consts::GW_EXTRACT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ExportGWScheduler",
        Consts::LDAP_EXTRACT_PROCESS_CONFIGURATION  =>  "App\Console\Scheduler\ExportLDAPScheduler",
        Consts::OL_EXTRACT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ExportOLScheduler",
        Consts::SF_EXTRACT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ExportSFScheduler",
        Consts::SLACK_EXTRACT_PROCESS_CONFIGURATION =>  "App\Console\Scheduler\ExportSlackScheduler",
        Consts::TL_EXTRACT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\ExportTLScheduler",
        Consts::ZOOM_EXTRACT_PROCESS_CONFIGURATION  =>  "App\Console\Scheduler\ExportZoomScheduler",
        Consts::RDB_EXTRACT_PROCESS_CONFIGURATION   =>  "App\Console\Scheduler\ExportRDBScheduler",
        Consts::CSV_OUTPUT_PROCESS_CONFIGURATION    =>  "App\Console\Scheduler\OutputScheduler",
    );

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     * keyspider.ini に設定したセクション名を取得し、セクション名に紐づく処理を実行します。
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // keyspider.ini の設定内容を全て取得します。
        $keyspiderIni = (new SettingsManager())->getAllConfigsFromKeyspiderIni();
        $sections = array_keys($keyspiderIni);
        foreach ($sections as $section) {
            try {
                $className = self::OPERATIONS[$section];
                if ($className == "none") {
                    continue;
                }
                $clazz = new $className;
                $clazz->execute($schedule);
            } catch (Exception $exception) {
                // セクション名が間違っている場合はログを出力して後続処理をおこないます。
                Log::error("There is a mistake in [$section] of keyspider.ini.");
            }
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . "/Commands");
        require base_path("routes/console.php");
    }
}
