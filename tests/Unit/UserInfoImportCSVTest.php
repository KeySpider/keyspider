<?php

namespace Tests\Unit;

use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DBImporter;
use App\Ldaplibs\SettingsManager;
use phpDocumentor\Reflection\Types\Self_;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use DB;
use Storage;
use File;

class UserInfoImportCSVTest extends TestCase
{
    use RefreshDatabase;

    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Basic Configuration";

    /**
     * test case normal
     */
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

        $filePath = $setting[self::CONFIGURATION]['FilePath'];

        if (!file_exists($filePath.'/hogehoge100.csv')) {
            File::copy(storage_path('tests/user/hogehoge100.csv'), $filePath.'/hogehoge100.csv');
        }

        // get data from one file
        $csvImportReader = new CSVReader(new SettingsManager());
        $dataCSVFile = $csvImportReader->getDataFromOneFile($files[0], [
            'CONVERSATION' => $setting[self::CONVERSION]
        ]);

        // process import
        $dbImporter = new DBImporter($setting, $files[0]);
        $dbImporter->import();

        // get all data in database
        $getAllDataUser = DB::select("select * from \"AAA\"");
        $dataInDB = json_decode(json_encode($getAllDataUser), true);

        // flag
        $flag = true;

        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach($dataCSVFile as $k => $v) {
            if ($v !== $dataInDB[$k]) {
                $flag = false;
            }
        }

        // move file
        if (!file_exists($filePath.'/hogehoge100.csv')) {
            File::copy(storage_path('tests/user/hogehoge100.csv'), $filePath.'/hogehoge100.csv');
        }

        // delete all file in import_csv_processed

        $this->assertTrue($flag);
    }

    /**
     * test case incorrect item csv
     * User.ID = (&,([A-Za-z0-9\._+]+)@(.*),$1)
     */
    public function testIncorrectItemCSV()
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
                    "AAA.001" => "(&,([A-Za-z0-9._+]+)@(.*),$1)",
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

        $filePath = $setting[self::CONFIGURATION]['FilePath'];

        if (!file_exists($filePath.'/hogehoge100.csv')) {
            File::copy(storage_path('tests/user/hogehoge100.csv'), $filePath.'/hogehoge100.csv');
        }

        // process import
        $dbImporter = new DBImporter($setting, $files[0]);
        $dbImporter->import();

        // get all data in database
        $getAllDataUser = DB::select("select * from \"AAA\"");
        $dataInDB = json_decode(json_encode($getAllDataUser), true);

        // flag
        $flag = true;

        foreach ($dataInDB as $column => $item) {
            if ($column === "AAA.001" && $item !== "") {
                $flag = false;
                break;
            }
        }

        if (!file_exists($filePath.'/hogehoge100.csv')) {
            File::copy(storage_path('tests/user/hogehoge100.csv'), $filePath.'/hogehoge100.csv');
        }

        // delete all file in import_csv_processed

        $this->assertTrue($flag);
    }

    /**
     * test incorrect item table
     * User.FamilyName = (3,(.+) (.+),$ABC)
     */
    public function testIncorrectItemTable()
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
                    "AAA.001" => "(&,([A-Za-z0-9._+]+)@(.*),$1)",
                    "AAA.002" => "(8,([0-9]{4})年([0-9]{2})月([0-9]{2})日,$1/$2/$3)",
                    "AAA.003" => "(2)",
                    "AAA.004" => "(4)",
                    "" => "mvfxvlvllvlvf",
                    "AAA.005" => "(5)",
                    "AAA.013" => "TODAY()",
                    "AAA.014" => "admin",
                    "AAA.015" => "0",
                    "AAA.006" => "(3,(.+) (.+), $1456)",
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

        $filePath = $setting[self::CONFIGURATION]['FilePath'];

        if (!file_exists($filePath.'/hogehoge100.csv')) {
            File::copy(storage_path('tests/user/hogehoge100.csv'), $filePath.'/hogehoge100.csv');
        }

        // process import
        $dbImporter = new DBImporter($setting, $files[0]);
        $dbImporter->import();

        // get all data in database
        $getAllDataUser = DB::select("select * from \"AAA\"");
        $dataInDB = json_decode(json_encode($getAllDataUser), true);

        // flag
        $flag = true;

        foreach ($dataInDB as $column => $item) {
            if ($column === "AAA.006" && $item !== "") {
                $flag = false;
                break;
            }
        }

        if (!file_exists($filePath.'/hogehoge100.csv')) {
            File::copy(storage_path('tests/user/hogehoge100.csv'), $filePath.'/hogehoge100.csv');
        }

        // delete all file in import_csv_processed

        $this->assertTrue($flag);
    }
}
