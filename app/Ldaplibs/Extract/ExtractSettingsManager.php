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
    public const OUTPUT_PROCESS_CONVERSION = 'Output Process Conversion';

    protected $iniExportSettingsFolder;
    protected $iniExportSettingsFiles = [];

    /**
     * ExtractSettingsManager constructor.
     * @param null $iniSettingsFiles
     */
    public function __construct($iniSettingsFiles = null)
    {
        parent::__construct($iniSettingsFiles);
        $this->iniExportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
        $allFiles = scandir($this->iniExportSettingsFolder);
        foreach ($allFiles as $fileName) {
            if ($this->contains('.ini', $fileName) && $this->contains('Extraction', $fileName)) {
                $this->iniExportSettingsFiles[] = storage_path("" . self::INI_CONFIGS . "/extract/").$fileName;
            }
        }
    }

    /**
     * @return array <p>
     * Array of Extract settings order and group by Time Execution.
     */
    public function getRuleOfDataExtract()
    {
        $timeArray = [];

        if ($this->areAllExtractIniFilesValid()) {
            foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
                $tableContent = parse_ini_file($iniExportSettingsFile, true);
                $masterDB = $this->masterDBConfigData;
                $tableContent = $this->convertFollowingDbMaster($tableContent, self::EXTRACTION_CONDITION, $masterDB);
                $tableContent = $this->convertValueFromDBMaster($tableContent, $masterDB);
                foreach ($tableContent[self::EXTRACTION_PROCESS_BASIC_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                    $filesArray['setting'] = $tableContent;
                    $timeArray[$specifyTime][] = $filesArray;
                }
            }
            ksort($timeArray);
            return $timeArray;
        } else {
            Log::info("Error in Extract INI file");
            return [];
        }
    }

    /**
     * @param $tableContents <p> All content of an extract ini file </p>
     * @param $tagToConversion <p> Tag to convert </p>
     * @param $masterTable <p> master db config in array </p>
     * @return mixed
     */
    private function convertFollowingDbMaster($tableContents, $tagToConversion, $masterTable)
    {
        $columnNameConversion = $tableContents[$tagToConversion];
        foreach ($columnNameConversion as $key => $value) {
            if (isset($masterTable[$key])) {
                $columnNameConversion[$masterTable[$key]] = $value;
                unset($columnNameConversion[$key]);
            }
        }
        $tableContents[$tagToConversion] = $columnNameConversion;
        return $tableContents;
    }

    /**
     * In ini extract file, there're columns name must be maped from DB master
     * @param $table_contents <p> array to convert
     * @param $masterDB <p> master db config in array </p>
     * @return mixed
     */
    private function convertValueFromDBMaster($table_contents, $masterDB)
    {
        $jsonData = json_encode($table_contents);
        foreach ($masterDB as $table => $masterTable) {
            foreach ($masterTable as $key => $value) {
                if (strpos($key, '.') !== false) {
                    $jsonData = str_replace($key, $value, $jsonData);
                }
            }
        }
        return (json_decode($jsonData, true));
    }

    /**
     * @param $filename
     * @return array|bool|null
     */
    private function getIniFileContent($filename)
    {
        try {
            $iniArray = parse_ini_file($filename, true);
            $isValid = $this->isExtractIniValid($iniArray, $filename);
            return $isValid?$iniArray:null;
        } catch (\Exception $e) {
            Log::error(json_encode($e->getMessage(), JSON_PRETTY_PRINT));
            return null;
        }
    }

    /**
     * @return bool
     * <p>Check if all extract ini files are valid
     */
    private function areAllExtractIniFilesValid()
    {
        foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
            if (!$this->getIniFileContent($iniExportSettingsFile)) {
                return false;
            }
        }
        Log::info('areAllExtractIniFilesValid: YES');
        return true;
    }

    /**
     * @param $iniArray
     * @param null $filename
     * @return bool
     * <p>Check if a extract ini file is valid
     */
    private function isExtractIniValid($iniArray, $filename = null):bool
    {
        $rules = [
            self::EXTRACTION_PROCESS_BASIC_CONFIGURATION => 'required',
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
            // Validate children
            $tempIniArray = [];
            $tempIniArray['EXTRACTION_PROCESS_BASIC_CONFIGURATION'] = $iniArray[
                self::EXTRACTION_PROCESS_BASIC_CONFIGURATION
            ];
            $tempIniArray['OUTPUT_PROCESS_CONVERSION'] = $iniArray[self::OUTPUT_PROCESS_CONVERSION];
            $rules = [
                'EXTRACTION_PROCESS_BASIC_CONFIGURATION.ExtractionTable' => 'required',
                'EXTRACTION_PROCESS_BASIC_CONFIGURATION.ExecutionTime' => 'required',
                'EXTRACTION_PROCESS_BASIC_CONFIGURATION.OutputType' => 'required',
                'OUTPUT_PROCESS_CONVERSION.output_conversion' => 'required'
            ];
            $validate = Validator::make($tempIniArray, $rules);
            if ($validate->fails()) {
                Log::error("Error file: ".$filename?$filename:'');
                Log::error(json_encode($validate->getMessageBag(), JSON_PRETTY_PRINT));
                return false;
            } else {
                if (file_exists($tempIniArray['OUTPUT_PROCESS_CONVERSION']['output_conversion'])) {
                    Log::info('Validation PASSED');
                    return true;
                } else {
                    Log::error("Error file: ".$filename?$filename:'');
                    $outputProcessConversion = $tempIniArray['OUTPUT_PROCESS_CONVERSION']['output_conversion'];
                    Log::error("The file is not existed: ".$outputProcessConversion);
                    return false;
                }
            }
        }
    }
}
