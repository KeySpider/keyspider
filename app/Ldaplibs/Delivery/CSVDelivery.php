<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs\Delivery;


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
     * delivery
     */
    public function delivery()
    {
        $delivery_source      = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['TempPath'];
        $delivery_destination = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['FilePath'];
        $filePattern          = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['FileName'];

        Log::info("From: ". $delivery_source);
        Log::info("To  : ". $delivery_destination);

        $source_files = scandir($delivery_source);
        foreach($source_files as $source_file){
            if ($this->isMatchedWithPattern($source_file, $filePattern)){
                Log::info("move or copy: ".$source_file);
                if (!file_exists($delivery_destination)) {
                    mkdir($delivery_destination, 0777, true);
                }
                if (file_exists($delivery_destination.'/'.$source_file)) {
                    $fileName = $this->removeExt($source_file).'_'.Carbon::now()->format('Ymd').rand(100,999).'.csv';
                    File::move($delivery_source.'/'.$source_file, $delivery_destination.'/'.$fileName);
                } else {
                    File::move($delivery_source.'/'.$source_file, $delivery_destination.'/'.$source_file);
                }
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
        if($fileName[0]=='.') {
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
}
