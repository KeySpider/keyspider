<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToADExtractorJob;

class ExportADScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToADExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'Azure Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'AzureAD';
    }
}
