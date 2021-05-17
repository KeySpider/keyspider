<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToOLExtractorJob;

class ExportOLScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToOLExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'OL Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'OL';
    }
}
