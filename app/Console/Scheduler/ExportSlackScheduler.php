<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToSlackExtractorJob;

class ExportSlackScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToSlackExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'SLACK Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'Slack';
    }
}
