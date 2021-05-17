<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToGWExtractorJob;

class ExportGWScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToGWExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'GW Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'GW';
    }
}
