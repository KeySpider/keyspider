<?php

namespace App\Console\Commands;

use App\Jobs\DBImporterJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportCSV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:import';

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
        $importSettingsManager = new ImportSettingsManager();
        $timeExecutionList = $importSettingsManager->getScheduleImportExecution();
        if ($timeExecutionList) {
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $this->importDataForTimeExecution($settingOfTimeExecution);
            }
        } else {
            Log::error("Can not run import schedule, getting error from config ini files");
        }
    }

    private function importDataForTimeExecution($dataSchedule): void
    {
        try {
            foreach ($dataSchedule as $data) {
                $setting = $data['setting'];
                $files = $data['files'];

                if (!is_dir($setting[self::CONFIGURATION]['FilePath'])) {
                    Log::channel('import')->error(
                        "ImportTable: {$setting[self::CONFIGURATION]['ImportTable']}
                        FilePath: {$setting[self::CONFIGURATION]['FilePath']} is not available"
                    );
                    break;
                }

                if (empty($files)) {
                    $infoSetting = json_encode($setting[self::CONFIGURATION], JSON_PRETTY_PRINT);
                    Log::channel('import')->info($infoSetting . " WITH FILES EMPTY");
                } else {
                    $queue = new ImportQueueManager();
                    foreach ($files as $file) {
                        $dbImporter = new DBImporterJob($setting, $file);
                        $queue->push($dbImporter);
                    }
                }
            }
        } catch (Exception $e) {
            Log::channel('import')->error($e);
        }
    }
}
