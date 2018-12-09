<?php

namespace App\Console;

use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Jobs\ExtractionData;
use App\Jobs\ProcessPodcast;
use App\Ldaplibs\Delivery\DBExtractor;
use App\Ldaplibs\Delivery\ExtractQueueManager;
use App\Ldaplibs\Import\ImportQueueManager;
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
        $setting = new SettingsManager();

        $timeExecutionList = $setting->get_schedule_import_execution();
        foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
            $schedule->call(function() use ($settingOfTimeExecution){
                $this->importDataForTimeExecution($settingOfTimeExecution);
            })->dailyAt($timeExecutionString);
        }


        $extractSetting = $setting->get_rule_of_data_extract();
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
