<?php

namespace Tests\Unit;

use App\Jobs\DBImporterJob;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\ImportQueueManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Copy a file, or recursively copy a folder and its contents
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.1
 * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
 * @param       string $source Source path
 * @param       string $dest Destination path
 * @param       int $permissions New folder creation permissions
 * @return      bool     Returns true on success, false on failure
 */
function xcopy($source, $dest, $permissions = 0755)
{
    // Check for symlinks
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }

    // Simple copy for a file
    if (is_file($source)) {
        return copy($source, $dest);
    }

    // Make destination directory
    if (!is_dir($dest)) {
        mkdir($dest, $permissions);
    }

    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        // Skip pointers
        if ($entry == '.' || $entry == '..') {
            continue;
        }

        // Deep copy directories
        xcopy("$source/$entry", "$dest/$entry", $permissions);
    }

    // Clean up
    $dir->close();
    return true;
}

class ImportQueueTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $originalString = $this->setDataToDoTest();

        $setting = $originalString['setting'];
        $files = $originalString['files'];
        $arrayOfFirstColumn = array();
        $queue = new ImportQueueManager();
        foreach ($files as $file) {
//            print_r('processing: ' . $file);
            $array = $this->getArrayOfFirstColumnFromCSVFile($file);
            $arrayOfFirstColumn = array_merge($arrayOfFirstColumn, $array);

            $dbImporter = new DBImporterJob($setting, $file);
            $queue->push($dbImporter);
        }

        $arrayOfFirstColumnInDB = $this->getArrayOfFirstColumnInDB();
//        var_dump($arrayOfFirstColumn);
//        var_dump($arrayOfFirstColumnInDB);
        $this->assertTrue($arrayOfFirstColumn== $arrayOfFirstColumnInDB);
    }

    /**
     * @return array
     */
    private function setDataToDoTest(): array
    {
        DB::statement('DELETE FROM "AAA";');
        xcopy(storage_path("unittest/user/store/"), storage_path("unittest/user/"));
        $originalString = [
            "setting" => [
                "CSV Import Process Basic Configuration" => [
                    "ImportTable" => "User",
                    "FilePath" => storage_path("unittest/user"),
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
                storage_path("unittest/user/hogehoge101.csv"),
                storage_path("unittest/user/hogehoge102.csv"),
//                storage_path("import_csv/user/hogehoge102.csv")
            ]
        ];
        return $originalString;
    }

    /**
     * @param $file
     * @return array
     */
    private function getArrayOfFirstColumnFromCSVFile($file): array
    {
        $array = array();
        $csv = array_map(function($v){return str_getcsv($v, ",");}, file($file));
        foreach ($csv as $row) {
//            var_dump($row);
            $array[] = explode('@', $row[0])[0];
        }
        return $array;
    }

    /**
     * @return array
     */
    private function getArrayOfFirstColumnInDB(): array
    {
        $arrayOfFirstColumnInDB = array();
        $sql = 'SELECT "AAA.001" FROM "AAA"';
        $result = DB::select($sql);
        $results = json_decode(json_encode($result), true);
        foreach ($results as $r) {
            $arrayOfFirstColumnInDB[] = $r['AAA.001'];
        }
        return $arrayOfFirstColumnInDB;
    }
}
