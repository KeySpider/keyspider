<?php

namespace App\Console\Commands;

use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\User;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportOneLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:export_ol';

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
        $extractSettingManager = new ExtractSettingsManager('OL Extract Process Configration');
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        $arrayOfSetting = [];
        foreach ($extractSetting as $ex) {
            $arrayOfSetting = array_merge($arrayOfSetting, $ex);
        }
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $this->exportDataToOneLoginForTimeExecution($settingOfTimeExecution);
            }
        } else {
            Log::error('Can not run export schedule, getting error from config ini files');
        }
        return null;
    }

    public function exportDataToOneLoginForTimeExecution($settings)
    {
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $extractor = new DBExtractor($setting);
            $extractor->processExtractToOL();
        }
    }

    // private function updateDomainForUsers(): void
    // {
    //     $faker = Factory::create();
    //     $domain = $faker->domainName;
    //     $users = User::all();
    //     foreach ($users as $user) {
    //         $id = $user->toArray()['ID'];
    //         $name = "$id@$domain";
    //         $user->where('ID', $id)->update(['Name' => $name, 'mail' => $name]);
    //     }
    // }
}
