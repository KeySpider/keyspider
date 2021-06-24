<?php

namespace App\Console\Commands;

use App\Commons\Consts;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\SCIM\TrustLogin\SCIMToTrustLogin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportTrustLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "command:export_tl";

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
        $keyspider = (new SettingsManager())->getAllConfigsFromKeyspiderIni();

        if (array_key_exists(Consts::TL_EXTRACT_PROCESS_CONFIGURATION, $keyspider)) {
            // Setup schedule for Extract
            $extractSettingManager = new ExtractSettingsManager(Consts::TL_EXTRACT_PROCESS_CONFIGURATION);
            $extractSetting = $extractSettingManager->getRuleOfDataExtract();
            $arrayOfSetting = [];
            foreach ($extractSetting as $ex) {
                $arrayOfSetting = array_merge($arrayOfSetting, $ex);
            }
            if ($extractSetting) {
                foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                    $this->exportDataToTrustLoginForTimeExecution($settingOfTimeExecution);
                }
            } else {
                Log::error("Can not run export schedule, getting error from config ini files");
            }
        } else {
            Log::Info("nothing to do.");
        }
        return null;
    }

    public function exportDataToTrustLoginForTimeExecution($settings)
    {
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule["setting"];
            $extractor = new DBExtractor($setting);
            $scimLib = new SCIMToTrustLogin();
            $extractor->processExtractToSCIM($scimLib);
        }
    }
}
