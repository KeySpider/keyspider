<?php
namespace App\Ldaplibs\Extract;

use App\Commons\Consts;
use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Adldap\Laravel\Facades\Adldap;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtractToLdap
{
    /**
     * define const
     * 
     * see set attribute value detail
     * https://support.microsoft.com/ja-jp/help/305144/how-to-use-useraccountcontrol-to-manipulate-user-account-properties
     */
    private const NORMAL_ACCOUNT = 544;
    private const DISABLE_ACCOUNT = 514;
    private const HOLDING_ACCOUNT = 66050;

    private const GROUP_TYPE_365 = 2;
    private const GROUP_TYPE_SECURITY = -2147483646;

    const PLUGINS_DIR = "App\\Commons\\Plugins\\";

    protected $setting;
    protected $regExpManagement;
    protected $settingManagement;
    protected $tableMaster;
    protected $provider;

    public function __construct()
    {
    }

    public function initialize($setting)
    {
        $this->setting = $setting;
        $this->tableMaster = null;
        $this->provider = null;
        $this->regExpManagement = new RegExpsManager();
        $this->settingManagement = new SettingsManager();
    }

    /**
     * Add a connection provider to Adldap.
     *
     */
    public function configureLDAPServer()
    {
        $setting =      $this->setting;
        $ldapHosts =    $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapHosts"];
        $ldapPort =     $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapPort"];
        $LdapBaseDn =   $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];
        $LdapUsername = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapUsername"];
        $LdapPassword = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapPassword"];
        $UseSSL =       $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["UseSSL"];
        $UseTLS =       $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["UseTLS"];

        $adLdap = new \Adldap\Adldap();

        $config = [
            "hosts"    => explode(",", $ldapHosts),
            "port"     => $ldapPort,
            "base_dn"  => $LdapBaseDn,
            "username" => $LdapUsername,
            "password" => $LdapPassword,
            "use_ssl"  => ($UseSSL == true),
            "use_tls"  => ($UseTLS == true),
        ];

        // Add a connection provider to Adldap.
        $adLdap->addProvider($config);

        return $adLdap;
    }

    public function extractCondition2($extractCondition, $nameColumnUpdate)
    {
        $whereData = [];
        foreach ($extractCondition as $key => &$condition) {
            if (!is_array($condition)) {
                // Add/Sub EffectiveDate
                if (strpos((string)$condition, "TODAY()") !== false) {
                    $condition = $this->regExpManagement->getEffectiveDate($condition);
                    array_push($whereData, [$key, "<=", $condition]);
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
            if ($condition === "TODAY() + 7") {
                $condition = Carbon::now()->addDay(7)->format("Y/m/d");
                array_push($whereData, [$key, "<=", $condition]);
            } elseif (is_array($condition)) {
                // JSON Columns(Use UpdateFlags)
                foreach ($condition as $keyJson => $valueJson) {
                    array_push($whereData, ["{$nameColumnUpdate}->$keyJson", "=", "{$valueJson}"]);
                }
                continue;
            } else {
                array_push($whereData, [$key, "=", $condition]);
            }
        }
        return $whereData;
    }    

    private function conversionArrayValue($array)
    {
        $setting = $this->setting;
        $conversions = $setting[Consts::EXTRACTION_PROCESS_FORMAT_CONVERSION];

        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $regExpManagement = new RegExpsManager();

        $prefixArray = $this->array_key_prefix($array, $this->tableMaster);
        
        $fields = [];

        foreach ($conversions as $key => $nameTable) {
            $explodeItem = explode(".", $nameTable);
            $item = $explodeItem[count($explodeItem) -1];

            if ($explodeItem[0] == "(".$this->tableMaster){
                $item = $nameTable;
            }

            if ($item === "admin") {
                $fields[$key] = "admin";
            } elseif ($item === "TODAY()") {
                $fields[$key] = Carbon::now()->format("Y/m/d");
            } elseif ($item === "0") {
                $fields[$key] = "0";
            } else {
                preg_match("/^(\w+)\.(\w+)\(([\w\.\,]*)\)$/", $nameTable, $matches);
                if (!empty($matches)) {
                    $fields[$key] = $this->executeExtend($nameTable, $matches, $array["ID"]);
                    continue;
                }
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

    private function array_key_prefix(array $array, $prefix = "")
    {
        $result = array();
        foreach ($array as $key => $value) {
            $result[$prefix . "." . $key] = $value;
        }
        return $result;
    }

    public function exportUserFromKeyspider($user)
    {
        try {
            $setting = $this->setting;
            $ldapSearchPath =  $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapSearchPath"];
            $ldapSearchColum = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapSearchColum"];
            $ldapBasePath =    $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];
            $useSSL =          $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["UseSSL"];
            $defaultPassword = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION]["DefaultPassword"];

            $ldapBaseDn = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];

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
                if ($user["DeleteFlag"] == "1") {
                    $ldapUser["userAccountControl"] = self::DISABLE_ACCOUNT;
                }
                if (array_key_exists("objectclass", $ldapUser)) {
                    unset($ldapUser["objectclass"]);
                }
                $is_success = $entry->update($ldapUser);
            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                // $ldapUser["userAccountControl"] = self::HOLDING_ACCOUNT;
                // $ldapUser["userAccountControl"] = self::NORMAL_ACCOUNT;
                // $ldapUser["pwdLastSet"] = 0;

                $entry =  $this->provider->make()->user();
                foreach ($ldapUser as $key => $value) {
                    if ($key == "dn") {
                        $entry->setAttribute($key, "${ldapSearchPath}=${value},${ldapBaseDn}");
                    } else if ($key == "objectclass") {
                        $entry->setAttribute($key, explode(",", $value));
                    } else {
                        $entry->setAttribute($key, $value);
                    }
                }

                if ($useSSL) {
                    $entry->setPassword($defaultPassword);
                }
                $is_success = $entry->save();

//                if ($is_success) {
//                    $is_success = $entry->update($ldapUser);    
//                }
            }

            // remove & add memberOf
            $removeGroups = $entry->getGroups();
            foreach ($removeGroups as $group) {
                $entry->removeGroup($group);
            }

            $addGroups = $this->getMemberOfLDAP($user["ID"]);
            foreach ($addGroups as $index => $group) {
                $fmt = sprintf("CN=%s,%s", $group, $ldapBaseDn);
                $entry->addGroup($fmt);
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($user["ID"]);
            }
        } catch (\Adldap\Auth\BindException $e) {
            Log::error($e);
            // There was an issue binding / connecting to the server.
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    private function getMemberOfLDAP($uid)
    {
        $table = "UserToGroup";
        $queries = DB::table($table)
                    ->select("Group_ID")
                    ->where("User_ID", $uid)
                    ->where("DeleteFlag", "0")->get();

        $groupIds = [];
        foreach ($queries as $key => $value) {
            $groupIds[] = $value->Group_ID;
        }

        $table = "Group";
        $queries = DB::table($table)
                    ->select("displayName")
                    ->whereIn("ID", $groupIds)
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
            $ldapBasePath = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];

            $ldapGroup = $this->conversionArrayValue($data);

            $ldapGroup["groupType"] = self::GROUP_TYPE_365;
            if (!empty($data["groupTypes"])) {
                if ($data["groupTypes"] == "Security") {
                    $ldapGroup["groupType"] = self::GROUP_TYPE_SECURITY;
                }
            }

            // Finding a record.
            try {
                $group = $this->provider->search()->groups()->find($data["displayName"]);
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info(sprintf("Group wasn't found! : %s = %s", $ldapBasePath, $data["displayName"]));
                $group = false;
            }

            $is_success = false;
            if ($group) {
               // Update or Delete LDAP entry. Setting a model's attribute.
                // $is_success = $group->update($ldapGroup);
                try {
                    $is_success = $group->update($ldapGroup);
                } catch (Exception $exception) {
                    Log::info($ldapGroup);
                    Log::error($exception);
                    $is_success = false;
                }

                // disble user
                if ($data["DeleteFlag"] == "1") {
                    $group->delete();
                }

            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                // $group =  $this->provider->make()->group([
                //     "cn"     => $ldapGroup["cn"],
                // ]);
                $group =  $this->provider->make()->group();
                foreach ($ldapGroup as $key => $value) {
                    if ($key == "dn") {
                        $group->setAttribute($key, "${ldapSearchPath}=${value},${ldapBaseDn}");
                    } else if ($key == "objectclass") {
                        $group->setAttribute($key, explode(",", $value));
                    } else {
                        $group->setAttribute($key, $value);
                    }
                }

                $is_success = $group->save();
                if ($is_success) {
                    try {
                        $is_success = $group->update($ldapGroup);
                    } catch (Exception $exception) {
                        Log::info($ldapGroup);
                        Log::error($exception);
                        $is_success = false;
                    }
                }
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($data["ID"]);
            }
        } catch (\Adldap\Auth\BindException $e) {
            Log::error($e);
            // There was an issue binding / connecting to the server.
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    public function exportOrganizationUnitFromKeyspider($data)
    {
        try {
            $setting = $this->setting;
            $ldapBasePath = $setting[Consts::EXTRACTION_LDAP_CONNECTING_CONFIGURATION]["LdapBaseDn"];
            $ldapOu = $this->conversionArrayValue($data);

            // Finding a record.
            try {
                $ou = $this->provider->search()->ous()->find($data["Name"]);
            } catch (Exception $exception) {
                // Record wasn't found!
                Log::info(sprintf("Organization wasn't found! : [%s] = [%s]", $ldapBasePath, $data["Name"]));
                $ou = false;
            }

            $is_success = false;
            if ($ou) {
               // Update or Delete LDAP entry. Setting a model's attribute.
                $is_success = $ou->update($ldapOu);
                // disble ou
                if ($data["DeleteFlag"] == "1") {
                    $ou->delete();
                }

            } else {
                // Creating a new LDAP entry. You can pass in attributes into the make methods.
                $rdn = sprintf("ou=%s,%s", $ldapOu["cn"], $ldapBasePath);
                $ou =  $this->provider->make()->ou([
                    "cn"     => $ldapOu["cn"],
                    "dn"     => $rdn,
                ]);
                $is_success = $ou->save();
            }

            // Saving the changes to your LDAP server.
            if ($is_success) {
                // User was saved!
                $this->resetTransferredFlag($data["ID"], true);
            }
        } catch (\Adldap\Auth\BindException $e) {
            Log::error($e);
            // There was an issue binding / connecting to the server.
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    private function resetTransferredFlag($id, $ouFlag = false)
    {
        $this->setUpdateFlags2($id);
    }

    public function setUpdateFlags2($id)
    {
        $setting = $this->setting;
        $itemTable = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_TABLE];
        $addFlagName = $setting[Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION][Consts::EXTRACTION_PROCESS_ID];

        try {
            DB::beginTransaction();
            $userRecord = DB::table($itemTable)->where("ID", $id)->first();

            $userRecord = (array)DB::table($itemTable)
                ->where("ID", $id)
                ->get(["UpdateFlags"])->toArray()[0];
    
            $updateFlags = json_decode($userRecord["UpdateFlags"], true);
            $updateFlags[$addFlagName] = "0";
            $setValues["UpdateFlags"] = json_encode($updateFlags);
    
            DB::table($itemTable)->where("ID", $id)
                ->update($setValues);
            DB::commit();
    
        } catch (Exception $e) {
            DB::rollback();
            Log::error($e);
        }
    }

    public function setProvider($provider) {
        $this->provider = $provider;
    }

    public function setTableMaster($tableMaster) {
        $this->tableMaster = $tableMaster;
    }

    public function getTableMaster() {
        return $this->tableMaster;
    }

}
