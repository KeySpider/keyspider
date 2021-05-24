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

namespace App\Ldaplibs\Export;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Adldap\Laravel\Facades\Adldap;

class LDAPExportor
{
    /**
     * define const
     */
    const EXTRACTION_CONDITION = 'Extraction Condition';
    const EXTRACTION_CONFIGURATION = "Extraction Process Basic Configuration";
    const EXTRACTION_PROCESS_FORMAT_CONVERSION = "Extraction Process Format Conversion";
    const LDAP_CONFIGRATION = "Extraction LDAP Connecting Configration";

    // see set attribute value detail
    // https://support.microsoft.com/ja-jp/help/305144/how-to-use-useraccountcontrol-to-manipulate-user-account-properties
    const NORMAL_ACCOUNT = 544;
    const DISABLE_ACCOUNT = 514;
    // const HOLDING_ACCOUNT = 66082;
    const HOLDING_ACCOUNT = 66050;

    const GROUP_TYPE_365 = 2;
    const GROUP_TYPE_SECURITY = -2147483646;

    protected $setting;
    protected $tableMaster;
    protected $provider;

    protected $settingManagement;

    protected $createCount;
    protected $updateCount;
    protected $deleteCount;

    /**
     * LDAPExportor constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->tableMaster = null;
        $this->provider = null;
        $this->regExpManagement = new RegExpsManager();
        $this->settingManagement = new SettingsManager();

        $this->createCount = 0;
        $this->updateCount = 0;
        $this->deleteCount = 0;
    }

    public function processExportLDAP4User()
    {
        try {
            $setting = $this->setting;
            $tableMaster = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
            $extractCondition = $setting[self::EXTRACTION_CONDITION];
            $nameColumnUpdate = 'UpdateFlags';

            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            // If a successful connection is made to your server, the provider will be returned.
            $this->provider = $this->configureLDAPServer()->connect();

            $whereData = $this->extractCondition($extractCondition, $nameColumnUpdate);
            $this->tableMaster = $tableMaster;

            $query = DB::table($tableMaster);
            $query = $query->where($whereData);

            $extractedSql = $query->toSql();
            // Log::info($extractedSql);

            $results = $query->get()->toArray();

            if ($results) {
                Log::info("Export to AD from " . $tableMaster . " entry(" . count($results) . ")");
                echo "Export to AD from " . $tableMaster . " entry(" . count($results) . ")\n";

                $this->createCount = 0;
                $this->updateCount = 0;
                $this->deleteCount = 0;
        
                foreach ($results as $data) {
                    $array = json_decode(json_encode($data), true);

                    // Skip because 'cn' cannot be created
                    if (empty($array['Name'])) {
                        if (empty($array['displayName'])) {
                            $this->setUpdateFlags($array['ID']);
                            continue;
                        }
                    }

                    switch ($this->tableMaster) {
                        case 'User':
                            $this->exportUserFromKeyspider($array);
                            break;
                            // case 'Role':
                            //     $this->exportRoleFromKeyspider($array);
                            //     break;
                        case 'Group':
                            $this->exportGroupFromKeyspider($array);
                            break;
                        case 'Organization':
                            $this->exportOrganizationUnitFromKeyspider($array);
                            break;
                    }
                }

                $scimInfo = array(
                    'provisoning' => 'LDAP',
                    'table' => $this->tableMaster,
                    'casesHandle' => count($results),
                    'createCount' => $this->createCount,
                    'updateCount' => $this->updateCount,
                    'deleteCount' => $this->deleteCount,
                );
                $settingManagement->summaryLogger($scimInfo);
    
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * Add a connection provider to Adldap.
     *
     */
    private function configureLDAPServer()
    {
        $setting = $this->setting;
        $ldapHosts =    $setting[self::LDAP_CONFIGRATION]['LdapHosts'];
        $LdapBaseDn =   $setting[self::LDAP_CONFIGRATION]['LdapBaseDn'];
        $LdapUsername = $setting[self::LDAP_CONFIGRATION]['LdapUsername'];
        $LdapPassword = $setting[self::LDAP_CONFIGRATION]['LdapPassword'];
        $UseSSL =       $setting[self::LDAP_CONFIGRATION]['UseSSL'];

        $adLdap = new \Adldap\Adldap();

        $config = [
            'hosts'    => explode(',', $ldapHosts),
            'base_dn'  => $LdapBaseDn,
            'username' => $LdapUsername,
            'password' => $LdapPassword,
            'use_tls'  => true,
        ];

        if ($UseSSL == true) {
            $config['port'] = 636;
            $config['use_ssl'] = true;
        }

        // Add a connection provider to Adldap.
        $adLdap->addProvider($config);
        return $adLdap;
    }

