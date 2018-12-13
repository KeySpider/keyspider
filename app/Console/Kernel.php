<?php

namespace App\Console;

use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Jobs\DeliveryJob;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\DeliveryQueueManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    const CONFIGURATION = "CSV Import Process Bacic Configuration";

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

//        Setup schedule for import
        $importSettingsManager = new ImportSettingsManager();
        $timeExecutionList = $importSettingsManager->getScheduleImportExecution();
        if ($timeExecutionList)
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->importDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        else {
            Log::error("Can not run import schedule, getting error from config ini files");
        }

//        Setup schedule for Extract
        $extractSettingManager = new ExtractSettingsManager();
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::error("Can not run export schedule, getting error from config ini files");
        }

//        Setup schedule for Delivery
        $scheduleDeliveryExecution = (new DeliverySettingsManager())->getScheduleDeliveryExecution();
        if ($scheduleDeliveryExecution) {
            Log::info(json_encode($scheduleDeliveryExecution, JSON_PRETTY_PRINT));
            foreach ($scheduleDeliveryExecution as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->deliveryDataForTimeExecution($settingOfTimeExecution);
                });
            }
        }
        else {
            Log::error("Can not run import schedule, getting error from config ini files");
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
    private function importDataForTimeExecution($dataSchedule): void
    {
        try {
            foreach ($dataSchedule as $data) {
                $setting = $data['setting'];
                $files = $data['files'];

                if (!is_dir($setting[self::CONFIGURATION]['FilePath'])) {
                    Log::channel('import')->error(
                        "ImportTable: {$setting[self::CONFIGURATION]['ImportTable']}
                        FilePath: {$setting[self::CONFIGURATION]['FilePath']} is not available"
                    );
                    break;
                }

                if (empty($files)) {
                    Log::channel('import')->info(json_encode($setting[self::CONFIGURATION], JSON_PRETTY_PRINT)." WITH FILES EMPTY");
                } else {
                    $queue = new ImportQueueManager();
                    foreach ($files as $file) {
                        $dbImporter = new DBImporterJob($setting, $file);
                        $queue->push($dbImporter);
                    }
                }
            }
        } catch (Exception $e) {
            Log::channel('import')->error($e);
        }
    }

    public function exportDataForTimeExecution($settings)
    {
        $queue = new ExtractQueueManager();
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $extractor = new DBExtractorJob($setting);
            $queue->push($extractor);
        }
    }

    public function deliveryDataForTimeExecution($settings)
    {
        $queue = new DeliveryQueueManager();
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            Log::info(json_encode($setting, JSON_PRETTY_PRINT));
            $delivery = new DeliveryJob($setting);
            $queue->push($delivery);
        }
    }

}
