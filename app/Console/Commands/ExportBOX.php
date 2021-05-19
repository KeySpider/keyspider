<?php

namespace App\Console\Commands;

use App\Commons\Consts;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportBOX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "command:export_box";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Command description";

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
        $sections = (new SettingsManager())->getAllConfigsFromKeyspiderIni();

        if (array_key_exists(Consts::BOX_EXTRACT_PROCESS_CONFIGURATION, $sections)) {
            // Setup schedule for Extract
            $extractSettingManager = new ExtractSettingsManager(Consts::BOX_EXTRACT_PROCESS_CONFIGURATION);
            $extractSetting = $extractSettingManager->getRuleOfDataExtract();
            $arrayOfSetting = [];
            foreach ($extractSetting as $ex) {
                $arrayOfSetting = array_merge($arrayOfSetting, $ex);
            }
            if ($extractSetting) {
                foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                    $this->exportDataToBOXForTimeExecution($settingOfTimeExecution);
                }
            } else {
                Log::error("Can not run export schedule, getting error from config ini files");
            }
        } else {
            Log::Info("nothing to do.");
        }
        return null;
    }

    public function exportDataToBOXForTimeExecution($settings)
    {
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule["setting"];
            $extractor = new DBExtractor($setting);
            $extractor->processExtractToBOX();
        }
    }
}
