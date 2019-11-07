<?php

namespace App\Console\Commands;

use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportAzureAD extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:export_ad';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     */
    public function handle()
    {
        // Setup schedule for Extract
        $extractSettingManager = new ExtractSettingsManager('Azure Extract Process Configration');
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
            Log::error('Can not run export schedule, getting error from config ini files');
        }
        return null;
    }

    public function exportDataForTimeExecution($settings)
    {
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $extractor = new DBExtractor($setting);
            $extractor->processExtractToAD();
        }
    }

}
