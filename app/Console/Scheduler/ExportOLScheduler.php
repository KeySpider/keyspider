<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToOLExtractorJob;

class ExportOLScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToOLExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::OL_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'OL';
    }
}
