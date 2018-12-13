<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs;



use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsManager
{
    const INI_CONFIGS = "ini_configs";
    public const EXTRACTION_CONDITION = "Extraction Condition";
    public const CSV_IMPORT_PROCESS_FORMAT_CONVERSION = "CSV Import Process Format Conversion";
    public const EXTRACTION_PROCESS_BASIC_CONFIGURATION = "Extraction Process Basic Configuration";
    public const CSV_IMPORT_PROCESS_BASIC_CONFIGURATION = "CSV Import Process Basic Configuration";
    public $iniMasterDBFile = null;
    public $masterDBConfigData = null;
    protected $key_spider;

    public function __construct($ini_settings_files = null)
    {
        if (!$this->validateKeySpider()){
            $this->key_spider = null;
        }else{
            $this->iniMasterDBFile = $this->key_spider['Master DB Configurtion']['master_db_config'];
            $this->masterDBConfigData = parse_ini_file($this->iniMasterDBFile, true);
        }
    }

    protected function removeExt($file_name)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
        return $file;
    }


    protected function contains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    public function validateKeySpider(){
        try{
            $this->key_spider = parse_ini_file(storage_path("" . self::INI_CONFIGS . "/KeySpider.ini"), true);
//            Log::info(json_encode($this->key_spider, JSON_PRETTY_PRINT));
            $validate = Validator::make($this->key_spider, [
                'Master DB Configurtion'=>'required',
                'CSV Import Process Configration'=>'required'
            ]);
            if($validate->fails()){
                Log::error('Key spider INI is not correct!');
                Log::error($validate->getMessageBag());
                return false;
            }else{
                return true;
            }
        }
        catch (\Exception $e){
            Log::error('Key spider INI is not correct!');
            return false;
        }
    }
}
