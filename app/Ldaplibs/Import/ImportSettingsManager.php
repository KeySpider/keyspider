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
     * define const
     */
    public const CSV_IMPORT_PROCESS_CONFIGRATION = 'CSV Import Process Configration';
    /**
     * @var array
     */
    private $iniImportSettingsFiles = [];
    private $iniImportSettingsFolder;
    private $allTableSettingsContent;

    /**
     * ImportSettingsManager constructor.
     *
     * @param $iniSettingsFiles
     */
    const SCIM_INPUT_BACIC_CONFIGURATION = 'SCIM Input Bacic Configuration';

    const SCIM_INPUT_FORMAT_CONVERSION = 'SCIM Input Format Conversion';

    public function __construct($iniSettingsFiles = null)
    {
        parent::__construct($iniSettingsFiles);
        $this->iniImportSettingsFolder = '';
    }

    /**
     * Get rule of Import order and group by Schedule
     *
     * @return array
     */
    public function getScheduleImportExecution(): array
    {
        if ($this->key_spider == null) {
            Log::error('Wrong key spider! Do nothing.');
            return [];
        }
        $allFiles = $this->key_spider[self::CSV_IMPORT_PROCESS_CONFIGRATION]['import_config'];
        foreach ($allFiles as $fileName) {
            $this->iniImportSettingsFiles[] = $fileName;
        }

        $rule = $this->getRuleOfImport();

        $timeArray = array();
        foreach ($rule as $tableContents) {
            foreach ($tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['ExecutionTime'] as $specifyTime) {
                $filesFromPattern = $this->getFilesFromPattern(
                    $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['FilePath'],
                    $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['FileName']
                );
                if (count($filesFromPattern) == 0) {
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
     * Get rule of Import without ordering by time execution
     *
     * @return array
     */
    private function getRuleOfImport(): array
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
                Log::error('Can not run import schedule');
                return [];
            }
            // set filename in json file
            $tableContents['IniFileName'] = $iniImportSettingsFile;
            // Set destination table in database
            $tableNameInput = $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['ImportTable'];
            $tableNameOutput = $master[(string)$tableNameInput][(string)$tableNameInput];
            $tableContents[self::CSV_IMPORT_PROCESS_BASIC_CONFIGURATION]['TableNameInDB'] = $tableNameOutput;

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
     * @return bool
     * <p>Check if all configure files are valid
     */
    private function areAllImportIniFilesValid(): bool
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
            Log::error('Key error validation');
            Log::error('Error file: ' . $fileName ? $fileName : '');
            Log::error($validate->getMessageBag());
            return false;
        }

        // Validate children
        $validate = $this->validateBasicConfiguration($iniArray);
        if ($validate == null) {
            Log::info('Please create the folder in your server');
            return false;
        }
        if ($validate->fails()) {
            Log::error('Key error validation');
            Log::error('Error file: ' . $fileName ? $fileName : '');
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
            'CSV_IMPORT_PROCESS_BASIC_CONFIGURATION.ProcessedFilePath' => 'required'
        ];
        if ($this->isFolderExisted($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['FilePath']) &&
            $this->isFolderExisted($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['ProcessedFilePath'])) {
            return Validator::make($tempIniArray, $rules);
        }

        Log::error('Double check folders are existed or not');
        Log::info($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['FilePath']);
        Log::info($tempIniArray['CSV_IMPORT_PROCESS_BASIC_CONFIGURATION']['ProcessedFilePath']);
        return null;
    }

    /**
     * @param $path <p>
     * Path of directory </p>
     * @param $pattern <p>
     * Pattern of file name </p>
     * @return array
     * <p>Array of file name matched pattern
     */
    private function getFilesFromPattern($path, $pattern): array
    {
        $data = [];

        $validateFile = ['csv'];

        if (is_dir($path)) {
            foreach (scandir($path) as $key => $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($ext, $validateFile) &&
                    preg_match("/{$this->removeExt($pattern)}/", $this->removeExt($file))) {
                    array_push($data, "{$path}/{$file}");
                }
            }
        }

        return $data;
    }

    /**
     * Get settings for import SCIM
     * @param null $filePath
     * @return array
     * @throws \Exception
     */
    public function getSCIMImportSettings($filePath)
    {
        try {
            $iniArray = parse_ini_file($filePath, true);
            $rules = [
                self::SCIM_INPUT_BACIC_CONFIGURATION => 'required',
                self::SCIM_INPUT_FORMAT_CONVERSION => 'required'
            ];
            // Validate main keys
            $validate = Validator::make($iniArray, $rules);
            if ($validate->fails()) {
                throw new \Exception($validate->getMessageBag());
            }
        } catch (\Exception $exception) {
            Log::error('Key error SCIM import validation');
        }


        $iniSCIMSettingsArray = [];
        try {
            $iniSCIMSettingsArray = parse_ini_file($filePath, true);
            $tableNameInput = $iniSCIMSettingsArray[self::SCIM_INPUT_BACIC_CONFIGURATION]['ImportTable'];
            $masterDBConversion = $this->masterDBConfigData[$tableNameInput];
            $columnNameConversion = $iniSCIMSettingsArray[self::SCIM_INPUT_FORMAT_CONVERSION];
            foreach ($columnNameConversion as $key => $value) {
                if (isset($masterDBConversion[$key])) {
                    $columnNameConversion[$masterDBConversion[$key]] = $value;
                    unset($columnNameConversion[$key]);
                }
            }
            $iniSCIMSettingsArray[self::SCIM_INPUT_FORMAT_CONVERSION] = $columnNameConversion;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }

        return $iniSCIMSettingsArray;
    }

    /**
     * @return array
     */
    public function getColumnsConversion()
    {
        $import_settings = parse_ini_file(storage_path('ini_configs/import/UserInfoSCIMInput.ini'), true);
        $masterDBConf = parse_ini_file(storage_path('ini_configs/MasterDBConf.ini'), true);

        $table_name = $import_settings[self::SCIM_INPUT_BACIC_CONFIGURATION]['ImportTable'];
        $formatConversion = $import_settings[self::SCIM_INPUT_FORMAT_CONVERSION];
        $dbConfigOfTable = $masterDBConf[$table_name];
        $result = [];

        foreach ($formatConversion as $key => $value) {
            if (isset($dbConfigOfTable[$key])) {
                $result[$value] = $dbConfigOfTable[$key];
            }
        }
        return $result;
    }
}
