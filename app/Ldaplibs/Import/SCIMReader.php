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
    public function readData($filePath): array
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
    public function getTableName($setting): ?string
    {
        $name = null;

        if (is_array($setting) && isset($setting[config('const.scim_input')])) {
            $importTable = $setting[config('const.scim_input')];

            switch ($importTable['ImportTable']) {
                case 'User':
                    $name = 'AAA';
                    break;
                case 'Role':
                    $name = 'CCC';
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
    public function getAllColumnFromSetting($dataFormat): array
    {
        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $columns = [];

        foreach ($dataFormat as $key => $item) {
            if ($key !== '' && preg_match($pattern, $key) !== 1) {
                $newstring = substr($key, -3);
                $columns[] = "\"{$newstring}\"";
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
    public function addColumns($setting): bool
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

    public function verifyData($data): void
    {
        // TODO: Implement verifyData() method.
    }

    public function getFormatData($dataPost, $setting)
    {
        $nameTable = $this->getTableName($setting);
        $scimInputFormat = $setting[config('const.scim_format')];

        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $columns = $this->getAllColumnFromSetting($setting[config('const.scim_format')]);
        $columns = implode(',', $columns);

        foreach ($scimInputFormat as $key => $item) {
            if ($key === '' || preg_match($pattern, $key) === 1) {
                unset($scimInputFormat[$key]);
            }
        }

        $data = [];
        foreach ($scimInputFormat as $key => $value) {
            $item = $this->processGroup($value, $dataPost);
            $newsKey = substr($key, -3);
            $data[$newsKey] = "\$\${$item}\$\$";
        }

        if (!empty($data)) {
            $condition = clean($data['001']);
            $condition = "\$\${$condition}\$\$";
            $firstColumn = '001';

            $query = "select exists(select 1 from \"{$nameTable}\" where \"{$firstColumn}\" = {$condition})";
            Log::debug($query);
            $isExit = DB::select($query);

            $stringValue = implode(',', $data);

            if ($isExit[0]->exists === false) {
                $query = "INSERT INTO \"{$nameTable}\"({$columns}) values ({$stringValue});";
                return DB::insert($query);
            }

            $query = "update \"{$nameTable}\" set ({$columns}) =
                    ({$stringValue}) where \"{$firstColumn}\" = {$condition};";
            return DB::update($query);
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
                if (!empty($dataPost['roles'])) {
                    return $dataPost['roles'][0];
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
                $valueAttribute = isset($dataPost[config('const.scim_schema')]) ?
                    $dataPost[config('const.scim_schema')]['department'] : null;
            } else {
                $valueAttribute = $dataPost[$attribute] ?? null;
            }

            if ($regx === '') {
                return $valueAttribute;
            }

            $isRegx = preg_match("/{$regx}/", $valueAttribute, $data);

            if ($regx === '\s') {
                return str_replace(' ', '', $valueAttribute);
            }
            if ($regx === '\w') {
                return strtolower($valueAttribute);
            }

            switch ($stt) {
                case '$1':
                    $str = $data[1] ?? null;
                    break;
                case '$2':
                    $str = $data[2] ?? null;
                    break;
                case '$3':
                    $str = $data[3] ?? null;
                    break;
                default:
                    $str = null;
            }

            return $str;
        }
    }

    /**
     * @param $id
     * @param $options
     * @return bool
     * @throws \Exception
     */
    public function updateReplaceSCIM($id, $options)
    {
        $externalId = $id;
        $path = $options['path'];
        $operation = $options['operation'];

        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($path);

        $pattern = '/\(\s*(?<exp1>\w+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/';
        $pattern2 = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';

        $attributeValue = null;
        $columns = [];
        $dataUpdate = [];

        foreach ($setting[config('const.scim_format')] as $key => $valueSetting) {
            if ($key !== '' && preg_match($pattern2, $key) !== 1) {
                $isCheck = preg_match($pattern, $valueSetting, $match);

                if ($isCheck) {
                    $attributeValue = $match['exp1'];

                    if ($attributeValue === $operation['path']) {
                        $newsKey = substr($key, -3);
                        $columns[] = "\"{$newsKey}\"";
                        $str = $this->convertDataFollowSetting($valueSetting, [
                            $attributeValue => $operation['value'],
                        ]);

                        $dataUpdate[] = "'{$str}'";
                    }
                }
            }
        }

        if ($operation['path'] === 'active') {
            array_push($columns, "\"015\"");
            if ($operation['value'] === 'False') {
                array_push($dataUpdate, '1');
            } else {
                array_push($dataUpdate, '1');
            }
        }

        $nameTable = $this->getTableName($setting);

        $condition = "\$\${$externalId}\$\$";
        $firstColumn = '001';

        $query = "select exists(select 1 from \"{$nameTable}\" where \"{$firstColumn}\" = {$condition})";
        $isExit = DB::select($query);

        $stringValue = implode(',', $dataUpdate);
        $columns = implode(',', $columns);

        if (!empty($columns) && !empty($dataUpdate) && $isExit[0]->exists) {
            $query = "update \"{$nameTable}\" set ({$columns}) = 
                    ({$stringValue}) where \"{$firstColumn}\" = {$condition}";
            DB::update($query);
        }

        return true;
    }
}
