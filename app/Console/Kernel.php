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

use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Jobs\DBToADExtractorJob;
use App\Jobs\DBToGWExtractorJob;
use App\Jobs\DBToTLExtractorJob;
use App\Jobs\DeliveryJob;
use App\Jobs\DBImporterFromRDBJob;
use App\Jobs\LDAPExportorJob;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\Import\RDBImportSettingsManager;
use App\Ldaplibs\Export\ExportLDAPSettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    public const CONFIGURATION = 'CSV Import Process Basic Configuration';
    public const EXPORT_AD_CONFIG = 'Azure Extract Process Configration';
    public const EXPORT_GW_CONFIG = 'GW Extract Process Configration';
    public const EXPORT_TL_CONFIG = 'TL Extract Process Configration';
    public const DATABASE_INFO = 'RDB Import Database Configuration';

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Setup schedule for import
        $importSettingsManager = new ImportSettingsManager();
        $timeExecutionList = $importSettingsManager->getScheduleImportExecution();
        if (count($timeExecutionList)>0) {
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->importDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no CSV file import to process.');
        }

        // Setup schedule for RDB import
        $rdbImportSettingsManager = new RDBImportSettingsManager();
        $rdbTimeExecutionList = $rdbImportSettingsManager->getScheduleImportExecution();
        if (count($rdbTimeExecutionList)>0) {
            foreach ($rdbTimeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->rdbImportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no RDB record import to process.');
        }

        // Setup schedule for Extract
        $extractSettingManager = new ExtractSettingsManager();
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no CSV file extracting.');
        }

        // Setup schedule for Extract
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_AD_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToADForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no Active Directory extracting.');
        }

        // Setup schedule for Extract
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_TL_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToTLForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no TrustLogin extracting.');
        }

        // Setup schedule for Extract
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_GW_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToGWForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no GoogleWorkspace extracting.');
        }

        // Setup schedule for Delivery
        $scheduleDeliveryExecution = (new DeliverySettingsManager())->getScheduleDeliveryExecution();
        if ($scheduleDeliveryExecution) {
            foreach ($scheduleDeliveryExecution as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->deliveryDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no CSV file delivery.');
        }

        // Setup schedule for Export
        $exportSettingManager = new ExportLDAPSettingsManager();
        $exportSetting = $exportSettingManager->getRuleOfDataExport();
        if ($exportSetting) {
            foreach ($exportSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->LDAPExportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no LDAP export data.');
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

    /**
     * @param $dataSchedule
     */
    public function importDataForTimeExecution($dataSchedule)
    {
        Log::info('importDataForTimeExecution');
        try {
            foreach ($dataSchedule as $data) {
                $setting = $data['setting'];
                $files = $data['files'];

                // queue
                $queue = new ImportQueueManager();
                foreach ($files as $file) {
                    $dbImporter = new DBImporterJob($setting, $file);
                    $queue->push($dbImporter);
                }
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param array $settings
     */
    public function exportDataForTimeExecution($settings)
    {
        Log::info('exportDataForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param $dataSchedule
     */
     public function rdbImportDataForTimeExecution($dataSchedule)
    {
        Log::info('rdbImportDataForTimeExecution');
        try {
            foreach ($dataSchedule as $data) {

                $setting = $data['setting'];

                $con = $setting[self::DATABASE_INFO]['Connection'];
                $sql = sprintf("select count(*) as cnt from %s", $setting[self::DATABASE_INFO]['ImportTable']);
    
                $rows = DB::connection($con)->select($sql);
                $array = json_decode(json_encode($rows), true);
                $cnt = (int)$array[0]['cnt'];

                if ($cnt > 0) {
                    $queue = new ImportQueueManager();
                    //echo ("- Import $cnt records\n");
                    $dbImporter = new DBImporterFromRDBJob($setting, $file = 'dummy');
                    $queue->push($dbImporter);
                };
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param array $settings
     */
    public function LDAPExportDataForTimeExecution($settings)
    {
        Log::info('LDAPExportDataForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $exportor = new LDAPExportorJob($setting);
                $queue->push($exportor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToADForTimeExecution($settings)
    {
        Log::info('exportDataToADForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToADExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToTLForTimeExecution($settings)
    {
        Log::info('exportDataToTLForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToTLExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToGWForTimeExecution($settings)
    {
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToGWExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param array $settings
     */
    public function deliveryDataForTimeExecution($settings)
    {
        Log::info('deliveryDataForTimeExecution');
        try {
            $queue = new DeliveryQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $delivery = new DeliveryJob($setting);
                $queue->push($delivery);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
