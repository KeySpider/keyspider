<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBImporterFromRDBJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\RDBImportSettingsManager;
use Illuminate\Support\Facades\DB;

class ImportRDBScheduler extends ExportScheduler
{

    public function queue($data)
    {
        $setting = $data['setting'];
        $config = $this->getServiceConfig();
        $queue = new ImportQueueManager();

        $con = $setting[$config]['Connection'];
        $sql = sprintf("select count(*) as cnt from %s", $setting[$config]['ImportTable']);

        $rows = DB::connection($con)->select($sql);
        $array = json_decode(json_encode($rows), true);
        $cnt = (int)$array[0]['cnt'];

        // $cnt = 1;

        if ($cnt > 0) {
            $dbImporter = $this->getjob($setting, $file = 'dummy');
            $queue->push($dbImporter);
        }
    }

    protected function getSettingManager($configtag)
    {
        return new RDBImportSettingsManager($configtag);
    }

    protected function getjob($setting, $file = null)
    {
        return new DBImporterFromRDBJob($setting, $file);
    }

    protected function getServiceName()
    {
        return 'RDB Import';
    }

    protected function getServiceConfig()
    {
        return Consts::IMPORT_PROCESS_DATABASE_CONFIGURATION;
    }
}
