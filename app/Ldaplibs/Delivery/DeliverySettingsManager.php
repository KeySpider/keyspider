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
