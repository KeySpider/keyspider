<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToSFExtractorJob;

class ExportSFScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToSFExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'SF Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'SalesForce';
    }
}
