<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs\Delivery;


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

    public function delivery()
    {
        // TODO: Implement delivery() method.
//        Log::info(json_encode($this->setting, JSON_PRETTY_PRINT));
        $delivery_source = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['TempPath'];
        $delivery_destination = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['FilePath'];
        $filePattern = $this->setting[self::CSV_OUTPUT_PROCESS_CONFIGURATION]['FileName'];
        Log::info("From: ". $delivery_source);
        Log::info("To  : ". $delivery_destination);

        $source_files = scandir($delivery_source);
        foreach($source_files as $source_file){
            if ($this->isMatchedWithPattern($source_file, $filePattern)){
                Log::info("move or copy: ".$source_file);
                if (!file_exists($delivery_destination)) {
                    mkdir($delivery_destination, 0777, true);
                }
                File::copy($delivery_source.'/'.$source_file, $delivery_destination.'/'.$source_file);
            }
        }
    }

    private function isMatchedWithPattern($fileName, $pattern){
        if($fileName[0]=='.')
            return false;
        else
            return true;
    }
}