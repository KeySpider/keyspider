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

class ImportSettingsManager extends SettingsManager
{
    protected $iniExportSettingsFolder;
    protected $iniExportSettingsFiles = array();
    private $iniImportSettingsFiles = array();
    private $iniImportSettingsFolder;
    private $allTableSettingsContent = null;
    public function __construct($ini_settings_files = null)
    {
        Log::info("ImportSettingsManager");
        parent::__construct($ini_settings_files);
        $this->iniImportSettingsFolder = storage_path("" . self::INI_CONFIGS . "/import/");
        $allFiles = scandir($this->iniImportSettingsFolder);
        foreach ($allFiles as $fileName) {
            if ($this->contains('.ini', $fileName)) {
                if ($this->contains('Master', $fileName)) {
                    $this->iniMasterDBFile = $fileName;
                } else {
                    $this->iniImportSettingsFiles[] = $fileName;
                }
            }
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

        foreach ($this->iniImportSettingsFiles as $ini_import_settings_file) {
            $tableContents = $this->getIniImportFileContent($ini_import_settings_file);
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

        $pathDir = storage_path("{$path}");
        $pathDir = $path;
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

    /**
     * @return array of key/value from ini file.
     */
    private function getIniImportFileContent($filename): array
    {
        $iniPath = $this->iniImportSettingsFolder . $filename;
        $iniArray = parse_ini_file($iniPath, true);
        return $iniArray;
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