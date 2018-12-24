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
        $output_type          = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['OutputType'];

        Log::info("From: ". $deliverySource);
        Log::info("To  : ". $deliveryDestination);

        $source_files = scandir($deliverySource);

        foreach ($source_files as $source_file) {
            if ($this->isMatchedWithPattern($source_file, $filePattern)) {
                $delivery_source = $deliverySource.'/'.$source_file;
                $delivery_target = $deliveryDestination.'/'.$source_file;

                // data delivery history
                $delivery_histories = [
                    "output_type" => $output_type,
                    "delivery_source" => $delivery_source,
                    "file_size" => File::size($delivery_source), // byte
                    'execution_at' => Carbon::now()->format('Y/m/d h:i'),
                    'status' => 1 // success
                ];

                Log::info("move or copy: ".$source_file);
                if (!file_exists($deliveryDestination)) {
                    mkdir($deliveryDestination, 0777, true);
                }

                if (file_exists($delivery_target)) {
                    $newFileName = Carbon::now() ->format('Ymd').rand(100, 999);
                    $delivery_target = $this->removeExt($source_file).'_'.$newFileName.'.csv';
                    $delivery_target = $deliveryDestination.'/'.$delivery_target;
                }

                // move file
                File::move($delivery_source, $delivery_target);

                // save history
                $delivery_histories['delivery_target'] = $delivery_target;
                $rowsColumn = $this->rowsColumnCSV($delivery_target);
                $delivery_histories['rows_count'] = $rowsColumn;
                $this->saveToHistory($this->buildHistoryData($delivery_histories));
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
                $data_line = str_getcsv($line);
                array_push($data, $data_line);
            }

            $rowsColumn = count($data);
        }

        return $rowsColumn;
    }
}
