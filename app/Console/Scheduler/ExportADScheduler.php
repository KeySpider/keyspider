<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToADExtractorJob;

class ExportADScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToADExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::AD_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'AzureAD';
    }
}
