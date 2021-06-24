<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToZoomExtractorJob;

class ExportZoomScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToZoomExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::ZOOM_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'Zoom';
    }
}
