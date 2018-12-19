<?php

namespace Tests\Unit;

use App\Jobs\DBImporterJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserInfoImportCSVTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testNormalUserInfoImportCSV()
    {
        $originalString = [
            "setting" => [
                "CSV Import Process Basic Configuration" => [
                    "ImportTable" => "User",
                    "FilePath" => storage_path("import_csv/user"),
                    "FileName" => "hogehoge[0-9]{3}.csv",
                    "ProcessedFilePath" => storage_path("import_csv_processed/user"),
                    "ExecutionTime" => ["00:00"],
                    "TableNameInDB" => "AAA"
                ],
                "CSV Import Process Format Conversion" => [
                    "#User.hogehogi" => "",
                    "AAA.001" => "(1,([A-Za-z0-9._+]+)@(.*),$1)",
                    "AAA.002" => "(8,([0-9]{4})年([0-9]{2})月([0-9]{2})日,$1/$2/$3)",
                    "AAA.003" => "(2)",
                    "AAA.004" => "(4)",
                    "" => "mvfxvlvllvlvf",
                    "AAA.005" => "(5)",
                    "AAA.013" => "TODAY()",
                    "AAA.014" => "admin",
                    "AAA.015" => "0",
                    "AAA.006" => "(3,(.+) (.+),$1)",
                    "AAA.007" => "(3,(.+) (.+),$2)",
                    "AAA.008" => "(3,\s,)",
                    "AAA.009" => "(1,\w,\l)",
                    "AAA.010" => "(6)",
                    "AAA.011" => "(7)",
                ],
                "IniFileName" => storage_path("import/UserInfoCSVImport.ini"),
            ],
            "files" => [
                storage_path("import_csv/user/hogehoge100.csv")
            ]
        ];

        $setting = $originalString['setting'];
        $files = $originalString['files'];

        $queue = new ImportQueueManager();
        foreach ($files as $file) {
            $dbImporter = new DBImporterJob($setting, $file);
            $queue->push($dbImporter);
        }

        $this->assertTrue(true);
    }
}
