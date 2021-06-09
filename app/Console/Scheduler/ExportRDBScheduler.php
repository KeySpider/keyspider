<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToRDBExtractorJob;

class ExportRDBScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToRDBExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::RDB_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'RDB';
    }
}
