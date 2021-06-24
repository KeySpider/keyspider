<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToGWExtractorJob;

class ExportGWScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToGWExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::GW_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'GW';
    }
}
