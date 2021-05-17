<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToBoxExtractorJob;

class ExportBoxScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToBoxExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'BOX Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'Box';
    }
}
