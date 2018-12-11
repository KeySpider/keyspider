<?php

namespace App\Console;

use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Ldaplibs\Delivery\DBExtractor;
use App\Ldaplibs\Delivery\ExtractQueueManager;
use App\Ldaplibs\Delivery\ExtractSettingsManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
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
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $importSettingsManager = new ImportSettingsManager();

        $timeExecutionList = $importSettingsManager->getScheduleImportExecution();
        foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
            $schedule->call(function() use ($settingOfTimeExecution){
                $this->importDataForTimeExecution($settingOfTimeExecution);
            })->dailyAt($timeExecutionString);
        }

        $extractSettingManager = new ExtractSettingsManager();
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
            $schedule->call(function() use ($settingOfTimeExecution){
                $this->exportDataForTimeExecution($settingOfTimeExecution);
            })->dailyAt($timeExecutionString);
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * @param $dataSchedule
     */
    private function importDataForTimeExecution($dataSchedule): void
    {
        foreach ($dataSchedule as $data) {
            $setting = $data['setting'];
            $files = $data['files'];

            $queue = new ImportQueueManager();
            foreach ($files as $file) {
                $dbImporter = new DBImporterJob($setting, $file);
                $queue->push($dbImporter);
            }
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

}
