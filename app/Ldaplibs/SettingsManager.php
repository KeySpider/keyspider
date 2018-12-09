<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs;

use App\Ldaplibs\Import\CSVReader;

function contains($needle, $haystack)
{
    return strpos($haystack, $needle) !== false;
}

class SettingsManager
{
    private $iniImportSettingsFolder;
    private $iniExportSettingsFolder;
    const CONVERSION = "CSV Import Process Format Conversion";
    const INI_CONFIGS = "ini_configs";
    private $iniImportSettingsFiles = array();
    private $iniExportSettingsFiles = array();
    private $iniMasterDBFile = null;
    private $allTableSettingsContent = null;

    const BASIC_CONFIGURATION = "CSV Import Process Bacic Configuration";

    const EXTRACTION_PROCESS_BACIC_CONFIGURATION = "Extraction Process Bacic Configuration";

    const EXTRACTION_CONDITION = "Extraction Condition";

    const EXTRACTION_CONVERSION = "Extraction Process Format Conversion";

    public function __construct($ini_settings_files = null)
    {
        $this->iniImportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/import/");
        $this->iniExportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/extract/");
//        echo '<h3> parsing all ini files in folder: ' . $this->ini_settings_folders . "</h3>";
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
        }

    }

    public function getRuleOfDataExtract()
    {
        $timeArray = array();
        foreach ($this->iniExportSettingsFiles as $iniExportSettingsFile) {
            $tableContent = $this->getIniExportFileContent($iniExportSettingsFile);
            $extract_table_name = $tableContent[$this::EXTRACTION_PROCESS_BACIC_CONFIGURATION]['ExtractionTable'];
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
    }

    /**
     * @return array
     */
    public function getRuleOfImport()
    {
        if ($this->allTableSettingsContent) {
            return $this->allTableSettingsContent;
        }

        $filename = $this->iniMasterDBFile;
        $master = $this->getIniImportFileContent($filename);

        $this->allTableSettingsContent = array();

        foreach ($this->iniImportSettingsFiles as $ini_import_settings_file) {
            $tableContents = $this->getIniImportFileContent($ini_import_settings_file);
//            set filename in json file
            $tableContents['IniFileName'] = $ini_import_settings_file;
//            Set destination table in database
            $tableNameInput = $tableContents[self::BASIC_CONFIGURATION]["ImportTable"];
            $tableNameOutput = $master["$tableNameInput"]["$tableNameInput"];
            $tableContents[self::BASIC_CONFIGURATION]["TableNameInDB"] = $tableNameOutput;

            $masterDBConversion = $master[$tableNameInput];

//            Column conversion
            $columnNameConversion = $tableContents[self::CONVERSION];
            foreach ($columnNameConversion as $key => $value)
                if (isset($masterDBConversion[$key])) {
                    $columnNameConversion[$masterDBConversion[$key]] = $value;
                    unset($columnNameConversion[$key]);
                }
            $tableContents[self::CONVERSION] = $columnNameConversion;

            $this->allTableSettingsContent[] = $tableContents;
        }
        return $this->allTableSettingsContent;
    }

    /**
     * @param $filename     ini file name to read
     * @return array of key/value from ini file.
     */
    public function getIniImportFileContent($filename): array
    {
        $iniPath = $this->iniImportSettingsFolder . $filename;
        $iniArray = parse_ini_file($iniPath, true);
        return $iniArray;
    }

    public function getIniExportFileContent($filename): array
    {
        $iniPath = $this->iniExportSettingsFolder . $filename;
        $iniArray = parse_ini_file($iniPath, true);
        return $iniArray;
    }

    public function getScheduleImportExecution()
    {
        $rule = ($this->getRuleOfImport());
        $timeArray = array();
        foreach ($rule as $table_contents) {
            foreach ($table_contents[$this::BASIC_CONFIGURATION]['ExecutionTime'] as $specify_time) {
                $filesArray = array();
                $filesArray['setting'] = $table_contents;
                $filesArray['files'] = $this->getFilesFromPattern($table_contents[$this::BASIC_CONFIGURATION]['FilePath'],
                    $table_contents[$this::BASIC_CONFIGURATION]['FileName']);
                $timeArray[$specify_time][] = $filesArray;
            }
        }
        ksort($timeArray);
        return $timeArray;
    }

    public function getFilesFromPattern($path, $pattern)
    {
        $data = [];

        $pathDir = storage_path("{$path}");
        $validateFile = ['csv'];

        if (is_dir($pathDir)) {
            foreach (scandir($pathDir) as $key => $file) {
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

    protected function removeExt($file_name)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
        return $file;
    }

    /**
     * @param $tableContents
     * @param $tagToConversion
     * @param $masterTable
     * @return mixed
     */
    public function convert_following_db_master($tableContents, $tagToConversion, $masterTable)
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

    public function convertValueFromDBMaster($table_contents, $masterTable)
    {
        $jsonData = json_encode($table_contents);
        foreach ($masterTable as $key => $value) {
            if (strpos($key, '.') !== false) {
                $jsonData = str_replace($key, $value, $jsonData);
            }
        }
        return (json_decode($jsonData, true));
    }

    public function getIniOutputContent($file_name){
        return $this->getIniExportFileContent($file_name);
    }
}
