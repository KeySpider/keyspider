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

use App\Commons\Consts;
use App\Jobs\DBImporterFromRDBJob;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\RDBImportSettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportRDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "command:rdb_import";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Reader setting import Oracle and process it";

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $keyspider = (new SettingsManager())->getAllConfigsFromKeyspiderIni();

        if (array_key_exists(Consts::RDB_IMPORT_PROCESS_CONFIGURATION, $keyspider)) {
            $importSettingsManager = new RDBImportSettingsManager();
            $timeExecutionList = $importSettingsManager->getScheduleImportExecution();
            if ($timeExecutionList) {
                (new \TablesBuilder($importSettingsManager))->buildTables();
                foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                    $this->importDataForTimeExecution($settingOfTimeExecution);
                }
            } else {
                echo ("\e[0;31;47mNothing to import!\e[0m\n");
                Log::error("Can not run import schedule, getting error from config ini files");
            }
        } else {
            Log::Info("nothing to do.");
        }
        return null;
    }

    private function importDataForTimeExecution($dataSchedule): void
    {
        foreach ($dataSchedule as $data) {
            $setting = $data["setting"];
            $con = $setting[Consts::IMPORT_PROCESS_DATABASE_CONFIGURATION]["Connection"];
            $sql = sprintf("select count(*) as cnt from %s", $setting[Consts::IMPORT_PROCESS_DATABASE_CONFIGURATION]["ImportTable"]);

            try {
                $rows = DB::connection($con)->select($sql);
                $array = json_decode(json_encode($rows), true);
                $cnt = (int)$array[0]["cnt"];

                if ($cnt > 0) {
                    $queue = new ImportQueueManager();
                    //echo ("- Import $cnt records\n");
                    $dbImporter = new DBImporterFromRDBJob($setting, $file = "dummy");
                    $queue->push($dbImporter);
                }
            } catch (Exception $e) {
                echo "Error : ",  $e->getMessage(), "\n";
                break;
            }
        }
    }
}
