<?php

namespace App\Console;

use App\Jobs\ExtractionData;
use App\Jobs\ProcessPodcast;
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

        // schedule for import
        try {
            $schedule_setting = $setting->get_schedule_import_execution();
            foreach ($schedule_setting as $time => $data_setting) {
//                $schedule->job(new ProcessPodcast($data_setting));
            }
        } catch (Exception $e) {
            Log::channel('import')->error($e);
        }

        // schedule for extract data
        try {
            $extractSetting = $setting->get_rule_of_data_extract();
            foreach ($extractSetting as $time => $setting) {
//                $schedule->job(new ExtractionData($setting));
            }
        } catch (Exception $e) {
            Log::channel('export')->error($e);
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
}
