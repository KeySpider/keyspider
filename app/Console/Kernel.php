<?php

namespace App\Console;

use App\Ldaplibs\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use DB;

class Kernel extends ConsoleKernel
{

    const CONVERSION = "CSV Import Process Format Conversion";
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
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
//        $setting_management = new SettingsManager();
//        $list_setting = $setting_management->get_rule_of_import();
//
//        $schedule->call(function () {
//            // do something
//        })->dailyAt('');
//
//        dd($list_setting);
//
//        foreach ($list_setting as $setting) {
////            $execution_time
//        }
//
//        $schedule->command('command:ImportCSV', [])
//                ->timezone('Asia/Ho_Chi_Minh')
//                ->dailyAt('15:25');
//
//        $schedule->command('command:ImportCSV')
//            ->timezone('Asia/Ho_Chi_Minh')
//            ->dailyAt('15:30');
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
