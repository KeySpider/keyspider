<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs;



class SettingsManager
{
    const INI_CONFIGS = "ini_configs";
    public const EXTRACTION_CONDITION = "Extraction Condition";
    public const CSV_IMPORT_PROCESS_FORMAT_CONVERSION = "CSV Import Process Format Conversion";
    public const EXTRACTION_PROCESS_BACIC_CONFIGURATION = "Extraction Process Bacic Configuration";
    public const CSV_IMPORT_PROCESS_BACIC_CONFIGURATION = "CSV Import Process Bacic Configuration";
    public $iniMasterDBFile = null;
    public $masterDBConfigData = null;

    public function __construct($ini_settings_files = null)
    {
        $this->iniMasterDBFile = storage_path("" . self::INI_CONFIGS . "/MasterDBConf.ini");
        $this->masterDBConfigData = parse_ini_file($this->iniMasterDBFile, true);
/*        $this->iniImportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/import/");
        $this->iniExportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
        $allFiles = scandir($this->iniImportSettingsFolder);
        foreach ($allFiles as $fileName) {
            if (contains('.ini', $fileName)) {
                if (contains('Master', $fileName)) {
                    $this->iniMasterDBFile = $fileName;
                } else {
                    $this->iniImportSettingsFiles[] = $fileName;
                }
            }
        }

        $allFiles = scandir($this->iniExportSettingsFolder);
        foreach ($allFiles as $fileName) {
            if (contains('.ini', $fileName) && contains('Extraction', $fileName)) {
                $this->iniExportSettingsFiles[] = $fileName;
            }
        }*/

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

}
