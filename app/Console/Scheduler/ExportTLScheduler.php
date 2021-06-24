<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToTLExtractorJob;

class ExportTLScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToTLExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::TL_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'TL';
    }
}
