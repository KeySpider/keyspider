<?php
/**
 * Created by PhpStorm.
 * User: anhtuan
 * Date: 12/12/18
 * Time: 11:53 PM
 */

namespace App\Ldaplibs\Delivery;


use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;

class DeliverySettingsManager extends SettingsManager
{
    private $iniDeliverySettingsFolder;
    private $iniDeliverySettingsFiles;
    const CSV_OUTPUT_PROCESS_CONFIGURATION = 'CSV Output Process Configuration';

    public function __construct($ini_settings_files = null)
    {

        parent::__construct($ini_settings_files);
        $this->iniDeliverySettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
        $allFiles = scandir($this->iniDeliverySettingsFolder);
        foreach ($allFiles as $fileName) {
            if ($this->contains('.ini', $fileName) && $this->contains('Output', $fileName)) {
                $this->iniDeliverySettingsFiles[] = storage_path("" . self::INI_CONFIGS . "/extract/").$fileName;
            }
        }

    }

    public function getScheduleDeliveryExecution(){
        var_dump($this->iniDeliverySettingsFiles);
        $timeArray = array();
        if(true) {
            foreach ($this->iniDeliverySettingsFiles as $iniDeliverySettingsFile) {
                $tableContent = parse_ini_file($iniDeliverySettingsFile, true);
                foreach ($tableContent[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                    $filesArray['setting'] = $tableContent;
                    $timeArray[$specifyTime][] = $filesArray;
                }
            }
            ksort($timeArray);
            return $timeArray;
        }else{
            Log::info("Error in Extract INI file");
            return [];
        }
    }

}