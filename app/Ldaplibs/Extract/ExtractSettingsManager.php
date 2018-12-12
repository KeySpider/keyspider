<?php
/**
 * Created by PhpStorm.
 * User: anhtuan
 * Date: 12/11/18
 * Time: 10:12 AM
 */

namespace App\Ldaplibs\Extract;


use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExtractSettingsManager extends SettingsManager
{
    public const EXTRACTION_PROCESS_FORMAT_CONVERSION = "Extraction Process Format Conversion";
    protected $iniExportSettingsFolder;
    protected $iniExportSettingsFiles = array();

    const OUTPUT_PROCESS_CONVERSION = 'Output Process Conversion';

    public function __construct($ini_settings_files = null)
    {
        parent::__construct($ini_settings_files);
        $this->iniExportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
        $allFiles = scandir($this->iniExportSettingsFolder);
        foreach ($allFiles as $fileName) {
            if ($this->contains('.ini', $fileName) && $this->contains('Extraction', $fileName)) {
                $this->iniExportSettingsFiles[] = storage_path("" . self::INI_CONFIGS . "/extract/").$fileName;
            }
        }
    }

    public function getRuleOfDataExtract()
    {
        $timeArray = array();
        if($this->areAllExtractIniFilesValid()) {
            foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
                $tableContent = parse_ini_file($iniExportSettingsFile, true);
                $extract_table_name = $tableContent[SettingsManager::EXTRACTION_PROCESS_BACIC_CONFIGURATION]['ExtractionTable'];
                $masterDB = $this->masterDBConfigData;
                $tableContent = $this->convert_following_db_master($tableContent, self::EXTRACTION_CONDITION, $masterDB);
                $tableContent = $this->convertValueFromDBMaster($tableContent, $masterDB);
                foreach ($tableContent[self::EXTRACTION_PROCESS_BACIC_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                    $filesArray['setting'] = $tableContent;
                    $timeArray[$specifyTime][] = $filesArray;
                }
            }
            ksort($timeArray);
            return $timeArray;
        }else{
            Log::info("Error in Extract INI file");
            return [];
        }
    }

    private function getIniExportFileContent($filename): array
    {
        $iniPath = $this->iniExportSettingsFolder . $filename;
        $iniArray = parse_ini_file($iniPath, true);
        return $iniArray;
    }

    /**
     * @param $tableContents
     * @param $tagToConversion
     * @param $masterTable
     * @return mixed
     */
    private function convert_following_db_master($tableContents, $tagToConversion, $masterTable)
    {
        $columnNameConversion = $tableContents[$tagToConversion];
        foreach ($columnNameConversion as $key => $value)
            if (isset($masterTable[$key])) {
                $columnNameConversion[$masterTable[$key]] = $value;
                unset($columnNameConversion[$key]);
            }
        $tableContents[$tagToConversion] = $columnNameConversion;
        return $tableContents;
    }

    private function convertValueFromDBMaster($table_contents, $masterDB)
    {
        $jsonData = json_encode($table_contents);
        foreach ($masterDB as $table=>$masterTable) {
            foreach ($masterTable as $key => $value) {
                if (strpos($key, '.') !== false) {
                    $jsonData = str_replace($key, $value, $jsonData);
                }
            }
        }
        return (json_decode($jsonData, true));
    }

    public function getIniOutputContent($file_name)
    {
        return $this->getIniExportFileContent($file_name);
    }

    private function getIniFileContent($filename)
    {
        try {
            $iniArray = parse_ini_file($filename, true);
            $isValid = $this->isExtractIniValid($iniArray, $filename);
//            Log::info('validation result'.$isValid?'True':'False');
            return $isValid?$iniArray:null;
        } catch (\Exception $e) {
            Log::error(json_encode($e->getMessage(), JSON_PRETTY_PRINT));
            return null;
        }
    }

    private function areAllExtractIniFilesValid(){
        foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
            if(!$this->getIniFileContent($iniExportSettingsFile))
                return false;
        }
        Log::info('areAllExtractIniFilesValid: YES');
        return true;
    }
    private function isExtractIniValid($iniArray, $filename=null):bool {
        $rules = [
            self::EXTRACTION_PROCESS_BACIC_CONFIGURATION => 'required',
            self::EXTRACTION_CONDITION => 'required',
            self::EXTRACTION_PROCESS_FORMAT_CONVERSION => 'required',
            self::OUTPUT_PROCESS_CONVERSION => 'required'
        ];

        $validate = Validator::make($iniArray, $rules);
        if ($validate->fails()) {
            Log::error("Key error validation");
            Log::error("Error file: ".$filename?$filename:'');
            Log::error(json_encode($validate->getMessageBag(), JSON_PRETTY_PRINT));
            return false;
        } else {

//            Log::error(json_encode($iniArray, JSON_PRETTY_PRINT));
//                Validate children
            $tempIniArray = array();
            $tempIniArray['EXTRACTION_PROCESS_BACIC_CONFIGURATION'] = $iniArray[self::EXTRACTION_PROCESS_BACIC_CONFIGURATION];
            $tempIniArray['OUTPUT_PROCESS_CONVERSION'] = $iniArray[self::OUTPUT_PROCESS_CONVERSION];
            $rules = [
                'EXTRACTION_PROCESS_BACIC_CONFIGURATION.ExtractionTable' => 'required',
                'EXTRACTION_PROCESS_BACIC_CONFIGURATION.ExecutionTime' => 'required',
                'EXTRACTION_PROCESS_BACIC_CONFIGURATION.OutputType' => 'required',
                'OUTPUT_PROCESS_CONVERSION.output_conversion' => 'required'
            ];
            $validate = Validator::make($tempIniArray, $rules);
            if ($validate->fails()) {
                Log::error("Error file: ".$filename?$filename:'');
                Log::error(json_encode($validate->getMessageBag(), JSON_PRETTY_PRINT));
                return false;
            } else {
                if (file_exists($tempIniArray['OUTPUT_PROCESS_CONVERSION']['output_conversion'])){
                    Log::info('Validation PASSED');
                    return true;
                }else{
                    Log::error("Error file: ".$filename?$filename:'');
                    Log::error("The file is not existed: ".$tempIniArray['OUTPUT_PROCESS_CONVERSION']['output_conversion']);
                    return false;
                }

            }
        }
    }
/*
    public function getRuleOfDataExtract()
    {
        $timeArray = array();
        foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
            $tableContent = $this->getIniExportFileContent($iniExportSettingsFile);
            $extract_table_name = $tableContent[SettingsManager::EXTRACTION_PROCESS_BACIC_CONFIGURATION]['ExtractionTable'];
            $fileName = $this->iniMasterDBFile;
            $masterDB = $this->getIniImportFileContent($fileName);
            $masterTable = $masterDB[$extract_table_name];
            $tableContent = $this->convert_following_db_master($tableContent, self::EXTRACTION_CONDITION, $masterTable);
            $tableContent = $this->convertValueFromDBMaster($tableContent, $masterTable);
            foreach ($tableContent[self::EXTRACTION_PROCESS_BACIC_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                $filesArray['setting'] = $tableContent;
                $timeArray[$specifyTime][] = $filesArray;
            }
        }
        ksort($timeArray);
        return $timeArray;
    }*/
}