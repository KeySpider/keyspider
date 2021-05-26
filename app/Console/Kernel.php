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

namespace App\Console;

use App\Jobs\DBExtractorJob;
use App\Jobs\DBImporterJob;
use App\Jobs\DBToADExtractorJob;
use App\Jobs\DBToGWExtractorJob;
use App\Jobs\DBToOLExtractorJob;
use App\Jobs\DBToTLExtractorJob;
use App\Jobs\DBToBoxExtractorJob;
use App\Jobs\DBToSFExtractorJob;
use App\Jobs\DBToZoomExtractorJob;
use App\Jobs\DBToSlacKExtractorJob;
use App\Jobs\DBToRDBExtractorJob;
use App\Jobs\DeliveryJob;
use App\Jobs\DBImporterFromRDBJob;
use App\Jobs\LDAPExportorJob;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use App\Ldaplibs\Extract\ExtractQueueManager;
use App\Ldaplibs\Extract\ExtractSettingsManager;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\Import\RDBImportSettingsManager;
use App\Ldaplibs\Export\ExportLDAPSettingsManager;
use App\Ldaplibs\SettingsManager;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    public const MASTER_DB_CONFIG = 'Master DB Configurtion';
    public const IMPORT_CSV_CONFIG = 'CSV Import Process Configration';
    public const CONFIGURATION = 'CSV Import Process Basic Configration';
    public const IMPORT_SCIM_CONFIG = 'SCIM Input Process Configration';
    public const EXPORT_CSV_CONFIG = 'CSV Extract Process Configration';
    public const EXPORT_AD_CONFIG = 'Azure Extract Process Configration';
    public const EXPORT_SF_CONFIG = 'SF Extract Process Configration';
    public const EXPORT_GW_CONFIG = 'GW Extract Process Configration';
    public const EXPORT_OL_CONFIG = 'OL Extract Process Configration';
    public const EXPORT_BOX_CONFIG = 'BOX Extract Process Configration';
    public const EXPORT_TL_CONFIG = 'TL Extract Process Configration';
    public const EXPORT_ZOOM_CONFIG = 'ZOOM Extract Process Configration';
    public const EXPORT_SLACK_CONFIG = 'SLACK Extract Process Configration';
    public const IMPORT_RDB_CONFIG = 'RDB Import Process Configration';
    public const EXPORT_RDB_CONFIG = 'RDB Extract Process Configration';
    public const DATABASE_INFO = 'RDB Import Database Configuration';
    public const EXPORT_LDAP_CONFIG = 'LDAP Export Process Configration';
    public const DELIVERY_CSV_CONFIG = 'CSV Output Process Configration';


    // [Master DB Configurtion] と [SCIM Input Process Configration] は 実行時間を定義しないため voidFunction() を実行します。
    public const OPERATIONS = array(
        self::MASTER_DB_CONFIG => 'voidFunction',
        self::IMPORT_CSV_CONFIG => 'importDataFromCsv',
        self::IMPORT_SCIM_CONFIG => 'voidFunction',
        self::IMPORT_RDB_CONFIG => 'importDataFromRdb',
        self::EXPORT_CSV_CONFIG => 'exportDataToCsv',
        self::EXPORT_AD_CONFIG => 'exportDataToAD',
        self::EXPORT_SF_CONFIG => 'exportDataToSF',
        self::EXPORT_TL_CONFIG => 'exportDataToTL',
        self::EXPORT_BOX_CONFIG => 'exportDataToBox',
        self::EXPORT_ZOOM_CONFIG => 'exportDataToZoom',
        self::EXPORT_GW_CONFIG => 'exportDataToGW',
        self::EXPORT_SLACK_CONFIG => 'exportDataToSlack',
        self::EXPORT_OL_CONFIG => 'exportDataToOL',
        self::EXPORT_RDB_CONFIG => 'exportDataToRDB',
        self::DELIVERY_CSV_CONFIG => 'deliveryCsv',
        self::EXPORT_LDAP_CONFIG => 'exportDataToLdap'
    );

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     * keyspider.ini に設定したセクション名を取得し、セクション名に紐づく処理を実行します。
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // keyspider.ini の設定内容を全て取得します。
        $keyspiderIni = (new SettingsManager())->getAllConfigsFromKeyspiderIni();
        $sections = array_keys($keyspiderIni);
        foreach ($sections as $section) {
            try {
                $function = self::OPERATIONS[$section];
                $this->$function($schedule);
            } catch (Exception $exception) {
                // セクション名が間違っている場合はログを出力して後続処理をおこないます。
                Log::error("There is a mistake in [$section] of keyspider.ini.");
            }
        }
    }

    /**
     * 実行時間を定義しないセクションのための処理です。
     */
    public function voidFunction(Schedule $schedule)
    {
    }

    /**
     * CSV インポート用の実行時間チェック処理です。
     */
    public function importDataFromCsv(Schedule $schedule)
    {
        $importSettingsManager = new ImportSettingsManager();
        $timeExecutionList = $importSettingsManager->getScheduleImportExecution();
        if (count($timeExecutionList) > 0) {
            foreach ($timeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->importDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no CSV file import to process.');
        }
    }

    /**
     * RDB インポート用の実行時間チェック処理です。
     */
    public function importDataFromRdb(Schedule $schedule)
    {
        $rdbImportSettingsManager = new RDBImportSettingsManager();
        $rdbTimeExecutionList = $rdbImportSettingsManager->getScheduleImportExecution();
        if (count($rdbTimeExecutionList) > 0) {
            foreach ($rdbTimeExecutionList as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->rdbImportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no RDB record import to process.');
        }
    }

    /**
     * CSV エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToCsv(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager();
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no CSV file extracting.');
        }
    }

    /**
     * Azure AD エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToAD(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_AD_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToADForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no Active Directory extracting.');
        }
    }

    /**
     * Salesforce エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToSF(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_SF_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToSFForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no Salesforce extracting.');
        }
    }

    /**
     * TrustLogin エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToTL(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_TL_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToTLForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no TrustLogin extracting.');
        }
    }

    /**
     * Box エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToBox(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_BOX_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToBoxForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no BOX extracting.');
        }
    }

    /**
     * Zoom エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToZoom(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_ZOOM_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToZoomForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no Zoom extracting.');
        }
    }

    /**
     * Google Workspace エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToGW(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_GW_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToGWForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no GoogleWorkspace extracting.');
        }
    }

    /**
     * Slack エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToSlack(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_SLACK_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToSlackForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no Slack extracting.');
        }
    }

    /**
     * One Login エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToOL(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_OL_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToOLForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no OneLogin extracting.');
        }
    }

    /**
     * RDB エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToRDB(Schedule $schedule)
    {
        $extractSettingManager = new ExtractSettingsManager(self::EXPORT_RDB_CONFIG);
        $extractSetting = $extractSettingManager->getRuleOfDataExtract();
        if ($extractSetting) {
            foreach ($extractSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->exportDataToRDBForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no RDB extracting.');
        }
    }

    /**
     * CSV アウトプット用の実行時間チェック処理です。
     */
    public function deliveryCsv(Schedule $schedule)
    {
        $scheduleDeliveryExecution = (new DeliverySettingsManager())->getScheduleDeliveryExecution();
        if ($scheduleDeliveryExecution) {
            foreach ($scheduleDeliveryExecution as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->deliveryDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no CSV file delivery.');
        }
    }

    /**
     * LDAP エクスポート用の実行時間チェック処理です。
     */
    public function exportDataToLdap(Schedule $schedule)
    {
        $exportSettingManager = new ExportLDAPSettingsManager();
        $exportSetting = $exportSettingManager->getRuleOfDataExport();
        if ($exportSetting) {
            foreach ($exportSetting as $timeExecutionString => $settingOfTimeExecution) {
                $schedule->call(function () use ($settingOfTimeExecution) {
                    $this->LDAPExportDataForTimeExecution($settingOfTimeExecution);
                })->dailyAt($timeExecutionString);
            }
        } else {
            Log::info('Currently, there is no LDAP export data.');
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }

    /**
     * @param $dataSchedule
     */
    public function importDataForTimeExecution($dataSchedule)
    {
        Log::info('importDataForTimeExecution');
        try {
            foreach ($dataSchedule as $data) {
                $setting = $data['setting'];
                $files = $data['files'];

                // queue
                $queue = new ImportQueueManager();
                foreach ($files as $file) {
                    $dbImporter = new DBImporterJob($setting, $file);
                    $queue->push($dbImporter);
                }
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param array $settings
     */
    public function exportDataForTimeExecution($settings)
    {
        Log::info('exportDataForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param $dataSchedule
     */
    public function rdbImportDataForTimeExecution($dataSchedule)
    {
        Log::info('rdbImportDataForTimeExecution');
        try {
            foreach ($dataSchedule as $data) {

                $setting = $data['setting'];

                $con = $setting[self::DATABASE_INFO]['Connection'];
                $sql = sprintf("select count(*) as cnt from %s", $setting[self::DATABASE_INFO]['ImportTable']);

                $rows = DB::connection($con)->select($sql);
                $array = json_decode(json_encode($rows), true);
                $cnt = (int)$array[0]['cnt'];

                if ($cnt > 0) {
                    $queue = new ImportQueueManager();
                    //echo ("- Import $cnt records\n");
                    $dbImporter = new DBImporterFromRDBJob($setting, $file = 'dummy');
                    $queue->push($dbImporter);
                };
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param array $settings
     */
    public function LDAPExportDataForTimeExecution($settings)
    {
        Log::info('LDAPExportDataForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $exportor = new LDAPExportorJob($setting);
                $queue->push($exportor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToADForTimeExecution($settings)
    {
        Log::info('exportDataToADForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToADExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToTLForTimeExecution($settings)
    {
        Log::info('exportDataToTLForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToTLExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToGWForTimeExecution($settings)
    {
        Log::info('exportDataToGWForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToGWExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToOLForTimeExecution($settings)
    {
        Log::info('exportDataToOLForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToOLExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToBoxForTimeExecution($settings)
    {
        Log::info('exportDataToBoxForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToBoxExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToSFForTimeExecution($settings)
    {
        Log::info('exportDataToSFForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToSFExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToZoomForTimeExecution($settings)
    {
        Log::info('exportDataToZoomForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToZoomExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToSlackForTimeExecution($settings)
    {
        Log::info('exportDataToSlackForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToSlackExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    public function exportDataToRDBForTimeExecution($settings)
    {
        Log::info('exportDataToRDBForTimeExecution');
        try {
            $queue = new ExtractQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $extractor = new DBToRDBExtractorJob($setting);
                $queue->push($extractor);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    /**
     * @param array $settings
     */
    public function deliveryDataForTimeExecution($settings)
    {
        Log::info('deliveryDataForTimeExecution');
        try {
            $queue = new DeliveryQueueManager();
            foreach ($settings as $dataSchedule) {
                $setting = $dataSchedule['setting'];
                $delivery = new DeliveryJob($setting);
                $queue->push($delivery);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
