<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToSFExtractorJob;

class ExportSFScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToSFExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::SF_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'SalesForce';
    }
}
