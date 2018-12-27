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
use DB;
use Exception;
use Illuminate\Support\Facades\Log;

class DBImporter
{
    /**
     * @var array $setting
     * @var string $fileName
     * @var object $csvReader
     */
    protected $setting;
    protected $fileName;
    protected $csvReader;

    /**
     * define const
     */
    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Basic Configuration";

    /**
     * DBImporter constructor.
     *
     * @param array $setting
     * @param $fileName
     */
    public function __construct($setting, $fileName)
    {
        $this->setting = $setting;
        $this->fileName = $fileName;
        $this->csvReader = new CSVReader(new SettingsManager());
    }

    /**
     * Process import data csv into database
     *
     * @return void
     */
    public function import()
    {
        try {
            $processedFilePath = $this->setting[self::CONFIGURATION]['ProcessedFilePath'];
            mkDirectory($processedFilePath);

            $name_table = $this->csvReader->getNameTableFromSetting($this->setting);
            $columns = $this->csvReader->getAllColumnFromSetting($this->setting);

            $this->csvReader->createTable($name_table, $columns);

            $params = [
                'CONVERSATION' => $this->setting[self::CONVERSION],
            ];
            $this->csvReader->getDataFromOneFile($this->fileName, $params, $columns, $name_table, $processedFilePath);
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
