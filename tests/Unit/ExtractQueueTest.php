<?php

namespace Tests\Unit;

use function App\Console\Commands\array2string;
use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\ImportQueueManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Exception;

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

/*
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
}*/

class ExtractQueueTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {

        $extractSetting = $this->setDataToDoTest();
        print_r("Do testing for extract");
        var_dump($extractSetting);
        foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
            $this->exportDataForTimeExecution($settingOfTimeExecution);
        }

        sleep(2);
        $unittestExportDir = '/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/export/';
        $hogehogeU_time = filemtime('' . $unittestExportDir . 'hogehogeU.csv');
        $hogehogeO_time = filemtime('/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/export/hogehogeO.csv');
        $this->assertTrue($hogehogeU_time<=$hogehogeO_time);
    }

    /**
     * @return array
     */
    private function setDataToDoTest(): array
    {
        $array = array();
        $array['00:00']['0']['setting']['Extraction Process Basic Configuration']['OutputType'] = "CSV";
        $array['00:00']['0']['setting']['Extraction Process Basic Configuration']['ExtractionTable'] = "User";
        $array['00:00']['0']['setting']['Extraction Process Basic Configuration']['ExecutionTime']['0'] = "00:00";
        $array['00:00']['0']['setting']['Extraction Condition']['AAA.002'] = "TODAY() + 7";
        $array['00:00']['0']['setting']['Extraction Condition']['AAA.015'] = "0";
        $array['00:00']['0']['setting']['Extraction Process Format Conversion']['1'] = "(AAA.009,\\w,\\u)";
        $array['00:00']['0']['setting']['Extraction Process Format Conversion']['2'] = "(AAA.003)";
        $array['00:00']['0']['setting']['Extraction Process Format Conversion']['3'] = "(AAA.008)";
        $array['00:00']['0']['setting']['Extraction Process Format Conversion']['4'] = "(AAA.004 -> BBB.004)";
        $array['00:00']['0']['setting']['Extraction Process Format Conversion']['5'] = "(AAA.005 -> CCC.003)";
        $array['00:00']['0']['setting']['Output Process Conversion']['output_conversion'] = "/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/export/ini/UserInfoOutput4CSV.ini";
        $array['14:18']['0']['setting']['Extraction Process Basic Configuration']['OutputType'] = "CSV";
        $array['14:18']['0']['setting']['Extraction Process Basic Configuration']['ExtractionTable'] = "Organization";
        $array['14:18']['0']['setting']['Extraction Process Basic Configuration']['ExecutionTime']['0'] = "14:18";
        $array['14:18']['0']['setting']['Extraction Condition']['BBB.002'] = "TODAY() + 7";
        $array['14:18']['0']['setting']['Extraction Condition']['BBB.012'] = "0";
        $array['14:18']['0']['setting']['Extraction Process Format Conversion']['1'] = "(BBB.001)";
        $array['14:18']['0']['setting']['Extraction Process Format Conversion']['2'] = "(BBB.004)";
        $array['14:18']['0']['setting']['Output Process Conversion']['output_conversion'] = "/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/export/ini/OrganizationInfoOutput4CSV.ini";
//        $array['15:51']['0']['setting']['Extraction Process Basic Configuration']['OutputType'] = "CSV";
//        $array['15:51']['0']['setting']['Extraction Process Basic Configuration']['ExtractionTable'] = "Role";
//        $array['15:51']['0']['setting']['Extraction Process Basic Configuration']['ExecutionTime']['0'] = "15:51";
//        $array['15:51']['0']['setting']['Extraction Condition']['CCC.002'] = "TODAY() + 7";
//        $array['15:51']['0']['setting']['Extraction Condition']['CCC.011'] = "0";
//        $array['15:51']['0']['setting']['Extraction Process Format Conversion']['1'] = "(CCC.001)";
//        $array['15:51']['0']['setting']['Extraction Process Format Conversion']['2'] = "(CCC.003)";
//        $array['15:51']['0']['setting']['Output Process Conversion']['output_conversion'] = "/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/export/ini/RoleInfoOutput4CSV.ini";

        return ($array);
    }

    public function exportDataForTimeExecution($settings)
    {
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::channel('export')->error($e);
        }
    }

}
