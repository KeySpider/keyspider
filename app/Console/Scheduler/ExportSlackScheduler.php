<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToSlackExtractorJob;

class ExportSlackScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToSlackExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::SLACK_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'Slack';
    }
}
