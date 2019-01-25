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

namespace App\Ldaplibs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsManager
{
    public const INI_CONFIGS = 'ini_configs';
    public const EXTRACTION_CONDITION = 'Extraction Condition';
    public const CSV_IMPORT_PROCESS_FORMAT_CONVERSION = 'CSV Import Process Format Conversion';
    public const EXTRACTION_PROCESS_BASIC_CONFIGURATION = 'Extraction Process Basic Configuration';
    public const CSV_IMPORT_PROCESS_BASIC_CONFIGURATION = 'CSV Import Process Basic Configuration';

    public $iniMasterDBFile;
    public $masterDBConfigData;
    protected $keySpider;

    public function __construct($ini_settings_files = null)
    {
        if (!$this->validateKeySpider()) {
            $this->keySpider = null;
        } else {
            $this->iniMasterDBFile = $this->keySpider['Master DB Configurtion']['master_db_config'];
            $this->masterDBConfigData = parse_ini_file($this->iniMasterDBFile, true);
        }
    }

    protected function removeExt($file_name)
    {
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
    }

    protected function contains($needle, $haystack): bool
    {
        return strpos($haystack, $needle) !== false;
    }
    /** @noinspection MultipleReturnStatementsInspection */

    /**
     * @return bool|null
     */
    public function validateKeySpider(): ?bool
    {
        try {
            $this->keySpider = parse_ini_file(
                storage_path(self::INI_CONFIGS . '/KeySpider.ini'),
                true);

            $validate = Validator::make($this->keySpider, [
                'Master DB Configurtion' => 'required',
                'CSV Import Process Configration' => 'required',
                'SCIM Input Process Configration' => 'required',
                'CSV Extract Process Configration' => 'required'
            ]);
            if ($validate->fails()) {
                Log::error('Key spider INI is not correct!');
                throw new \RuntimeException($validate->getMessageBag());
            }
        } catch (\Exception $exception) {
            Log::error('Error on file KeySpider.ini');
            Log::error($exception->getMessage());
            dd($exception->getMessage());
        }

        try {
            $filesPath = parse_ini_file(storage_path(self::INI_CONFIGS .
                '/KeySpider.ini'));

            $allFilesInKeySpider = array_merge($filesPath['import_config'],
                $filesPath['extract_config'],
                [$filesPath['master_db_config']]);
            array_walk($allFilesInKeySpider,
                function ($filePath) {
                    if (!file_exists($filePath)) {
                        throw new \RuntimeException("[KeySpider validation error] The file: <$filePath> is not existed!");
                    }
                });
        } catch (\Exception $exception) {
            Log::error('Error on file KeySpider.ini');
            Log::error($exception->getMessage());
            dd($exception->getMessage());
        }
        return true;
    }

    public function isFolderExisted($folderPath): bool
    {
        return is_dir($folderPath);
    }
}
