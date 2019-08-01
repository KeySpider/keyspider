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

use App\Exceptions\SCIMException;
use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use Flow\JSONPath\JSONPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SCIMReader
{
    public function __construct()
    {
        $this->settingImport = new ImportSettingsManager();
    }

    /** Read file setting
     * @param $filePath
     * @return array
     * @throws \Exception
     */

    public function readData($filePath): array
    {
        $data = [];

        if (file_exists($filePath)) {
            $data = $this->settingImport->getSCIMImportSettings($filePath);
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
            return $importTable['ImportTable'];
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

    public function importFromSCIMData($dataPost, $setting)
    {
        try {
            $settingManagement = new SettingsManager();

            $nameTable = $this->getTableName($setting);
            $scimInputFormat = $setting[config('const.scim_format')];

            $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($nameTable);
            $DeleteFlagColumnName = $settingManagement->getDeleteFlagColumnName($nameTable);
            $primaryKey = $settingManagement->getTableKey($nameTable);
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            foreach ($scimInputFormat as $key => $value) {
                $scimValue = $this->getValueFromScimFormat($value, $dataPost);
                explode('.', $key);
                if (isset(explode('.', $key)[1])) {
                    if(isset($scimValue)){
                        //Create keys for postgres
                        $keyWithoutTableName = explode('.', $key)[1];

                        if (in_array($scimValue, $getEncryptedFields)) {
                            $dataCreate[$keyWithoutTableName] = $settingManagement->passwordEncrypt($scimValue);
                        }
                        else{
                            $dataCreate[$keyWithoutTableName] = "$scimValue";
                        }
                    }
                    //Remove old Key (it's only suitable for Mysql)
                    //Mysql:[User.ID], Postgres:[ID] only
                    unset($key);
                }
            }
            $data = DB::table($nameTable)->where($primaryKey, $dataCreate[$primaryKey])->first();

            if ($data) {
                DB::table($nameTable)->where($primaryKey, $dataCreate[$primaryKey])->update($dataCreate);
            } else {
//                Log::info($dataCreate);
                $query = DB::table($nameTable);
                $query->insert($dataCreate);
                Log::info($query->toSql());
            }

            return true;
        } catch (\Exception $e) {
            echo("Import from SCIM failed: $e");
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

    private function getValueFromScimFormat($value, $dataPost)
    {
        if ($value == 'TODAY()') {
            $nowString = Carbon::now()->format('Y/m/d');
            return $nowString;
        }
        $pattern = "/\((.*?)\)/";

        $isMatched = preg_match($pattern, $value, $matchedValue);
        try{
            if ($isMatched) {
                $findData = (new JSONPath($dataPost))->find($matchedValue[1]);
                //If get data from SCIM: DeleteFlag = (active)
                //active = false -> DeleteFlag =1
                //So flip the value
                if($value=="(active)")
                    return isset($findData[0])?(string)(((int)$findData[0]+1)%2):null;
                return isset($findData[0])?$findData[0]:null;
            }
        }catch (\Exception $exception){
            Log::info($exception);
        }

        return $value;
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

    public function updateUser($memberId, $inputRequest)
    {
        $path = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
        $operations = $inputRequest['Operations'];
        $pathToColumn = ["active"=>"DeleteFlag"];
        foreach ($operations as $operation) {
            //Add member to group.
            if (array_get($operation, 'op') === 'Replace')// and array_get($operation, 'path') === 'active')
            {
//                $columnNameInDB = isset($pathToColumn[array_get($operation, 'path')])?$pathToColumn[array_get($operation, 'path')]:array_get($operation, 'path');
                if(isset($pathToColumn[array_get($operation, 'path')]))
                {
                    $columnNameInDB = $pathToColumn[array_get($operation, 'path')];
                    $this->updateSCIMUser($memberId, [$columnNameInDB => $operation['value']]);
                }
            }
        }
        return true;
    }
    public function updateRole($memberId, $inputRequest)
    {
        $path = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
        $operations = $inputRequest['Operations'];
        $pathToColumn = ["active"=>"DeleteFlag"];
        foreach ($operations as $operation) {
            //Add member to group.
            if (array_get($operation, 'op') === 'Replace')// and array_get($operation, 'path') === 'active')
            {
//                $columnNameInDB = isset($pathToColumn[array_get($operation, 'path')])?$pathToColumn[array_get($operation, 'path')]:array_get($operation, 'path');
                if(isset($pathToColumn[array_get($operation, 'path')]))
                {
                    $columnNameInDB = $pathToColumn[array_get($operation, 'path')];
                    $this->updateSCIMUser($memberId, [$columnNameInDB => $operation['value']]);
                }
            }
        }
        return true;
    }

    public function updateMembersOfGroup($groupId, $inputRequest)
    {
        $path = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
        $operations = $inputRequest['Operations'];
        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($path);
        $tableName = $this->getTableName($setting);
        $roleMap = $importSetting->getRoleMapInName();
        $tableKey = $importSetting->getTableKey($tableName);

        $returnFlag = true;
        //TODO: So bad code, but keep it for testing, fix later.
        foreach ($operations as $operation) {
            //Add member to group.
            if (array_get($operation, 'op') === 'Add' and array_get($operation, 'path') === 'members') {
                $members = $operation['value'];
                if (is_string($members)) {
                    $memberId = $members;
                    $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId);
                } elseif (is_array($members)) {
                    foreach ($members as $member) {
                        $memberId = $member['value'];
                        $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId);
                    }
                }
            }
            //Remove member from group.
            if (array_get($operation, 'op') === 'Remove' and array_get($operation, 'path') === 'members') {
                $members = $operation['value'];
                if (is_string($members)) {
                    $memberId = $members;
                    $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId, false);
                } elseif (is_array($members)) {
                    foreach ($members as $member) {
                        $memberId = $member['value'];
                        $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId, false);
                    }
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
        $nameColumnUpdate = $settingManagement->getUpdateFlagsColumnName($nameTable);

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

    /**
     * @param string $memberId
     * @param string|null $tableName
     * @param string $tableKey
     * @param array|null $roleMap
     * @param $groupId
     * @return mixed
     */
    private function updateRoleForUser(string $memberId, ?string $tableName, string $tableKey, ?array $roleMap, $groupId, $isAdd=true)
    {
        Log::info("<<<<<Add member>>>>");
        Log::info(json_encode($roleMap, JSON_PRETTY_PRINT));
        Log::info("Add [$memberId] to [$groupId]\n\n\n");
        $setValues = [];
        $setValues["RoleID1"] = $groupId;
        foreach ($roleMap as $index => $role) {
            if ($role['ID'] == $groupId) {
                $setValues["RoleFlag-$index"] = $isAdd?1:0;
                break;
            }
        }
        $userRecord = (array)DB::table($tableName)->where($tableKey, $memberId)->get(['UpdateFlags'])->toArray()[0];
        $updateFlags = json_decode($userRecord['UpdateFlags'], true);
        array_walk($updateFlags, function (&$value) {
            $value = 1;
        });
        $setValues["UpdateFlags"] = json_encode($updateFlags);
        DB::table($tableName)->where($tableKey, $memberId)->update($setValues);
    }

    private function updateSCIMUser(string $memberId, array $values)
    {
        $setValues = $values;
        //Set DeleteFlag to 1 if active = false
        foreach ($setValues as $column=>$value){
            if($column==='DeleteFlag'){//If this is patch of deleting user, reset all roleFlags to 0
                $roleFlagColumns = $this->settingImport->getRoleFlags();
                if($value==="False"){
                    $setValues[$column] = 1;
//                    $setValues[$column] = $value==="False"?1:0;
                    foreach ($roleFlagColumns as $roleFlagColumn){
                        $setValues[$roleFlagColumn] = 0;
                    }
                }
                else{
                    $setValues[$column] = 0;
                }
            }
        }
        $userRecord = (array)DB::table("User")->where("ID", $memberId)->get(['UpdateFlags'])->toArray()[0];
        $updateFlags = json_decode($userRecord['UpdateFlags'], true);
        array_walk($updateFlags, function (&$value) {
            $value = 1;
        });
        $setValues["UpdateFlags"] = json_encode($updateFlags);
        DB::table("User")->where("ID", $memberId)->update($setValues);
    }

    private function updateSCIMRole(string $memberId, array $values)
    {
        $setValues = $values;
        $userRecord = (array)DB::table("Role")->where("ID", $memberId)->get(['UpdateFlags'])->toArray()[0];
        $updateFlags = json_decode($userRecord['UpdateFlags'], true);
        array_walk($updateFlags, function (&$value) {
            $value = 1;
        });
        $setValues["UpdateFlags"] = json_encode($updateFlags);
        DB::table("User")->where("ID", $memberId)->update($setValues);
    }

}
