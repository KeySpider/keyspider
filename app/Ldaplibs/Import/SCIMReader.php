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
                    if (isset($scimValue)) {
                        //Create keys for postgres
                        $keyWithoutTableName = explode('.', $key)[1];

                        if (in_array($scimValue, $getEncryptedFields)) {
                            $dataCreate[$keyWithoutTableName] = $settingManagement->passwordEncrypt($scimValue);
                        } else {
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
            Log::error($e->getMessage());
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
        try {
            if ($isMatched) {
                $findData = (new JSONPath($dataPost))->find($matchedValue[1]);
                //If get data from SCIM: DeleteFlag = (active)
                //active = false -> DeleteFlag =1
                //So flip the value
                if ($value == "(active)")
                    return isset($findData[0]) ? (string)(((int)$findData[0] + 1) % 2) : null;
                return isset($findData[0]) ? $findData[0] : null;
            }
        } catch (\Exception $exception) {
            Log::info($exception->getMessage());
        }

        return null;
    }

    public function updateRsource($memberId, $inputRequest, $setting)
    {
        $resourceType = $this->getTableName($setting);
        $operations = $inputRequest['Operations'];
        $pathToColumn = $this->getScimPathToColumnMap($setting);
        if (count($pathToColumn) < 1) {
            return false;
        }
        $formattedOperations = $this->getFormatedOperationsFromRequest($operations);

        foreach ($formattedOperations as $operation) {
            //Update user attribute. Replace or Add is both ok.
            if (in_array(array_get($operation, 'op'), ["Replace", "Add"]))//
            {
                if (isset($pathToColumn[array_get($operation, 'path')])) {
                    $mapColumnsInDB = $pathToColumn[array_get($operation, 'path')];
                    foreach ($mapColumnsInDB as $column) {
                        $this->updateSCIMResource($memberId, [$column => $operation['value']], $resourceType);
                    }
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
                    $returnFlag = $returnFlag & $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId);
                } elseif (is_array($members)) {
                    foreach ($members as $member) {
                        $memberId = $member['value'];
                        $returnFlag = $returnFlag & $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId);
                    }
                }
            }
            //Remove member from group.
            if (array_get($operation, 'op') === 'Remove' and array_get($operation, 'path') === 'members') {
                $members = $operation['value'];
                if (is_string($members)) {
                    $memberId = $members;
                    $returnFlag = $returnFlag & $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId, false);
                } elseif (is_array($members)) {
                    foreach ($members as $member) {
                        $memberId = $member['value'];
                        $returnFlag = $returnFlag & $this->updateRoleForUser($memberId, $tableName, $tableKey, $roleMap, $groupId, false);
                    }
                }
            }
        }

        return $returnFlag;
    }

    /**
     * @param string $memberId
     * @param string|null $tableName
     * @param string $tableKey
     * @param array|null $roleMap
     * @param $groupId
     * @return mixed
     */
    private function updateRoleForUser(string $memberId, ?string $tableName, string $tableKey, ?array $roleMap, $groupId, $isAdd = true)
    {
        Log::info("<<<<<Add member>>>>");
        Log::info(json_encode($roleMap, JSON_PRETTY_PRINT));
        Log::info("Add [$memberId] to [$groupId]\n\n\n");
        $setValues = [];
//        $setValues["RoleID1"] = $groupId;
        foreach ($roleMap as $index => $role) {
            if ($role['ID'] == $groupId) {
                $setValues["RoleFlag-$index"] = $isAdd ? 1 : 0;
                break;
            }
        }
        if(count($setValues)) {
            $userRecord = (array)DB::table($tableName)->where($tableKey, $memberId)->get(['UpdateFlags'])->toArray()[0];
            $updateFlags = json_decode($userRecord['UpdateFlags'], true);
            array_walk($updateFlags, function (&$value) {
                $value = 1;
            });
            $setValues["UpdateFlags"] = json_encode($updateFlags);
            DB::table($tableName)->where($tableKey, $memberId)->update($setValues);
            return true;
        }
        else{
            return false;
        }
    }

    private function updateSCIMResource(string $memberId, array $values, $resourceType)
    {
        $updateFlagsColumnName = $this->settingImport->getUpdateFlagsColumnName($resourceType);
        $deleteFlagColumnName = $this->settingImport->getDeleteFlagColumnName($resourceType);
        $primaryKey = $this->settingImport->getTableKey($resourceType);
        $setValues = $values;
        //Set DeleteFlag to 1 if active = false
        foreach ($setValues as $column => $value) {
            if ($column === 'DeleteFlag') {//If this is patch of deleting user, reset all roleFlags to 0
                $roleFlagColumns = $this->settingImport->getRoleFlags();
                if ($value === "False") {
                    $setValues[$column] = 1;
//                    $setValues[$column] = $value==="False"?1:0;
                    foreach ($roleFlagColumns as $roleFlagColumn) {
                        $setValues[$roleFlagColumn] = 0;
                    }
                } else {
                    $setValues[$column] = 0;
                }
            }
        }
        $userRecord = (array)DB::table($resourceType)->where($primaryKey, $memberId)->get([$updateFlagsColumnName])->toArray()[0];
        $updateFlags = json_decode($userRecord[$updateFlagsColumnName], true);
        array_walk($updateFlags, function (&$value) {
            $value = 1;
        });
        $setValues[$updateFlagsColumnName] = json_encode($updateFlags);
        DB::table($resourceType)->where($primaryKey, $memberId)->update($setValues);
    }

    /**
     * Get map from Scim ini config
     * Flip the array
     * New scim format is no brackets
     * @return array
     */
    private function getScimPathToColumnMap($setting): array
    {
        try {
            $scimFormatConversion = $setting['SCIM Input Format Conversion'];

            $results = [];
            foreach ($scimFormatConversion as $column => $scimFormat) {
                $pattern = "/^\((.*?)\)$/";
                //Get string inside () and from . to the end
                $shortColumnName = explode('.', $column)[1];
                $isMatched = preg_match($pattern, $scimFormat, $matchedValue);
                if ($isMatched) {
                    //Remove everything between []
                    $newKey = preg_replace("/\[[^)]+\]/", "", $matchedValue[1]);
                    if (array_key_exists($newKey, $results)) {
                        array_push($results[$newKey], $shortColumnName);
                    } else {
                        $results[$newKey] = [$shortColumnName];
                    }
                }
            }


        } catch (\Exception $exception) {
            Log::info(json_encode($exception->getMessage()));
            return [];
        }
        return $results;
    }

    /**
     * @param $operations
     * @return array
     */
    private function getFormatedOperationsFromRequest($operations): array
    {
//New format of operations is no bracket in the path
        //This is my smart way to compare with scim ini config
        $formattedOperations = array_map(function ($operation) {
            $operation['path'] = preg_replace("/\[[^)]+\]/", "", $operation['path']);
            return $operation;
        }, $operations);
        //When deleteing user, ignore changing other things.
        $deleteUserPatch =
            ['op' => "Replace",
                'path' => "active",
                'value' => "False"];
        foreach ($formattedOperations as $op) {
            if ($op === $deleteUserPatch) {
                $formattedOperations = [$deleteUserPatch];
                break;
            }
        }
        return $formattedOperations;
    }

}
