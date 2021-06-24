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

use App\Commons\Consts;
use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;

class DeliverySettingsManager extends SettingsManager
{
    private $iniDeliverySettingsFolder;
    private $iniDeliverySettingsFiles;

    public function __construct($iniSettingsFiles = null)
    {
        parent::__construct();
        $this->iniDeliverySettingsFiles = $this->keySpider[Consts::CSV_OUTPUT_PROCESS_CONFIGURATION][Consts::OUTPUT_CONFIG];
    }

    /**
     * Get schedule delivery execution
     * @return array
     */
    public function getScheduleDeliveryExecution(): ?array
    {
        $timeArray = [];
        if (true) {
            foreach ($this->iniDeliverySettingsFiles as $iniDeliverySettingsFile) {
                $tableContent = parse_ini_file($iniDeliverySettingsFile, true);
                foreach ($tableContent[Consts::CSV_OUTPUT_PROCESS_CONFIGURATION][Consts::EXECUTION_TIME] as $specifyTime) {
                    $filesArray["setting"] = $tableContent;
                    $timeArray[$specifyTime][] = $filesArray;
                }
            }
            ksort($timeArray);
            return $timeArray;
        }
        Log::error("Error in Extract INI file");
        return [];
    }
}
