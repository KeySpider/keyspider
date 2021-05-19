<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Jobs\DeliveryJob;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class OutputScheduler
{
    public function execute(Schedule $schedule)
    {
        Log::debug('service name is ' . $this->getServiceName());
        // return;

        $outputSettingManager = new DeliverySettingsManager();
        $timeExecutionList = $outputSettingManager->getScheduleDeliveryExecution();

        if ($timeExecutionList) {
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->queueJob($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no ' . $this->getServiceName());
        }
    }

    public function queueJob($settings)
    {
        try {
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $queue = new DeliveryQueueManager();
                $job = $this->getJob($setting);
                $queue->push($job);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    protected function getJob($setting)
    {
        return new DeliveryJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::CSV_OUTPUT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'CSV Output';
    }
}