    public function extractCondition($extractCondition, $nameColumnUpdate)
    {
        $whereData = [];
        foreach ($extractCondition as $key => &$condition) {
            if (!is_array($condition)) {
                // Add/Sub EffectiveDate
                if (strpos((string)$condition, 'TODAY()') !== false) {
                    $condition = $this->regExpManagement->getEffectiveDate($condition);
                    array_push($whereData, [$key, '<=', $condition]);
                    continue;
                }

                // Logical operation setting or Regular expressions?
                $match = $this->regExpManagement->hasLogicalOperation($condition);
                if (!empty($match)) {
                    $whereData = $this->regExpManagement->makeExpLOCondition($key, $match, $whereData);
                    continue;
                }
            }

            // make standard condition
            if ($condition === 'TODAY() + 7') {
                $condition = Carbon::now()->addDay(7)->format('Y/m/d');
                array_push($whereData, [$key, '<=', $condition]);
            } elseif (is_array($condition)) {
                // JSON Columns(Use UpdateFlags)
                foreach ($condition as $keyJson => $valueJson) {
                    array_push($whereData, ["{$nameColumnUpdate}->$keyJson", '=', "{$valueJson}"]);
                }
                continue;
            } else {
                array_push($whereData, [$key, '=', $condition]);
            }
        }
        return $whereData;
    }

