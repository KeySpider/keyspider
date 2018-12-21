<?php

namespace App\Console\Commands;

use App\Jobs\DBImporterJob;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ExportCSV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reader setting import file and process it';
    const CONFIGURATION = "CSV Import Process Basic Configuration";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        // Setup schedule for Extract
        print_r("execute export...");
        $extractSettingManager = new ExtractSettingsManager();
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();

        $arrayOfSetting = [];
        foreach ($extractSetting as $ex){
            $arrayOfSetting = array_merge($arrayOfSetting, $ex);
        }
        var_dump(json_encode($arrayOfSetting, JSON_PRETTY_PRINT));
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                    $this->exportDataForTimeExecution($settingOfTimeExecution);
            }
        } else {
            Log::error("Can not run export schedule, getting error from config ini files");
        }
    }

    public function exportDataForTimeExecution($settings)
    {
        try {
//            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBExtractor($setting);
                $extractor->process();
//                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::channel('export')->error($e);
        }
    }

}
