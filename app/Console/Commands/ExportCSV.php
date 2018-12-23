<?php

namespace App\Console\Commands;

use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
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
        $extractSettingManager = new ExtractSettingsManager();
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();

        $arrayOfSetting = [];
        foreach ($extractSetting as $ex) {
            $arrayOfSetting = array_merge($arrayOfSetting, $ex);
        }
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                    $this->exportDataForTimeExecution($settingOfTimeExecution);
            }
        } else {
            Log::error("Can not run export schedule, getting error from config ini files");
        }
    }

    /**
     * Export Data For Execution
     *
     * @param array $settings
     */
    public function exportDataForTimeExecution($settings)
    {
        try {
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBExtractor($setting);
                $extractor->process();
            }
        } catch (Exception $e) {
            Log::channel('export')->error($e);
        }
    }
}
