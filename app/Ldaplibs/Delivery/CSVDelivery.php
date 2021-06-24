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
use App\Http\Models\DeliveryHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CSVDelivery implements DataDelivery
{
    protected $setting;
    protected $deliveryHistoryModel;

    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->deliveryHistoryModel = new DeliveryHistory();
    }

    public function format()
    {
        // TODO: Implement format() method.
    }

    /**
     * Process delivery
     *
     * @return void
     * @throws \Exception
     */
    public function delivery()
    {
        $deliverySource = $this->setting[Consts::CSV_OUTPUT_PROCESS_CONFIGURATION][Consts::TEMP_PATH];
        $deliveryDestination = $this->setting[Consts::CSV_OUTPUT_PROCESS_CONFIGURATION][Consts::FILE_PATH];
        $filePattern = $this->setting[Consts::CSV_OUTPUT_PROCESS_CONFIGURATION][Consts::FILE_NAME];
        $outputType = $this->setting[Consts::CSV_OUTPUT_PROCESS_CONFIGURATION][Consts::OUTPUT_TYPE];

        Log::info("From: " . $deliverySource);
        Log::info("To  : " . $deliveryDestination);

        if (!is_dir($deliverySource)) {
            return;
        }

        $sourceFiles = scandir($deliverySource);

        foreach ($sourceFiles as $sourceFile) {
            if ($this->isMatchedWithPattern($sourceFile, $filePattern)) {
                $deliverySourcePath = $deliverySource . "/" . $sourceFile;
                $deliveryTarget = $deliveryDestination . "/" . $sourceFile;

                $sizeOfSourceFile = File::size($deliverySourcePath);
                // data delivery history
                Log::info("Delivery file: " . $sourceFile);
                $deliveryHistories = [
                    "output_type" => $outputType,
                    "delivery_source" => $deliverySourcePath,
                    "file_size" => (string)$sizeOfSourceFile, // byte
                    "execution_at" => Carbon::now()->format("Y/m/d h:i"),
                    "status" => 1 // success
                ];

                mkDirectory($deliveryDestination);

                if (file_exists($deliveryTarget)) {
                    $newFileName = Carbon::now()->format("Ymd") . random_int(100, 999);
                    $deliveryTarget = $this->removeExt($sourceFile) . "_" . $newFileName . ".csv";
                    $deliveryTarget = $deliveryDestination . "/" . $deliveryTarget;
                }

                // move file
                File::move($deliverySourcePath, $deliveryTarget);

                // save history
                $deliveryHistories["delivery_target"] = $deliveryTarget;
                $rowsColumn = $this->rowsColumnCSV($deliveryTarget);
                $deliveryHistories["rows_count"] = $rowsColumn;
                $this->saveToHistory($this->buildHistoryData($deliveryHistories));
            }
        }
    }

    /**
     * @param $fileName
     * @param $pattern
     * @return bool
     */
    private function isMatchedWithPattern($fileName, $pattern)
    {
        return !($fileName[0] === ".");
    }

    /**
     * @param $file_name
     * @return string|string[]|null
     */
    public function removeExt($file_name)
    {
        return preg_replace("/\\.[^.\\s]{3,4}$/", "", $file_name);
    }

    /**
     * @param $fileCSV
     * @return int|void
     */
    private function rowsColumnCSV($fileCSV)
    {
        $rowsColumn = 0;
        if (file_exists($fileCSV)) {
            $data = [];
            foreach (file($fileCSV) as $line) {
                $dataLine = str_getcsv($line);
                $data[] = $dataLine;
            }
            $rowsColumn = count($data);
        }
        return $rowsColumn;
    }

    /**
     * Save history delivery
     * @param array $historyData
     */
    public function saveToHistory(array $historyData)
    {
        $this->deliveryHistoryModel->create($historyData);
    }

    /**
     * Get data delivery history
     * @param array $deliveryInformation
     * @return array
     */
    public function buildHistoryData(array $deliveryInformation): array
    {
        return $deliveryInformation;
    }
}
