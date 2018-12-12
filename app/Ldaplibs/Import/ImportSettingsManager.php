<?php
/**
 * Created by PhpStorm.
 * User: anhtuan
 * Date: 12/11/18
 * Time: 9:16 AM
 */

namespace App\Ldaplibs\Import;


use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportSettingsManager extends SettingsManager
{
    protected $iniExportSettingsFolder;
    protected $iniExportSettingsFiles = array();
    private $iniImportSettingsFiles = array();
    private $iniImportSettingsFolder;
    private $allTableSettingsContent = null;
    const CSV_IMPORT_PROCESS_CONFIGRATION = 'CSV Import Process Configration';

    public function __construct($ini_settings_files = null)
    {
//        Log::info("ImportSettingsManager");
        parent::__construct($ini_settings_files);
//        $this->iniImportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/import/");
        $this->iniImportSettingsFolder = '';
        $allFiles = $this->key_spider[self::CSV_IMPORT_PROCESS_CONFIGRATION]['import_config'];
//        var_dump($allFiles);

//        $allFiles = scandir($this->iniImportSettingsFolder);

        foreach ($allFiles as $fileName) {
            $this->iniImportSettingsFiles[] = $fileName;
        }

    }

    /**
     * @return array
     */
    public function getRuleOfImport()
    {
        if ($this->allTableSettingsContent) {
            return $this->allTableSettingsContent;
        }

        $master = $this->masterDBConfigData;

        $this->allTableSettingsContent = array();

        if(!$this->areAllImportIniFilesValid())
            return [];

        foreach ($this->iniImportSettingsFiles as $ini_import_settings_file) {
            $tableContents = parse_ini_file($ini_import_settings_file, true);
            if ($tableContents == null) {
                Log::error("Can not run import schedule");
                return [];
            }
//            set filename in json file
            $tableContents['IniFileName'] = $ini_import_settings_file;
//            Set destination table in database
            $tableNameInput = $tableContents[self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION]["ImportTable"];
            $tableNameOutput = $master["$tableNameInput"]["$tableNameInput"];
            $tableContents[self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION]["TableNameInDB"] = $tableNameOutput;

            $masterDBConversion = $master[$tableNameInput];

//            Column conversion
            $columnNameConversion = $tableContents[SettingsManager::CSV_IMPORT_PROCESS_FORMAT_CONVERSION];
            foreach ($columnNameConversion as $key => $value)
                if (isset($masterDBConversion[$key])) {
                    $columnNameConversion[$masterDBConversion[$key]] = $value;
                    unset($columnNameConversion[$key]);
                }
            $tableContents[SettingsManager::CSV_IMPORT_PROCESS_FORMAT_CONVERSION] = $columnNameConversion;

            $this->allTableSettingsContent[] = $tableContents;
        }
        return $this->allTableSettingsContent;
    }

    public function getScheduleImportExecution()
    {
        $rule = ($this->getRuleOfImport());

        $timeArray = array();
        foreach ($rule as $table_contents) {
            foreach ($table_contents[self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION]['ExecutionTime'] as $specify_time) {
                $filesArray = array();
                $filesArray['setting'] = $table_contents;
                $filesArray['files'] = $this->getFilesFromPattern($table_contents[self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION]['FilePath'],
                    $table_contents[self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION]['FileName']);
                $timeArray[$specify_time][] = $filesArray;
            }
        }
        ksort($timeArray);
        return $timeArray;
    }

    private function getFilesFromPattern($path, $pattern)
    {
        $data = [];

        $validateFile = ['csv'];

        if (is_dir($path)) {
            foreach (scandir($path) as $key => $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($ext, $validateFile)) {
                    if (preg_match("/{$this->removeExt($pattern)}/", $this->removeExt($file))) {
                        array_push($data, "{$path}/{$file}");
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @return array of key/value from ini file.
     */
    private function getIniFileContent($filename)
    {
        try {
            $iniPath = $filename;
            $iniArray = parse_ini_file($iniPath, true);
            $isValid = $this->isImportIniValid($iniArray);
//            Log::info('validation result'.$isValid?'True':'False');
            return $isValid?$iniArray:null;
        } catch (\Exception $e) {
            Log::error(json_encode($e->getMessage(), JSON_PRETTY_PRINT));
            return null;
        }
    }

    private function areAllImportIniFilesValid(){
        foreach ($this->iniImportSettingsFiles as $ini_import_settings_file) {
            if(!$this->getIniFileContent($ini_import_settings_file))
                return false;
        }
        Log::info('areAllImportIniFilesValid: YES');
        return true;
    }
    private function isImportIniValid($iniArray):bool {
        $rules = [
            self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION => 'required',
            self::CSV_IMPORT_PROCESS_FORMAT_CONVERSION => 'required'
        ];

        $validate = Validator::make($iniArray, $rules);
        if ($validate->fails()) {
            Log::error("Key error validation");
            Log::error($validate->getMessageBag());
            return false;
        } else {
//                Validate children
            $tempIniArray['CSV_IMPORT_PROCESS_BACIC_CONFIGURATION'] = $iniArray[self::CSV_IMPORT_PROCESS_BACIC_CONFIGURATION];
            $tempIniArray['CSV_IMPORT_PROCESS_FORMAT_CONVERSION'] = $iniArray[self::CSV_IMPORT_PROCESS_FORMAT_CONVERSION];
            $rules = [
                'CSV_IMPORT_PROCESS_BACIC_CONFIGURATION.ImportTable' => 'required',
                'CSV_IMPORT_PROCESS_BACIC_CONFIGURATION.FilePath' => 'required',
                'CSV_IMPORT_PROCESS_BACIC_CONFIGURATION.FileName' => 'required',
                'CSV_IMPORT_PROCESS_BACIC_CONFIGURATION.ProcessedFilePath' => 'required',
            ];
            $validate = Validator::make($tempIniArray, $rules);
            if ($validate->fails()) {
                Log::error("Key error validation");
                Log::error(json_encode($validate->getMessageBag(), JSON_PRETTY_PRINT));
                return false;
            } else {
                Log::info('Validation PASSED');
                return true;
            }
        }
    }

    private function getIniExportFileContent($filename): array
    {
        $iniPath = $this->iniExportSettingsFolder . $filename;
        $iniArray = parse_ini_file($iniPath, true);
        return $iniArray;
    }

    public function getIniOutputContent($file_name)
    {
        return $this->getIniExportFileContent($file_name);
    }
}
