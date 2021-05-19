<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBImporterJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class ImportScheduler
{
    public function execute(Schedule $schedule)
    {
        Log::debug('service name is ' . $this->getServiceName());
        // return;

        $importSettingManager = $this->getSettingManager($this->getServiceConfig());
        $timeExecutionList = $importSettingManager->getScheduleImportExecution();

        if ($timeExecutionList) {
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->queueJob($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no ' . $this->getServiceName() . ' import to process.');
        }
    }

    public function queueJob($settings)
    {
        try {
            foreach ($settings as $dataSchedule) {
                $this->queue($dataSchedule);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    protected function queue($data)
    {
        $setting = $data['setting'];
        $files = $data['files'];
        $queue = new ImportQueueManager();
        
        foreach ($files as $file) {
            $dbImporter = $this->getjob($setting, $file);
            $queue->push($dbImporter);
        }
    }

    protected function getSettingManager($configtag)
    {
        return new ImportSettingsManager($configtag);
    }

    protected function getjob($setting, $file = null)
    {
        return new DBImporterJob($setting, $file);
    }

    protected function getServiceConfig()
    {
        return Consts::CSV_IMPORT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'CSV';
    }
}
