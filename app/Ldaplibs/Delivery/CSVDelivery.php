<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs\Delivery;

use App\Http\Models\DeliveryHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CSVDelivery implements DataDelivery
{
    protected $setting;

    const CSV_OUTPUT_PROCESS_CONFIGURATION = 'CSV Output Process Configuration';

    public function __construct($setting)
    {
        $this->setting = $setting;
    }

    public function format()
    {
        // TODO: Implement format() method.
    }

    /**
     * Process delivery
     *
     * @return void
     */
    public function delivery()
    {
        $deliverySource       = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['TempPath'];
        $deliveryDestination  = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['FilePath'];
        $filePattern          = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['FileName'];
        $outputType          = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['OutputType'];

        Log::info("From: " . $deliverySource);
        Log::info("To  : " . $deliveryDestination);

        $sourceFiles = scandir($deliverySource);

        foreach ($sourceFiles as $sourceFile) {
            if ($this->isMatchedWithPattern($sourceFile, $filePattern)) {
                $deliverySource = $deliverySource . '/' . $sourceFile;
                $deliveryTarget = $deliveryDestination . '/' . $sourceFile;

                // data delivery history
                $deliveryHistories = [
                    "output_type" => $outputType,
                    "delivery_source" => $deliverySource,
                    "file_size" => File::size($deliverySource), // byte
                    'execution_at' => Carbon::now()->format('Y/m/d h:i'),
                    'status' => 1 // success
                ];

                Log::info("move or copy: " . $sourceFile);
                mkDirectory($deliveryDestination);

                if (file_exists($deliveryTarget)) {
                    $newFileName = Carbon::now() ->format('Ymd') . rand(100, 999);
                    $deliveryTarget = $this->removeExt($sourceFile) . '_' . $newFileName . '.csv';
                    $deliveryTarget = $deliveryDestination . '/' . $deliveryTarget;
                }

                // move file
                File::move($deliverySource, $deliveryTarget);

                // save history
                $deliveryHistories['delivery_target'] = $deliveryTarget;
                $rowsColumn = $this->rowsColumnCSV($deliveryTarget);
                $deliveryHistories['rows_count'] = $rowsColumn;
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
        if ($fileName[0] === '.') {
            return false;
        }
        return true;
    }

    /**
     * @param $file_name
     * @return string|string[]|null
     */
    public function removeExt($file_name)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
        return $file;
    }

    /**
     * Save history delivery
     * @param array $historyData
     */
    public function saveToHistory(array $historyData)
    {
        DeliveryHistory::create($historyData);
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

    /**
     * Get rows column csv file
     *
     * @param \phpDocumentor\Reflection\File $fileCSV
     *
     * @return int rowsColumn
     */
    private function rowsColumnCSV($fileCSV)
    {
        $rowsColumn = 0;
        if (file_exists($fileCSV)) {
            $data = [];
            foreach (file($fileCSV) as $line) {
                $dataLine = str_getcsv($line);
                array_push($data, $dataLine);
            }

            $rowsColumn = count($data);
        }

        return $rowsColumn;
    }
}
