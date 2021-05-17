<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToZoomExtractorJob;

class ExportZoomScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToZoomExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'ZOOM Extract Process Configration';
    }

    protected function getServiceName()
    {
        return 'Zoom';
    }
}
