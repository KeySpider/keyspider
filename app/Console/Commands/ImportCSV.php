<?php
/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

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
    }
}
