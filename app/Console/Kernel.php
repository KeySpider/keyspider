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

use App\Ldaplibs\SettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    public const MASTER_DB_CONFIG = 'Master DB Configurtion';
    public const CONFIGURATION = 'CSV Import Process Basic Configration';
    public const DATABASE_INFO = 'RDB Import Database Configuration';
    public const IMPORT_CSV_CONFIG = 'CSV Import Process Configration';
    public const IMPORT_RDB_CONFIG = 'RDB Import Process Configration';
    public const IMPORT_SCIM_CONFIG = 'SCIM Input Process Configration';
    public const EXPORT_AD_CONFIG = 'Azure Extract Process Configration';
    public const EXPORT_BOX_CONFIG = 'BOX Extract Process Configration';
    public const EXPORT_CSV_CONFIG = 'CSV Extract Process Configration';
    public const EXPORT_GW_CONFIG = 'GW Extract Process Configration';
    public const EXPORT_LDAP_CONFIG = 'LDAP Export Process Configration';
    public const EXPORT_OL_CONFIG = 'OL Extract Process Configration';
    public const EXPORT_SF_CONFIG = 'SF Extract Process Configration';
    public const EXPORT_SLACK_CONFIG = 'SLACK Extract Process Configration';
    public const EXPORT_TL_CONFIG = 'TL Extract Process Configration';
    public const EXPORT_ZOOM_CONFIG = 'ZOOM Extract Process Configration';
    public const DELIVERY_CSV_CONFIG = 'CSV Output Process Configration';

    // [Master DB Configurtion] と [SCIM Input Process Configration] は 実行時間を定義しないため "none" をマッピングします。
    public const OPERATIONS = array(
        self::MASTER_DB_CONFIG => 'none',
        self::IMPORT_CSV_CONFIG => 'App\Console\Scheduler\ImportScheduler',
        self::IMPORT_RDB_CONFIG => 'App\Console\Scheduler\ImportRDBScheduler',
        self::IMPORT_SCIM_CONFIG => 'none',
        self::EXPORT_AD_CONFIG => 'App\Console\Scheduler\ExportADScheduler',
        self::EXPORT_BOX_CONFIG => 'App\Console\Scheduler\ExportBoxScheduler',
        self::EXPORT_CSV_CONFIG => 'App\Console\Scheduler\ExportScheduler',
        self::EXPORT_GW_CONFIG => 'App\Console\Scheduler\ExportGWScheduler',
        self::EXPORT_LDAP_CONFIG => 'App\Console\Scheduler\ExportLDAPScheduler',
        self::EXPORT_OL_CONFIG => 'App\Console\Scheduler\ExportOLScheduler',
        self::EXPORT_SF_CONFIG => 'App\Console\Scheduler\ExportSFScheduler',
        self::EXPORT_SLACK_CONFIG => 'App\Console\Scheduler\ExportSlackScheduler',
        self::EXPORT_TL_CONFIG => 'App\Console\Scheduler\ExportTLScheduler',
        self::EXPORT_ZOOM_CONFIG => 'App\Console\Scheduler\ExportZoomScheduler',
        self::DELIVERY_CSV_CONFIG => 'App\Console\Scheduler\OutputScheduler'
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
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
