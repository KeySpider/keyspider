<?php

namespace App\Console;

use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Jobs\DeliveryJob;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use DB;
use Illuminate\Support\Facades\Log;
use Exception;

class Kernel extends ConsoleKernel
{
    const CONFIGURATION = "CSV Import Process Basic Configuration";

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
            Log::error("Can not run import schedule: - Getting error from config ini files or - Nothing to import");
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
            Log::error("Can not run export schedule, getting error from config ini files");
        }

        // Setup schedule for Delivery
        $scheduleDeliveryExecution = (new DeliverySettingsManager())->getScheduleDeliveryExecution();
        if ($scheduleDeliveryExecution) {
            Log::info(json_encode($scheduleDeliveryExecution, JSON_PRETTY_PRINT));
            foreach ($scheduleDeliveryExecution as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->deliveryDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::error("Can not run delivery schedule, getting error from config ini files");
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
    private function importDataForTimeExecution($dataSchedule)
    {
        try {
            foreach ($dataSchedule as $data) {
                $setting = $data['setting'];
                $files = $data['files'];

                // get table name
                $tableName = $setting[self::CONFIGURATION]['TableNameInDB'];
                if (!isTableNameInDatabase($tableName)) {
                    Log::channel('import')->error("
                    Error: The table {$tableName} does not exist.\n
                    File ini: {$setting['IniFileName']} \n
                    File name: TableNameInDB");
                    break;
                }

                if (!is_dir($setting[self::CONFIGURATION]['FilePath'])) {
                    Log::channel('import')->error("
                        FilePath: {$setting[self::CONFIGURATION]['FilePath']} is not available \n
                        File ini: {$setting['IniFileName']} \n
                        ");
                    break;
                }

                if (empty($files)) {
                    Log::info("There are no csv files in {$setting[self::CONFIGURATION]['FilePath']}");
                } else {
                    $queue = new ImportQueueManager();
                    foreach ($files as $file) {
                        $dbImporter = new DBImporterJob($setting, $file);
                        $queue->push($dbImporter);
                    }
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
            Log::channel('export')->error($e);
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
            Log::channel('delivery')->error($e);
        }
    }
}
