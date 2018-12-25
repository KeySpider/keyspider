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

    /**
     * define const
     */
    const CSV_OUTPUT_PROCESS_CONFIGURATION = 'CSV Output Process Configuration';

    /**
     * DeliverySettingsManager constructor.
     * @param null $iniSettingsFiles
     */
    public function __construct($iniSettingsFiles = null)
    {
        parent::__construct($iniSettingsFiles);
        $this->iniDeliverySettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
        $allFiles = scandir($this->iniDeliverySettingsFolder);
        foreach ($allFiles as $fileName) {
            if ($this->contains('.ini', $fileName) && $this->contains('Output', $fileName)) {
                $this->iniDeliverySettingsFiles[] = storage_path("" . self::INI_CONFIGS . "/extract/") . $fileName;
            }
        }
    }

    /**
     * Get schedule delivery execution
     * @return array
     */
    public function getScheduleDeliveryExecution()
    {
        $timeArray = [];
        if (true) {
            foreach ($this->iniDeliverySettingsFiles as $iniDeliverySettingsFile) {
                $tableContent = parse_ini_file($iniDeliverySettingsFile, true);
                foreach ($tableContent[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                    $filesArray['setting'] = $tableContent;
                    $timeArray[$specifyTime][] = $filesArray;
                }
            }
            ksort($timeArray);
            return $timeArray;
        } else {
            Log::info("Error in Extract INI file");
            return [];
        }
    }
}
