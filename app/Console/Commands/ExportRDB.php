<?php

namespace App\Console\Commands;

use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportRDB extends Command
{
    public const EXPORT_RDB_CONFIG = 'RDB Extract Process Configration';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:export_rdb';

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
        $sections = (new SettingsManager())->getAllConfigsFromKeyspiderIni();
        
        if (array_key_exists(self::EXPORT_RDB_CONFIG, $sections)) {
            // Setup schedule for Extract
            $extractSettingManager = new ExtractSettingsManager(self::EXPORT_RDB_CONFIG);
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
        } else {
            Log::Info('nothing to do.');
        }
        return null;
    }

    public function exportDataForTimeExecution($settings)
    {
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $extractor = new DBExtractor($setting);
            $extractor->processExtractToRDB();
        }
    }

}
