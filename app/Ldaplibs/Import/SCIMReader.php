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

use Illuminate\Support\Facades\DB;

class SCIMReader
{
    /** Read file setting
     * @param $filePath
     * @return array
     * @throws \Exception
     */
    public function readData($filePath)
    {
        $data = [];

        if (file_exists($filePath)) {
            $settingImport = new ImportSettingsManager();
            $data = $settingImport->getSCIMImportSettings($filePath);
        }

        return $data;
    }

    /**
     * Scim , get name table
     *
     * @param $setting
     * @return string|null
     */
    public function getTableName($setting)
    {
        $name = null;

        if (is_array($setting) && isset($setting[config('const.scim_input')])) {
            $importTable = $setting[config('const.scim_input')];

            switch ($importTable['ImportTable']) {
                case 'User':
                    $name = "AAA";
                    break;
                case 'Group':
                    $name = "CCC";
                    break;
            }

            return $name;
        }
    }

    /**
     * Get all column from setting SCIM file
     *
     * @param $dataFormat
     * @return array
     */
    public function getAllColumnFromSetting($dataFormat)
    {
        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $columns = [];

        foreach ($dataFormat as $key => $item) {
            if ($key !== "" && preg_match($pattern, $key) !== 1) {
                array_push($columns, "\"{$key}\"");
            }
        }

        return $columns;
    }

    /**
     * Create columns
     *
     * @param $setting
     * @return bool
     */
    public function addColumns($setting)
    {
        $sql = null;
        $nameTable = $this->getTableName($setting);
        $columns = $this->getAllColumnFromSetting($setting[config('const.scim_format')]);

        foreach ($columns as $key => $col) {
            if ($key < count($columns) - 1) {
                $sql .= "ADD COLUMN if not exists {$col} VARCHAR (250) NULL,";
            } else {
                $sql .= "ADD COLUMN if not exists {$col} VARCHAR (250) NULL";
            }
        }

        if ($sql) {
            $query = "ALTER TABLE \"{$nameTable}\" {$sql};";

            if (DB::statement($query)) {
                return true;
            }
        }

        return false;
    }

    public function verifyData($data)
    {
        // TODO: Implement verifyData() method.
    }

    public function getFormatData($data)
    {
        // TODO: Implement getFormatData() method.
    }
}
