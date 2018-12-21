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


class ExtractQueueTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $unittestExportDir = '/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/export/';
        array_map('unlink', glob($unittestExportDir.'*.*'));
        sleep(2);

        $extractSetting = $this->setDataToDoTest();
        print_r("Do testing for extract");
        var_dump($extractSetting);
        foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
            $this->exportDataForTimeExecution($settingOfTimeExecution);
        }

        sleep(2);

        $hogehogeU_time = filemtime('' . $unittestExportDir . 'hogehogeU.csv');
        $hogehogeO_time = filemtime('' . $unittestExportDir . 'hogehogeO.csv');

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
