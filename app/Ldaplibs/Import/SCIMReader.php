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
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
                $columns[] = "{$newstring}";
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
        $nameTable = $this->getTableName($setting);
        $columns = $this->getAllColumnFromSetting($setting[config('const.scim_format')]);

        foreach ($columns as $key => $col) {
            if (!Schema::hasColumn($nameTable, $col)) {
                Schema::table($nameTable, function (Blueprint $table) use ($col) {
                    $table->string($col)->nullable();
                });
            }
        }
    }

    public function verifyData($data): void
    {
        // TODO: Implement verifyData() method.
    }

    public function getFormatData($dataPost, $setting)
    {
        try {
            $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
            $settingManagement = new SettingsManager();

            $nameTable = $this->getTableName($setting);
            $scimInputFormat = $setting[config('const.scim_format')];

            $colUpdateFlag = $settingManagement->getNameColumnUpdated($nameTable);
            $primaryKey = $settingManagement->getTableKey($nameTable);
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            foreach ($scimInputFormat as $key => $item) {
                if ($key === '' || preg_match($pattern, $key) === 1) {
                    unset($scimInputFormat[$key]);
                }
            }

            $dataCreate = [];
            foreach ($scimInputFormat as $key => $value) {
                $item = $this->processGroup($value, $dataPost);
                $newsKey = substr($key, -3);
                $dataCreate[$newsKey] = $item;
            }
            $dataCreate[$colUpdateFlag] = json_encode(config('const.updated_flag_default'));

            foreach ($dataCreate as $cl => $item) {
                $tableColumn = $nameTable . '.' . $cl;

                if (in_array($tableColumn, $getEncryptedFields)) {
                    $dataCreate[$cl] = $settingManagement->passwordEncrypt($item);
                }
            }

            $data = DB::table($nameTable)->where($primaryKey, $dataCreate[$primaryKey])->first();

            if ($data) {
                DB::table($nameTable)->where($primaryKey, $dataCreate[$primaryKey])->update($dataCreate);
            } else {
                DB::table($nameTable)->insert($dataCreate);
            }

            return true;
        } catch (\Exception $e) {
            Log::error($e);
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
                if (isset($dataPost[config('const.scim_schema')])) {
                    $valueAttribute = $dataPost[config('const.scim_schema')]['department'];
                } else {
                    $valueAttribute = isset($dataPost['department']) ? $dataPost['department'] : null;
                }
            } else {
                $valueAttribute = $dataPost[$attribute] ?? null;
            }

            if ($regx === '') {
                return $valueAttribute;
            }

            preg_match("/{$regx}/", $valueAttribute, $data);

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

    public function updateMembersOfGroup($groupId, $inputRequest){
        $path = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
        $operations = $inputRequest['Operations'];
        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($path);
        $tableName = $this->getTableName($setting);
        $tableKey = (new SettingsManager())->getTableKey($tableName);
        $returnFlag = true;
        foreach ($operations as $operation){
            if(array_get($operation, 'op')==='Add' and array_get($operation, 'path')==='members') {
                $members = $operation['value'];
                foreach ($members as $member){
                    $memberId = $member['value'];
                    $query = "update `{$tableName}` set `005`='$groupId' where `{$tableKey}` = '$memberId'";
                    $returnFlag = $returnFlag and DB::update($query);
                }
            }
            if(array_get($operation, 'op')==='Remove' and array_get($operation, 'path')==='members') {
                $members = $operation['value'];
                foreach ($members as $member){
                    $memberId = $member['value'];
                    $query = "update `{$tableName}` set `005`=null where `{$tableKey}` = '$memberId'";
                    $returnFlag = $returnFlag and DB::update($query);
                }
            }
        }

        return $returnFlag;
    }

    /**
     * @param $id
     * @param $options
     * @return bool
     * @throws \Exception
     */
    public function updateReplaceSCIM($id, $options)
    {
        $path = $options['path'];

        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($path);

        $this->setColumnAndDataForUpdate($columns, $dataUpdate, $match, $options, $setting);

        $this->setDataForUpdateAndDeleteColumn($options['operation'], $setting, $columns, $dataUpdate);

        $columnsMerged = $this->buildSetQueryFromColumnsAndValues($columns, $dataUpdate);

        $updateResult = $this->updateDataToDB($id, $setting, $columnsMerged);

        return $updateResult;
    }

    /**
     * @param array $columns
     * @param array $dataUpdate
     * @return bool|string
     */
    private function buildSetQueryFromColumnsAndValues(array $columns, array $dataUpdate)
    {
        $columnsMerged = "";
        for ($i = 0; $i < count($columns); $i++) {
            $keyColumn = str_replace('"', '', $columns[$i]);
            $columnsMerged = "{$columnsMerged} `{$keyColumn}`=$dataUpdate[$i],";
        }
        $columnsMerged = substr($columnsMerged, 0, -1);
        return $columnsMerged;
    }

    /**
     * @param $id
     * @param array $setting
     * @param $columnsMerged
     * @return bool
     */
    private function updateDataToDB($id, array $setting, $columnsMerged): bool
    {
        $updateResult = false;
        $externalId = $id;
        $nameTable = $this->getTableName($setting);
        $keyTable = (new SettingsManager())->getTableKey($nameTable);
        $data = DB::table($nameTable)->where("{$keyTable}", $externalId)->first();
        if ($data) {
            if ($columnsMerged) {
                $key = "`{$keyTable}`";
                $query = "update `{$nameTable}` set $columnsMerged where {$key} = '$externalId'";
                DB::update($query);
            }

            $updateResult = true;
        }
        return $updateResult;
    }

    /**
     * @param $operation
     * @param array $setting
     * @param $columns
     * @param $dataUpdate
     */
    private function setDataForUpdateAndDeleteColumn($operation, array $setting, &$columns, &$dataUpdate): void
    {
        if ($operation['path'] === 'active') {
            array_push($columns, "\"015\"");
            if ($operation['value'] === 'False') {
                array_push($dataUpdate, '1');
            } else {
                array_push($dataUpdate, '1');
            }
        }

        $nameTable = $this->getTableName($setting);
        $settingManagement = new SettingsManager();
        $nameColumnUpdate = $settingManagement->getNameColumnUpdated($nameTable);

        if ($nameColumnUpdate) {
            array_push($columns, "\"{$nameColumnUpdate}\"");
        }


        $dataJsonUpdatedFlag = json_encode(config('const.updated_flag_default'));
        array_push($dataUpdate, "'{$dataJsonUpdatedFlag}'");
    }

    /**
     * @param $columns
     * @param $dataUpdate
     * @param $match
     * @param $options
     * @param array $setting
     */
    private function setColumnAndDataForUpdate(&$columns, &$dataUpdate, &$match, $options, array $setting): void
    {
        $pattern = '/\(\s*(?<exp1>\w+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/';
        $pattern2 = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';

        $attributeValue = null;
        $columns = [];
        $dataUpdate = [];
        $operation = $options['operation'];
        foreach ($setting[config('const.scim_format')] as $key => $valueSetting) {
            if ($key !== '' && preg_match($pattern2, $key) !== 1) {
                $isCheck = preg_match($pattern, $valueSetting, $match);

                if ($isCheck) {
                    $attributeValue = $match['exp1'];
                    $departmentField = config('const.scim_schema') . ":{$attributeValue}";

                    if ($attributeValue === $operation['path'] || $departmentField === $operation['path']) {
                        $newsKey = substr($key, -3);
                        $columns[] = "\"{$newsKey}\"";
                        $str = $this->convertDataFollowSetting($valueSetting, [
                            $attributeValue => $operation['value'],
                        ]);
                        array_push($dataUpdate, "\$\${$str}\$\$");
                    }
                }
            }
        }
    }
}
