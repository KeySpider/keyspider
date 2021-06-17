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
use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\UserGraphAPI;

use Carbon\Carbon;
use Flow\JSONPath\JSONPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SCIMReader
{
    private const SCIM_ENT = 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User';
    private const SCIM_MNG = 'manager';

    private const USER_ENTERPRISE_ATTRS = array(
        '(department)', '(employeeNumber)',
    );

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
            $resourceType = $importTable['ImportTable'];
            $this->updateFlagsColumnName = $this->settingImport->getUpdateFlagsColumnName($resourceType);
            $this->deleteFlagColumnName = $this->settingImport->getDeleteFlagColumnName($resourceType);
            $this->primaryKey = $this->settingImport->getTableKey();
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
            $externalId = $setting[config('const.scim_input')]["ExternalID"];
            $scimInputFormat = $setting[config('const.scim_format')];
            $colUpdateFlag = $this->updateFlagsColumnName;
            $DeleteFlagColumnName = $this->deleteFlagColumnName;
            $primaryKey = $this->primaryKey;
            $getEncryptedFields = $settingManagement->getEncryptedFields();
            $roleMaps = $settingManagement->getRoleMapInName($nameTable);

            $dataCreate = [];

            foreach ($scimInputFormat as $key => $value) {
                $scimValue = $this->getValueFromScimFormat($value, $dataPost);
                //Create keys for postgres
                $keyWithoutTableName = explode('.', $key)[1] ?? null;
                if ($keyWithoutTableName == config('const.JOB_TITLE')) {
                    $dataCreate = $settingManagement->resetRoleFlagX($dataCreate);
                    $xIndex = $settingManagement->getRoleFlagX($scimValue, $roleMaps);
                    if (!empty($xIndex)) {
                        $keyValue = sprintf("RoleFlag-%d", $xIndex);
                        $dataCreate[$keyValue] = '1';
                    }
                }

                if ($keyWithoutTableName && isset($scimValue)) {

                    // if (in_array($scimValue, $getEncryptedFields)) {
                    if (in_array($key, $getEncryptedFields)) {
                        $dataCreate[$keyWithoutTableName] = $settingManagement->passwordEncrypt($scimValue);
                    } else {
                        $dataCreate[$keyWithoutTableName] = "$scimValue";
                    }

                    //Remove old Key (it's only suitable for Mysql)
                    //Mysql:[User.ID], Postgres:[ID] only
                    unset($key);
                }
            }
            $dataCreate[$colUpdateFlag] = $settingManagement->makeUpdateFlagsJson($nameTable);
            $data = DB::table($nameTable)->where("user-PrincipalName", $dataCreate["user-PrincipalName"])->first();
            if ($data) {
                DB::table($nameTable)->where("user-PrincipalName", $dataCreate["user-PrincipalName"])->update($dataCreate);
            } else {
                // Log::info($dataCreate);
                $uObjectId = $this->getAzureUserObjectId($dataCreate);
                $dataCreate['externalID'] = $uObjectId;

                $dataCreate[$primaryKey] = $settingManagement->makeIdBasedOnMicrotime($nameTable);
                DB::table($nameTable)->insert($dataCreate);
            }

            return true;
        } catch (\Exception $e) {
            echo ("Import from SCIM failed: $e");
            Log::error($e->getMessage());
        }
    }

    private function getAzureUserObjectId($uObject)
    {
        // Get AAD User ObjectID
        $userGraphAPI = new UserGraphAPI();

        $uID = 'Non-existent-user';
        $uPN = $uObject['user-PrincipalName'];

        $aadUser = $userGraphAPI->getUserDetail($uID, $uPN);
        // change object to array
        $aryUser = (array)$aadUser;
        // split key and value
        foreach ($aryUser as $propDict => $userAttribute) {
            $uID = $userAttribute['id'];
        }
        return $uID;
    }

    private function getValueFromScimFormat($value, $dataPost)
    {
        if ($value == 'TODAY()') {
            $nowString = Carbon::now()->format('Y/m/d');
            return $nowString;
        }

        // process with regular expressions?
        $regExpManagement = new RegExpsManager();
        $columnName = $regExpManagement->checkRegExpRecord($value);
        if (isset($columnName)) {
            $findData = (array)(new JSONPath($dataPost))->find($columnName);
            $array_val = array_values($findData);

            // If settings is correct, return regexped SCIM attribute.
            $ret = '';
            if (count($array_val[0]) > 0) {
                $scimValue = $array_val[0][0];
                $ret = $regExpManagement->convertDataFollowSetting($value, $scimValue);
            }
            return $ret;
        }

        // Return Enterprise attribute 
        if (in_array($value, self::USER_ENTERPRISE_ATTRS)) {
            $entAttr = $dataPost[self::SCIM_ENT];

            $pattern = "/\((.*?)\)/";
            preg_match($pattern, $value, $matchedValue);
            return $entAttr[$matchedValue[1]];
        }

        $pattern = "/\((.*?)\)/";
        $isMatched = preg_match($pattern, $value, $matchedValue);

        $phoneStr = "phoneNumbers";
        $addressStr = "addresses";
        $emailStr = "emails";

        try {
            if ($isMatched) {
                // take a phone number
                if (strncmp($matchedValue[1], $phoneStr, strlen($phoneStr)) === 0) {
                    return $this->getPhoneNumber($matchedValue[1], $dataPost[$phoneStr]);
                }

                // take a address info
                if (strncmp($matchedValue[1], $addressStr, strlen($addressStr)) === 0) {
                    return $this->getAddressInfo($matchedValue[1], $dataPost[$addressStr]);
                }

                // take a email address
                if (strncmp($matchedValue[1], $emailStr, strlen($emailStr)) === 0) {
                    return $this->getMailAddress($matchedValue[1], $dataPost[$emailStr]);
                }

                // take a enterprise attribute
                if (strncmp($matchedValue[1], self::SCIM_ENT, strlen(self::SCIM_ENT)) === 0) {
                    return $this->getEnterpriseAttribute($matchedValue[1], $dataPost[self::SCIM_ENT]);
                }

                $findData = (new JSONPath($dataPost))->find($matchedValue[1]);
                //If get data from SCIM: DeleteFlag = (active)
                //active = false -> DeleteFlag =1
                //So flip the value
                if ($value == "(active)")
                    return isset($findData[0]) ? (string)(((int)$findData[0] + 1) % 2) : null;
                return isset($findData[0]) ? $findData[0] : null;
            } else {
                // Return Fixed value
                return $value;
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }

        return null;
    }

    public function getPhoneNumber($phoneType, $phones)
    {
        $pattern = "/phoneNumbers\[type eq '(.*)'\]\.(.*)/";
        $isMatched = preg_match($pattern, $phoneType, $matchedValue);

        $retValue = null;
        if ($isMatched) {
            foreach ($phones as $phone) {
                if ($phone['type'] === $matchedValue[1]) {
                    $retValue = $phone[$matchedValue[2]];
                }
            }
        }
        return $retValue;
    }

    public function getAddressInfo($addressType, $addresses)
    {
        $pattern = "/addresses\[type eq '(.*)'\]\.(.*)/";
        $isMatched = preg_match($pattern, $addressType, $matchedValue);

        $retValue = null;
        if ($isMatched) {
            foreach ($addresses as $address) {
                if ($address['type'] === $matchedValue[1]) {
                    $retValue = $address[$matchedValue[2]];
                }
            }
        }
        return $retValue;
    }

    public function getMailAddress($emailType, $emails)
    {
        $pattern = "/emails\[type eq '(.*)'\]\.(.*)/";
        $isMatched = preg_match($pattern, $emailType, $matchedValue);

        $retValue = null;
        if ($isMatched) {
            foreach ($emails as $email) {
                if ($email['type'] === $matchedValue[1]) {
                    $retValue = $email[$matchedValue[2]];
                }
            }
        }
        return $retValue;
    }

    public function getEnterpriseAttribute($entAttrType, $entAttrs)
    {
        $entAttr = str_replace(self::SCIM_ENT . ':', '', $entAttrType);
        $retValue = $entAttrs[$entAttr];
        return $retValue;
    }

    public function updateRsource($memberId, $inputRequest, $setting)
    {
        $resourceType = $this->getTableName($setting);
        $operations = $inputRequest['Operations'];
        $pathToColumn = $this->getScimPathToColumnMap($setting);

        // Bug fix: UpdateUserID not contain SCIM Object
        $formatConversions = $setting['SCIM Input Format Conversion'];
        $UpdateUserID = null;
        foreach ($formatConversions as $fcKey => $fcValue) {
            if ($fcKey == 'User.UpdateUserID') {
                $UpdateUserID = $fcValue;
            }
        }

        if (count($pathToColumn) < 1) {
            return false;
        }

        $formattedOperations = $this->getFormatedOperationsFromRequest($operations);

        foreach ($formattedOperations as $operation) {
            //Update user attribute. Replace or Add is both ok.
            if (in_array(array_get($operation, 'op'), ["Replace", "Add"])) //
            {
                // Convert Operation double coat to single coat
                $operation['path'] = str_replace('"', "'", $operation['path']);

                if (isset($pathToColumn[array_get($operation, 'path')])) {
                    $mapColumnsInDB = $pathToColumn[array_get($operation, 'path')];
                    foreach ($mapColumnsInDB as $column) {
                        // Bugfix : logical delete flag value is not boolean
                        if ($column == 'LockFlag') {
                            if ($operation['value'] == 'False') {
                                $operation['value'] = 1;
                            } else {
                                $operation['value'] = 0;
                            }
                        }
                        $this->updateSCIMResource($memberId, [$column => $operation['value']], $resourceType);
                    }
                }
            }
        }

        // Bugfix : UpdateDate and UpdateUserID columns not updated
        $nowString = Carbon::now()->format('Y/m/d');
        $this->updateSCIMResource($memberId, ['UpdateDate' => $nowString], $resourceType);
        if (!empty($UpdateUserID)) {
            $this->updateSCIMResource($memberId, ['UpdateUserID' => $UpdateUserID], $resourceType);
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
        $tableKey = $importSetting->getTableKey();
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
        // $setValues["RoleID1"] = $groupId;
        foreach ($roleMap as $index => $role) {
            if ($role['ID'] == $groupId) {
                $setValues["RoleFlag-$index"] = $isAdd ? 1 : 0;
                break;
            }
        }

        if (count($setValues)) {
            $userRecord = (array)DB::table($tableName)->where($tableKey, $memberId)->get([$this->updateFlagsColumnName])->toArray()[0];
            $updateFlags = json_decode($userRecord[$this->updateFlagsColumnName], true);
            array_walk($updateFlags, function (&$value) {
                $value = 1;
            });
            $setValues[$this->updateFlagsColumnName] = json_encode($updateFlags);
            DB::table($tableName)->where($tableKey, $memberId)->update($setValues);
            return true;
        } else {
            return false;
        }
    }

    private function updateSCIMResource(string $memberId, array $values, $resourceType)
    {
        $setValues = $values;
        //Set DeleteFlag to 1 if active = false

        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        foreach ($setValues as $column => $value) {

            $chkEncField = $resourceType . '.' . $column;
            if (in_array($chkEncField, $getEncryptedFields)) {
                $setValues[$column] = $settingManagement->passwordEncrypt($value);
            }

            if ($column === $this->deleteFlagColumnName) { //If this is patch of deleting user, reset all roleFlags to 0
                $roleFlagColumns = $this->settingImport->getRoleFlags();
                if ($value === "False" || $value === "1") {
                    $setValues[$column] = 1;
                    // $setValues[$column] = $value==="False"?1:0;
                    foreach ($roleFlagColumns as $roleFlagColumn) {
                        $setValues[$roleFlagColumn] = 0;
                    }
                } else {
                    $setValues[$column] = 0;
                }
            }
        }

        $settingManagement = new SettingsManager();
        $setValues[$this->updateFlagsColumnName] = $settingManagement->makeUpdateFlagsJson($resourceType);

        DB::table($resourceType)->where($this->primaryKey, $memberId)->update($setValues);
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
                    $results[$matchedValue[1]] = [$shortColumnName];
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
            // $operation['path'] = preg_replace("/\[[^)]+\]/", "", $operation['path']);
            return $operation;
        }, $operations);

        //When deleteing user, ignore changing other things.
        $deleteUserPatch =
            [
                'op' => "Replace",
                'path' => "active",
                'value' => "False"
            ];

        foreach ($formattedOperations as $op) {
            if ($op === $deleteUserPatch) {
                $formattedOperations = [$deleteUserPatch];
                break;
            }
        }
        return $formattedOperations;
    }
}
