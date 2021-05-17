<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToTLExtractorJob;

class ExportTLScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToTLExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'TL Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'TL';
    }
}