    private function conversionArrayValue($array)
    {
        $setting = $this->setting;
        $conversions = $setting[self::EXTRACTION_PROCESS_FORMAT_CONVERSION];

        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $regExpManagement = new RegExpsManager();

        $prefixArray = $this->array_key_prefix($array, $this->tableMaster);

        $fields = [];

        foreach ($conversions as $key => $nameTable) {
            preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $nameTable, $matches);
            if (!empty($matches)) {
                $fields[$key] = $this->executeExtend($nameTable, $matches, $array["ID"]);
                continue;
            }

            $explodeItem = explode('.', $nameTable);
            $item = $explodeItem[count($explodeItem) - 1];

            if ($explodeItem[0] == '(' . $this->tableMaster) {
                $item = $nameTable;
            }

            if ($item === 'admin') {
                $fields[$key] = 'admin';
            } elseif ($item === 'TODAY()') {
                $fields[$key] = Carbon::now()->format('Y/m/d');
            } elseif ($item === '0') {
                $fields[$key] = '0';
            } else {
                // Set ini file const value
                $itemValue = $item;
                // Swap const value and record value
                if (array_key_exists($nameTable, $prefixArray)) {
                    $itemValue = $array[$item];
                }

                // Need a decrypt?
                if (in_array($nameTable, $getEncryptedFields)) {
                    $itemValue = $settingManagement->passwordDecrypt($itemValue);
                }
                // process with regular expressions?
                $columnName = $regExpManagement->checkRegExpRecord($itemValue);

                if (isset($columnName)) {
                    // $slow = strtolower($columnName);
                    // if (array_key_exists($slow, $array)) {
                    if (array_key_exists($columnName, $array)) {
                        $recValue = $array[$columnName];
                        $fields[$key] = $regExpManagement->convertDataFollowSetting($itemValue, $recValue);
                    }
                } else {
                    $fields[$key] = $itemValue;
                }
            }
        }
        return $fields;
    }

    private function executeExtend($value, $matches, $id) {
        $className = self::PLUGINS_DIR . "$matches[1]";
        if (!class_exists($className)) {
            return $value;
        }
        $clazz = new $className;
        if (!method_exists($clazz, $matches[2])) {
            return $value;
        }
        $methodName = $matches[2];
        $parameters = [];
        $keys = [];
        $tableName;
        if (!empty($matches[3])) {
            $params = explode(",", $matches[3]);
            $selectColumns = [];
            foreach ($params as $param) {
                $value = explode(".", $param);
                $tableName = $value[0];
                array_push($selectColumns, $value[1]);
            }
            $queries = DB::table($tableName)
                ->select($selectColumns)
                ->where('ID', $id)->first();
            foreach ($selectColumns as $selectColumn) {
                array_push($parameters, $queries->$selectColumn);
            }
        }
        return $clazz->$methodName($parameters);
    }

    private function array_key_prefix(array $array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            $result[$prefix . '.' . $key] = $value;
        }
        return $result;
    }

    public function exportUserFromKeyspider($user)
    {
        try {
            $setting = $this->setting;
            $ldapSearchPath =  $setting[self::LDAP_CONFIGRATION]['LdapSearchPath'];
            $ldapSearchColum = $setting[self::LDAP_CONFIGRATION]['LdapSearchColum'];
            $ldapBasePath =    $setting[self::LDAP_CONFIGRATION]['LdapBaseDn'];
            $useSSL =          $setting[self::LDAP_CONFIGRATION]['UseSSL'];
            $defaultPassword = $setting[self::EXTRACTION_CONFIGURATION]['DefaultPassword'];

            $ldapBaseDn = $setting[self::LDAP_CONFIGRATION]['LdapBaseDn'];

            $scimInfo = array(
                'provisoning' => 'LDAP',
                'scimMethod' => 'create',
                'table' => 'User',
                'itemId' => $user['ID'],
                'itemName' => sprintf("%s %s", $user['surname'], $user['givenName']),
                'message' => '',
            );
    
            // Finding a record.
            try {
                $entry = $this->provider->search()
                    ->whereEquals($ldapSearchPath, $user[$ldapSearchColum])->firstOrFail();
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info("Record wasn't found! : $ldapSearchPath = $user[$ldapSearchColum]");
                $entry = false;
            }

            $ldapUser = $this->conversionArrayValue($user);

            $is_success = false;
            if ($entry) {
                // Update or Delete LDAP entry. Setting a model's attribute.
                // disble user
                if ($user['DeleteFlag'] == '1') {
                    $ldapUser['userAccountControl'] = self::DISABLE_ACCOUNT;
                    $scimInfo['scimMethod'] = 'delete';
                    $this->deleteCount++;
                } else {
                    $scimInfo['scimMethod'] = 'update';
                    $this->updateCount++;
                }
                $is_success = $entry->update($ldapUser);
            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                $ldapUser['userAccountControl'] = self::HOLDING_ACCOUNT;
                // $ldapUser['userAccountControl'] = self::NORMAL_ACCOUNT;
                // $ldapUser['pwdLastSet'] = 0;

                $entry =  $this->provider->make()->user([
                    'cn' => $ldapUser['name'],
                    'userAccountControl' => self::HOLDING_ACCOUNT,
                    // 'userAccountControl' => self::NORMAL_ACCOUNT,
                    // 'pwdLastSet' => 0,
                ]);
                if ($useSSL) {
                    $entry->setPassword($defaultPassword);
                }
                $is_success = $entry->save();

                if ($is_success) {
                    $is_success = $entry->update($ldapUser);
                }
                $scimInfo['scimMethod'] = 'create';
                $this->createCount++;
            }

            // remove & add memberOf
            $removeGroups = $entry->getGroups();
            foreach ($removeGroups as $group) {
                $entry->removeGroup($group);
            }

            $addGroups = $this->getMemberOfLDAP($user['ID']);
            foreach ($addGroups as $index => $group) {
                $fmt = sprintf("CN=%s,%s", $group, $ldapBaseDn);
                $entry->addGroup($fmt);
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($user['ID']);
            }
            $this->settingManagement->detailLogger($scimInfo);

        } catch (\Adldap\Auth\BindException $exception) {
            Log::error($exception);
            // There was an issue binding / connecting to the server.
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        } catch (Exception $exception) {
            Log::error($exception);
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
    }

    private function getMemberOfLDAP($uid)
    {
        $table = 'UserToGroup';
        $queries = DB::table($table)
            ->select('Group_ID')
            ->where('User_ID', $uid)
            ->where('DeleteFlag', '0')->get();

        $groupIds = [];
        foreach ($queries as $key => $value) {
            $groupIds[] = $value->Group_ID;
        }

        $table = 'Group';
        $queries = DB::table($table)
            ->select('displayName')
            ->whereIn('ID', $groupIds)
            ->get();

        $addGroupIDs = [];
        foreach ($queries as $key => $value) {
            $cnv = (array)$value;
            foreach ($cnv as $key => $value) {
                $addGroupIDs[] = $value;
            }
        }
        return $addGroupIDs;
    }


    public function exportGroupFromKeyspider($data)
    {
        try {
            $setting = $this->setting;
            $ldapBasePath = $setting[self::LDAP_CONFIGRATION]['LdapBaseDn'];

            $ldapGroup = $this->conversionArrayValue($data);

            $ldapGroup['groupType'] = self::GROUP_TYPE_365;
            if (!empty($data['groupTypes'])) {
                if ($data['groupTypes'] == 'Security') {
                    $ldapGroup['groupType'] = self::GROUP_TYPE_SECURITY;
                }
            }

            $scimInfo = array(
                'provisoning' => 'LDAP',
                'scimMethod' => 'create',
                'table' => 'Group',
                'itemId' => $data['ID'],
                'itemName' => $data['displayName'],
                'message' => '',
            );

            // Finding a record.
            try {
                $group = $this->provider->search()->groups()->find($data['displayName']);
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info(sprintf("Group wasn't found! : %s = %s", $ldapBasePath, $data['displayName']));
                $group = false;
            }

            $is_success = false;
            if ($group) {
                // Update or Delete LDAP entry. Setting a model's attribute.
                try {
                    $is_success = $group->update($ldapGroup);
                    $scimInfo['scimMethod'] = 'update';
                    $this->updateCount++;
                } catch (Exception $exception) {
                    Log::info($ldapGroup);
                    Log::error($exception);

                    $scimInfo['message'] = $exception->getMessage();
                    $this->settingManagement->faildLogger($scimInfo);
        
                    $is_success = false;
                }
                // disble user
                if ($data['DeleteFlag'] == '1') {
                    $group->delete();
                    $scimInfo['scimMethod'] = 'delete';
                    $this->deleteCount++;
                }
            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                $group =  $this->provider->make()->group([
                    'cn'     => $ldapGroup['cn'],
                ]);

                $is_success = $group->save();
                if ($is_success) {
                    try {
                        $is_success = $group->update($ldapGroup);
                        $scimInfo['scimMethod'] = 'create';
                        $this->createCount++;
        
                    } catch (Exception $exception) {
                        Log::info($ldapGroup);
                        Log::error($exception);

                        $scimInfo['message'] = $exception->getMessage();
                        $this->settingManagement->faildLogger($scimInfo);
            
                        $is_success = false;
                    }
                }
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($data['ID']);
            }
            $this->settingManagement->detailLogger($scimInfo);

        } catch (\Adldap\Auth\BindException $exception) {
            Log::error($exception);
            // There was an issue binding / connecting to the server.
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);

        } catch (Exception $exception) {
            Log::error($exception);
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);

        }
    }

    private function unsetArrayKeys($item)
    {
        $retArray = [];
        foreach ($item as $key => $value) {
            if (!empty($value)) {
                $retArray[$key] = $value;
            }
        }
        return $retArray;
    }

    public function exportOrganizationUnitFromKeyspider($data)
    {
        try {
            $setting = $this->setting;
            $ldapBasePath = $setting[self::LDAP_CONFIGRATION]['LdapBaseDn'];
            $ldapOu = $this->conversionArrayValue($data);

            $scimInfo = array(
                'provisoning' => 'LDAP',
                'scimMethod' => 'create',
                'table' => 'Organization',
                'itemId' => $data['ID'],
                'itemName' => $data['Name'],
                'message' => '',
            );

            // Finding a record.
            try {
                $ou = $this->provider->search()->ous()->find($data['Name']);
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info(sprintf("Organization wasn't found! : [%s] = [%s]", $ldapBasePath, $data['Name']));
                $ou = false;
            }

            $is_success = false;
            if ($ou) {
                // Update or Delete LDAP entry. Setting a model's attribute.
                $is_success = $ou->update($ldapOu);
                // disble ou
                if ($data['DeleteFlag'] == '1') {
                    $ou->delete();
                    $scimInfo['scimMethod'] = 'delete';
                    $this->deleteCount++;
                } else {
                    $scimInfo['scimMethod'] = 'update';
                    $this->updateCount++;
                }
            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                $rdn = sprintf("ou=%s,%s", $ldapOu['cn'], $ldapBasePath);
                $ou =  $this->provider->make()->ou([
                    'cn'     => $ldapOu['cn'],
                    'dn'     => $rdn,
                ]);
                $is_success = $ou->save();
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($data['ID'], true);
                $scimInfo['scimMethod'] = 'create';
                $this->createCount++;
            }
            $this->settingManagement->detailLogger($scimInfo);

        } catch (\Adldap\Auth\BindException $exception) {
            Log::error($exception);
            // There was an issue binding / connecting to the server.
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        } catch (Exception $exception) {
            Log::error($exception);
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
    }

    private function resetTransferredFlag($id, $ouFlag = false)
    {
        $this->setUpdateFlags($id);
    }

    private function setUpdateFlags($id)
    {
        $setting = $this->setting;
        $itemTable = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
        $addFlagName = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionProcessID'];

        try {
            DB::beginTransaction();
            $userRecord = DB::table($itemTable)->where("ID", $id)->first();

            $userRecord = (array)DB::table($itemTable)
                ->where("ID", $id)
                ->get(['UpdateFlags'])->toArray()[0];

            $updateFlags = json_decode($userRecord['UpdateFlags'], true);
            $updateFlags[$addFlagName] = '0';
            $setValues["UpdateFlags"] = json_encode($updateFlags);

            DB::table($itemTable)->where("ID", $id)
                ->update($setValues);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            Log::error($e);
        }
    }
}
