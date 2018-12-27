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

namespace Tests\Unit;

use App\Jobs\DBExtractorJob;
use App\Ldaplibs\Extract\ExtractQueueManager;
use Tests\TestCase;
use function App\Console\Commands\array2string;

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
        array_map('unlink', glob($unittestExportDir . '*.*'));
        sleep(1);

        $extractSetting = $this->setDataToDoTest();
        foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
            $this->exportDataForTimeExecution($settingOfTimeExecution);
        }
        sleep(1);
        $hogehogeU_time = filemtime('' . $unittestExportDir . 'hogehogeU.csv');
        $hogehogeO_time = filemtime('' . $unittestExportDir . 'hogehogeO.csv');

        $this->assertTrue($hogehogeU_time <= $hogehogeO_time);
    }

    /**
     * @return array
     */
    private function setDataToDoTest(): array
    {
        $json_delivery_schedule = '{
    "00:00": [
        {
            "setting": {
                "Extraction Process Basic Configuration": {
                    "OutputType": "CSV",
                    "ExtractionTable": "User",
                    "ExecutionTime": [
                        "00:00"
                    ]
                },
                "Extraction Condition": {
                    "AAA.002": "TODAY() + 7",
                    "AAA.015": "0"
                },
                "Extraction Process Format Conversion": {
                    "1": "(AAA.009, w,u)",
                    "2": "(AAA.003)",
                    "3": "(AAA.008)",
                    "4": "(AAA.004 -> BBB.004)",
                    "5": "(AAA.005 -> CCC.003)"
                },
                "Output Process Conversion": {
                    "output_conversion": "\/Applications\/MAMP\/htdocs\/LDAP_ID\/storage\/unittest\/export\/ini\/UserInfoOutput4CSV.ini"
                }
            }
        }
    ],
    "14:18": [
        {
            "setting": {
                "Extraction Process Basic Configuration": {
                    "OutputType": "CSV",
                    "ExtractionTable": "Organization",
                    "ExecutionTime": [
                        "14:18"
                    ]
                },
                "Extraction Condition": {
                    "BBB.002": "TODAY() + 7",
                    "BBB.012": "0"
                },
                "Extraction Process Format Conversion": {
                    "1": "(BBB.001)",
                    "2": "(BBB.004)"
                },
                "Output Process Conversion": {
                    "output_conversion": "\/Applications\/MAMP\/htdocs\/LDAP_ID\/storage\/unittest\/export\/ini\/OrganizationInfoOutput4CSV.ini"
                }
            }
        }
    ]
}';
        return json_decode($json_delivery_schedule, true);
    }

    public function exportDataForTimeExecution($settings)
    {
        $queue = new ExtractQueueManager();
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $extractor = new DBExtractorJob($setting);
            $queue->push($extractor);
        }
    }
}
