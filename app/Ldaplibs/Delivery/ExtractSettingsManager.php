<?php
/**
 * Created by PhpStorm.
 * User: anhtuan
 * Date: 12/11/18
 * Time: 10:12 AM
 */

namespace App\Ldaplibs\Delivery;


use App\Ldaplibs\SettingsManager;

class ExtractSettingsManager extends SettingsManager
{
    public const EXTRACTION_CONVERSION = "Extraction Process Format Conversion";
    protected $iniExportSettingsFolder;
    protected $iniExportSettingsFiles = array();

    public function __construct($ini_settings_files = null)
    {
        parent::__construct($ini_settings_files);
        $this->iniExportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
        $allFiles = scandir($this->iniExportSettingsFolder);
        foreach ($allFiles as $fileName) {
            if ($this->contains('.ini', $fileName) && $this->contains('Extraction', $fileName)) {
                $this->iniExportSettingsFiles[] = $fileName;
            }
        }
    }

    public function getRuleOfDataExtract()
    {
        $timeArray = array();
        foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
            $tableContent = $this->getIniExportFileContent($iniExportSettingsFile);
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