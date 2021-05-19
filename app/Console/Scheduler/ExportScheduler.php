<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBExtractorJob;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class ExportScheduler
{
    public function execute(Schedule $schedule)
    {
        Log::debug('service name is ' . $this->getServiceName());
        // return;

        $extractSettingManager = new ExtractSettingsManager($this->getServiceConfig());
        $timeExecutionList = $extractSettingManager->getRuleOfDataExtract();

        if ($timeExecutionList) {
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->queueJob($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no ' . $this->getServiceName() . ' extracting.');
        }
    }

    public function queueJob($settings)
    {
        try {
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $queue = new ExtractQueueManager();
                $job = $this->getJob($setting);
                $queue->push($job);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    protected function getJob($setting)
    {
        return new DBExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::CSV_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'CSV';
    }
}
