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

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                case 'Role':
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

    public function getFormatData($dataPost, $setting)
    {
        $nameTable = $this->getTableName($setting);
        $scimInputFormat = $setting[config('const.scim_format')];
        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $columns = $this->getAllColumnFromSetting($setting[config('const.scim_format')]);
        $columns = implode(",", $columns);

        foreach ($scimInputFormat as $key => $item) {
            if ($key === "" || preg_match($pattern, $key) === 1) {
                unset($scimInputFormat[$key]);
            }
        }

        foreach ($scimInputFormat as $key => $value) {
            $item = $this->processGroup($value, $dataPost);
            $scimInputFormat[$key] = "\$\${$item}\$\$";
        }

        if (!empty($scimInputFormat)) {
            $condition = clean($scimInputFormat["{$nameTable}.001"]);
            $condition = "\$\${$condition}\$\$";
            $firstColumn = "{$nameTable}.001";

            $query = "select exists(select 1 from \"{$nameTable}\" where \"{$firstColumn}\" = {$condition})";
            Log::debug($query);
            $isExit = DB::select($query);

            $stringValue = implode(",", $scimInputFormat);

            if ($isExit[0]->exists === FALSE) {
                $query = "INSERT INTO \"{$nameTable}\"({$columns}) values ({$stringValue});";
                return DB::insert($query);
            } else {
                $query = "update \"{$nameTable}\" set ({$columns}) = ({$stringValue}) where \"{$firstColumn}\" = {$condition};";
                return DB::update($query);
            }
        }
    }

    public function processGroup($group, $dataPost)
    {
        switch ($group) {
            case 'admin':
                return 'admin';
            case 'TODAY()':
                return Carbon::now()->format('Y/m/d');
            case '0':
                return '0';
            case 'hogehoge':
                return 'hogehoge';
            case 'hogehoga':
                return 'hogehoga';
            case '(roles[0])':
                if (!empty($dataPost["roles"])) {
                    return $dataPost["roles"][0];
                }
                return null;
            default:
                return $this->convertDataFollowSetting($group, $dataPost);
        }
    }

    /**
     * Covert data follow from setting
     *
     * @param $value
     * @param $dataPost
     * @return mixed|string
     */
    public function convertDataFollowSetting($value, $dataPost)
    {
        $str = null;
        $pattern = '/\(\s*(?<exp1>\w+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/';

        $isCheck = preg_match($pattern, $value, $match);

        if ($isCheck) {
            $attribute = $match['exp1'];
            $regx = $match['exp2'];
            $stt = $match['exp3'];

            if ($attribute === 'department') {
                $valueAttribute = $dataPost[config('const.scim_schema')]['department'];
            } else {
                $valueAttribute = isset($dataPost[$attribute]) ? $dataPost[$attribute] : null;
            }

            if ($regx === "") {
                return $valueAttribute;
            }

            $isRegx = preg_match("/{$regx}/", $valueAttribute, $data);

            if ($isRegx) {
                if ($regx === '\s') {
                    return str_replace(' ', '', $valueAttribute);
                }
                if ($regx === '\w') {
                    return strtolower($valueAttribute);
                }

                switch ($stt) {
                    case '$1':
                        $str = isset($data[1]) ? $data[1] : null;
                        break;
                    case '$2':
                        $str = isset($data[2]) ? $data[2] : null;
                        break;
                    case '$3':
                        $str = isset($data[3]) ? $data[3] : null;
                        break;
                    default:
                        $str = null;
                }
            }

            return $str;
        }
    }
}
