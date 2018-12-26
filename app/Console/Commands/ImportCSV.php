<?php

namespace App\Console\Commands;

use App\Jobs\DBImporterJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportCSV extends Command
{
    public const CONFIGURATION = 'CSV Import Process Basic Configuration';
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
            Log::error('Can not run import schedule, getting error from config ini files');
        }
        return null;
    }

    private function importDataForTimeExecution($dataSchedule)
    {
        try {
            foreach ($dataSchedule as $data) {
                $setting = $data['setting'];
                $files = $data['files'];

                if (!is_dir($setting[self::CONFIGURATION]['FilePath'])) {
                    Log::error(
                        "ImportTable: {$setting[self::CONFIGURATION]['ImportTable']}
                        FilePath: {$setting[self::CONFIGURATION]['FilePath']} is not available"
                    );
                    break;
                }

                if (empty($files)) {
                    $infoSetting = json_encode($setting[self::CONFIGURATION], JSON_PRETTY_PRINT);
                    Log::info($infoSetting . " WITH FILES EMPTY");
                } else {
                    $queue = new ImportQueueManager();
                    foreach ($files as $file) {
                        $dbImporter = new DBImporterJob($setting, $file);
                        $queue->push($dbImporter);
                    }
                }
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
