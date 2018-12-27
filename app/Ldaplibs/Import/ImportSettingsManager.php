<?php
/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Ldaplibs\Import;

use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportSettingsManager extends SettingsManager
{
    /**
     * @var array
     */
    private $iniImportSettingsFiles = [];
    private $iniImportSettingsFolder;
    private $allTableSettingsContent = null;

    /**
     * define const
     */
    const CSV_IMPORT_PROCESS_CONFIGRATION = 'CSV Import Process Configration';

    /**
     * ImportSettingsManager constructor.
     *
     * @param $iniSettingsFiles
     */
    public function __construct($iniSettingsFiles = null)
    {
        parent::__construct($iniSettingsFiles);
        $this->iniImportSettingsFolder = '';
    }

    /**
     * Get rule of Import without ordering by time execution
     *
     * @return array
     */
    private function getRuleOfImport()
    {

        if ($this->allTableSettingsContent) {
            return $this->allTableSettingsContent;
        }

        $master = $this->masterDBConfigData;

        $this->allTableSettingsContent = array();

        if (!$this->areAllImportIniFilesValid()) {
            return [];
        }

        foreach ($this->iniImportSettingsFiles as $iniImportSettingsFile) {
            $tableContents = parse_ini_file($iniImportSettingsFile, true);
            if ($tableContents == null) {
                Log::error("Can not run import schedule");
                return [];
            }
            // set filename in json file
            $tableContents['IniFileName'] = $iniImportSettingsFile;
            // Set destination table in database
            $tableNameInput = $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]["ImportTable"];
            $tableNameOutput = $master[(string)$tableNameInput][(string)$tableNameInput];
            $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]["TableNameInDB"] = $tableNameOutput;

            $masterDBConversion = $master[$tableNameInput];

            // Column conversion
            $columnNameConversion = $tableContents[SettingsManager::CSV_IMPORT_PROCESS_FORMAT_CONVERSION];
            foreach ($columnNameConversion as $key => $value) {
                if (isset($masterDBConversion[$key])) {
                    $columnNameConversion[$masterDBConversion[$key]] = $value;
                    unset($columnNameConversion[$key]);
                }
            }
            $tableContents[SettingsManager::CSV_IMPORT_PROCESS_FORMAT_CONVERSION] = $columnNameConversion;

            $this->allTableSettingsContent[] = $tableContents;
        }
        return $this->allTableSettingsContent;
    }

    /**
     * Get rule of Import order and group by Schedule
     *
     * @return array
     */
    public function getScheduleImportExecution()
    {
        if ($this->key_spider == null) {
            Log::error("Wrong key spider! Do nothing.");
            return[];
        }
        $allFiles = $this->key_spider[self::CSV_IMPORT_PROCESS_CONFIGRATION]['import_config'];
        foreach ($allFiles as $fileName) {
            $this->iniImportSettingsFiles[] = $fileName;
        }

        $rule = ($this->getRuleOfImport());

        $timeArray = array();
        foreach ($rule as $tableContents) {
            foreach ($tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                $filesFromPattern = $this->getFilesFromPattern(
                    $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['FilePath'],
                    $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['FileName']
                );
                if (count($filesFromPattern)==0) {
                    continue;
                }
                $filesArray = [];
                $filesArray['setting'] = $tableContents;
                $filesArray['files'] = $filesFromPattern;
                $timeArray[$specifyTime][] = $filesArray;
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
     * @param $fileName
     * @return array|bool|null <p>array of key/value from ini file.</p>
     */
    private function getIniFileContent($fileName)
    {
        try {
            $iniArray = parse_ini_file($fileName, true);
            $isValid = $this->isImportIniValid($iniArray, $fileName);
            return $isValid ? $iniArray : null;
        } catch (\Exception $e) {
            Log::error(json_encode($e->getMessage(), JSON_PRETTY_PRINT));
            return null;
        }
    }

    /**
     * @return bool
     * <p>Check if all configure files are valid
     */
    private function areAllImportIniFilesValid()
    {
        foreach ($this->iniImportSettingsFiles as $iniImportSettingsFile) {
            if (!$this->getIniFileContent($iniImportSettingsFile)) {
                return false;
            }
        }
        Log::info('areAllImportIniFilesValid: YES');
        return true;
    }

    /**
     * @param $iniArray
     * @param null $fileName
     * @return bool
     * <p>Check if a configure file are valid
     */
    private function isImportIniValid($iniArray, $fileName = null): bool
    {
        $rules = [
            self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION => 'required',
            self::CSV_IMPORT_PROCESS_FORMAT_CONVERSION => 'required'
        ];
// Validate main keys
        $validate = Validator::make($iniArray, $rules);
        if ($validate->fails()) {
            Log::error("Key error validation");
            Log::error("Error file: " . $fileName ? $fileName : '');
            Log::error($validate->getMessageBag());
            return false;
        }

// Validate children
        $validate = $this->validateBasicConfiguration($iniArray);
        if ($validate->fails()) {
            Log::error("Key error validation");
            Log::error("Error file: " . $fileName ? $fileName : '');
            Log::info(json_encode($validate->getMessageBag(), JSON_PRETTY_PRINT));
            return false;
        }
        return true;
    }

    /**
     * @param $iniArray
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateBasicConfiguration($iniArray)
    {
        $tempIniArray = [];
        $tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION'] = $iniArray[
            self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION];
        $tempIniArray['CSV_IMPORT_PROCESS_FORMAT_CONVERSION'] = $iniArray[self::CSV_IMPORT_PROCESS_FORMAT_CONVERSION];
        $rules = [
            'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.ImportTable' => 'required',
            'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.FilePath' => 'required',
            'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.FileName' => 'required',
            'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.ProcessedFilePath' => 'required',
        ];
        if (($this->isFolderExisted($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['FilePath'])) &&
            ($this->isFolderExisted($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['ProcessedFilePath']))) {
            return Validator::make($tempIniArray, $rules);
        }

        Log::error('Double check folders are existed or not');
        Log::info($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['FilePath']);
        Log::info($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['ProcessedFilePath']);
        return Validator::make([], $rules);
    }
}
