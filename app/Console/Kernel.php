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
use App\Jobs\DeliveryJob;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    public const CONFIGURATION = 'CSV Import Process Basic Configuration';

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
            Log::debug('Currently, there is no file import to process.');
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
            Log::debug('Currently, there is no file extracting.');
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
            Log::debug('Currently, there is no file delivery.');
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
     * @param array $settings
     */
    public function deliveryDataForTimeExecution($settings)
    {
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
