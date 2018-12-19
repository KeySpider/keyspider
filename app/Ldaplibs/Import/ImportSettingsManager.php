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
    private $iniImportSettingsFiles = array();
    private $iniImportSettingsFolder;
    private $allTableSettingsContent = null;
    const CSV_IMPORT_PROCESS_CONFIGRATION = 'CSV Import Process Configration';

    public function __construct($ini_settings_files = null)
    {
        parent::__construct($ini_settings_files);
        $this->iniImportSettingsFolder = '';

    }

    /**
     * @return array
     * Get rule of Import without ordering by time execution
     */
    private function getRuleOfImport()
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
            $tableNameInput = $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]["ImportTable"];
            $tableNameOutput = $master["$tableNameInput"]["$tableNameInput"];
            $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]["TableNameInDB"] = $tableNameOutput;

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

    /**
     * @return array
     * Get rule of Import order and group by Schedule
     */
    public function getScheduleImportExecution()
    {
        if($this->key_spider==null)
        {
            Log::error("Wrong key spider! Do nothing.");
            return[];
        }
        $allFiles = $this->key_spider[self::CSV_IMPORT_PROCESS_CONFIGRATION]['import_config'];
        foreach ($allFiles as $fileName) {
            $this->iniImportSettingsFiles[] = $fileName;
        }

        $rule = ($this->getRuleOfImport());

        $timeArray = array();
        foreach ($rule as $table_contents) {
            foreach ($table_contents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['ExecutionTime'] as $specify_time) {
                $filesArray = array();
                $filesArray['setting'] = $table_contents;
                $filesArray['files'] = $this->getFilesFromPattern($table_contents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['FilePath'],
                    $table_contents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['FileName']);
                $timeArray[$specify_time][] = $filesArray;
            }
        }
        ksort($timeArray);
        return $timeArray;
    }

    /**
     * @param $path <p>
     * Path of directory </p>
     * @param $pattern <p>
     * Pattern of file name </p>
     * @return array
     * <p>Array of file name matched pattern
     */
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
     * @return <p>array of key/value from ini file.</p>
     */
    private function getIniFileContent($filename)
    {
        try {
            $iniArray = parse_ini_file($filename, true);
            $isValid = $this->isImportIniValid($iniArray, $filename);
//            Log::info('validation result'.$isValid?'True':'False');
            return $isValid?$iniArray:null;
        } catch (\Exception $e) {
            Log::error(json_encode($e->getMessage(), JSON_PRETTY_PRINT));
            return null;
        }
    }

    /**
     * @return bool
     * <p>Check if all configure files are valid
     */
    private function areAllImportIniFilesValid(){
        foreach ($this->iniImportSettingsFiles as $ini_import_settings_file) {
            if(!$this->getIniFileContent($ini_import_settings_file))
                return false;
        }
        Log::info('areAllImportIniFilesValid: YES');
        return true;
    }

    /**
     * @return bool
     * <p>Check if a configure file are valid
     */

    private function isImportIniValid($iniArray, $fileName=null):bool {
        $rules = [
            self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION => 'required',
            self::CSV_IMPORT_PROCESS_FORMAT_CONVERSION => 'required'
        ];

        $validate = Validator::make($iniArray, $rules);
        if ($validate->fails()) {
            Log::error("Key error validation");
            Log::error("Error file: ".$fileName?$fileName:'');
            Log::error($validate->getMessageBag());
            return false;
        } else {
//                Validate children
            $tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION'] = $iniArray[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION];
            $tempIniArray['CSV_IMPORT_PROCESS_FORMAT_CONVERSION'] = $iniArray[self::CSV_IMPORT_PROCESS_FORMAT_CONVERSION];
            $rules = [
                'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.ImportTable' => 'required',
                'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.FilePath' => 'required',
                'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.FileName' => 'required',
                'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.ProcessedFilePath' => 'required',
            ];
            $validate = Validator::make($tempIniArray, $rules);
            if ($validate->fails()) {
                Log::error("Key error validation");
                Log::error("Error file: ".$fileName?$fileName:'');
                Log::error(json_encode($validate->getMessageBag(), JSON_PRETTY_PRINT));
                return false;
            } else {
                Log::info('Validation PASSED');
                return true;
            }
        }
    }

}
