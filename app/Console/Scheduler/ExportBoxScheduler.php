<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToBoxExtractorJob;

class ExportBoxScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToBoxExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::BOX_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'Box';
    }
}
