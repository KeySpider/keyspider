<?php

namespace App\Ldaplibs\SCIM\OneLogin;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\OneLogin\Client;
use App\Ldaplibs\SCIM\OneLogin\Role;
use App\Ldaplibs\SCIM\OneLogin\User;
use Illuminate\Support\Facades\Log;

class SCIMToOneLogin
{
    protected $setting;

    private $externalIdName;
    private $client;
    private $data;

    /**
     * SCIMToOneLogin constructor.
     */
    public function __construct()
    {
    }

    public function initialize($setting, $externalIdName)
    {
        $this->setting = $setting;
        $this->externalIdName = $externalIdName;
        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["OneLogin Keys"];
        $clientId = $scimOptions["clientId"];
        $clientSecret = $scimOptions["clientSecret"];
        $region = $scimOptions["region"];
        $this->client = new Client($clientId, $clientSecret, $region);
        $this->settingManagement = new SettingsManager();
    }

    public function getServiceName() {
        return "OneLogin";
    }

    public function createResource($resourceType, $item)
    {
        $item = $this->replaceResource($resourceType, $item);

        if ($resourceType == "User") {
            $this->data = new User($this->client);
        } else if ($resourceType == "Role") {
            $this->data = new Role($this->client);
        }

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "create", ucfirst(strtolower($resourceType)), $item["ID"], "", ""
        );

        try {
            $this->data->setAttributes($item);
            $result = $this->data->create();

            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::" . $resourceType . " [" . $item["ID"] . "] create was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                $scimInfo["message"] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
                return null;
            }
            $this->settingManagement->detailLogger($scimInfo);

            return $this->data->getPrimaryKey($result);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    public function updateResource($resourceType, $item)
    {
        $item = $this->replaceResource($resourceType, $item);

        if ($resourceType == "User") {
            $this->data = new User($this->client);
        } else if ($resourceType == "Role") {
            $this->data = new Role($this->client);
        }

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "update", ucfirst(strtolower($resourceType)), $item["ID"], "", ""
        );

        try {
            $result = $this->getResourceDetail($item[$this->externalIdName], $resourceType);
            if ($result == null) {
                return null;
            }
            $this->data->setResource($result);
            $this->data->setAttributes($item);
            $result = $this->data->update($item[$this->externalIdName]);

            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::" . $resourceType . " [" . $item["ID"] . "] update was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                $scimInfo["message"] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
                return null;
            }

            $this->settingManagement->detailLogger($scimInfo);
            return $this->data->getPrimaryKey($result);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    public function deleteResource($resourceType, $item)
    {
        if ($resourceType == "User") {
            $this->data = new User($this->client);
        } else if ($resourceType == "Role") {
            $this->data = new Role($this->client);
        }

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "delete", ucfirst(strtolower($resourceType)), $item["ID"], "", ""
        );

        try {
            $this->data->delete($item[$this->externalIdName]);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::" . $resourceType . " [" . $item["ID"] . "] delete was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                return null;
            }

            $this->settingManagement->detailLogger($scimInfo);
            // return $item[$this->externalIdName];
            return true;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    public function passwordResource($resourceType, $item, $externalId)
    {
        if ($resourceType != "User" || !array_key_exists("password", $item)) {
            return;
        }

        $item = $this->replaceResource($resourceType, $item);

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "passwordResource", "User", $item["ID"], "", ""
        );

        try {
            $password = $item["password"];
            $this->data->setPassword($externalId, $password);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::User [" . $item["ID"] . "] password set was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                $scimInfo["message"] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
    }

    public function statusResource($resourceType, $item, $externalId)
    {
        if ($resourceType != "User" || !array_key_exists("status", $item)) {
            return;
        }

        $item = $this->replaceResource($resourceType, $item);

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "statusResource", "User", $item["ID"], "", ""
        );

        try {
            $status = $item["status"];
            $value = 1;
            if ($status == "1") {
                $value = 3;
            }
            $this->data->setStatus($externalId, $value);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::User [" . $item["ID"] . "] set status was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                $scimInfo["message"] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
    }

    public function userGroup($resourceType, $item, $externalId)
    {
        return;
    }

    public function userRole($resourceType, $item, $externalId)
    {
        if ($resourceType == "User") {
            $this->userToRoleFromUser($item["ID"], $externalId);
        } else if ($resourceType == "Role") {
            $this->userToRoleFromRole($item["ID"], $externalId);
        }
    }

    private function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        foreach ($item as $key => $value) {
            $twColumn = "$resourceType.$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $item[$key] = $settingManagement->passwordDecrypt($value);
            }
        }
        return $item;
    }

    private function getResourceDetail($resourceId, $resourceType)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "getResourceDetail", ucfirst(strtolower($resourceType)), $resourceId, "", ""
        );

        try {
            $result = $this->data->get($resourceId);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::" . $resourceType . " [" . $resourceId . "] was not found."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                $scimInfo["message"] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
                return null;
            }

            if ($resourceType == "User") {
                return $result->getUserParams();
            } else if ($resourceType == "Role") {
                return array("name" => $result->getName());
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    private function userToRoleFromUser($id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "User_ID", $id);
        if (empty($result) === true) {
            return;
        }

        $assignedRoleIds = $this->data->getUserRoles($externalId);
        $addRoleIds = array();
        $deleteRoleIds = array();
        foreach ($result as $value) {
            $extRoleId = $reg->getAttrFromID("Role", $value["Role_ID"], $this->externalIdName);
            if (empty($extRoleId[0]) === true) {
                continue;
            }
            if (in_array($extRoleId[0], $assignedRoleIds) === false && $value["DeleteFlag"] == "0") {
                array_push($addRoleIds, (int) $extRoleId[0]);
            } else if (in_array($extRoleId[0], $assignedRoleIds) === true && $value["DeleteFlag"] == "1") {
                array_push($deleteRoleIds, (int) $extRoleId[0]);
            }
        }

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "userToRoleFromUser", "UserToRole", $id, "", ""
        );

        try {
            if (empty($addRoleIds) === false) {
                $this->data->assignRoleToUser($externalId, $addRoleIds);
                if (empty($this->data->getError()) === false) {
                    Log::critical("OneLogin::User [" . $id . "] assign role was failed."
                        . ' reason "' . $this->data->getErrorDescription() . '"');
                    $scimInfo["message"] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
                }
            }

            if (empty($deleteRoleIds) === false) {
                $this->data->removeRoleFromUser($externalId, $deleteRoleIds);
                if (empty($this->data->getError()) === false) {
                    Log::critical("OneLogin::User [" . $id . "] remove role was failed."
                        . ' reason "' . $this->data->getErrorDescription() . '"');
                    $scimInfo["message"] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
                }
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    private function userToRoleFromRole($id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "Role_ID", $id);
        if (empty($result) === true) {
            return;
        }

        $assignedUserIds = array();
        $tmp = $this->data->getRoleUsers($externalId);
        if (empty($tmp) === false) {
            foreach ($tmp as $value) {
                array_push($assignedUserIds, (int) $value->id);
            }
        }

        $addUserIds = array();
        $deleteUserIds = array();
        foreach ($result as $value) {
            $extUserId = $reg->getAttrFromID("User", $value["User_ID"], $this->externalIdName);
            if (empty($extUserId[0]) === true) {
                continue;
            }
            if (in_array($extUserId[0], $assignedUserIds) === false && $value["DeleteFlag"] == "0") {
                array_push($addUserIds, (int) $extUserId[0]);
            } else if (in_array($extUserId[0], $assignedUserIds) === true && $value["DeleteFlag"] == "1") {
                array_push($deleteUserIds, (int) $extUserId[0]);
            }
        }

        $scimInfo = $this->settingManagement->makeScimInfo(
            "OneLogin", "userToRoleFromRole", "UserToRole", $id, "", ""
        );

        try {
            if (empty($addUserIds) === false) {
                $this->data->assignUserToRole($externalId, $addUserIds);
                if (empty($this->data->getError()) === false) {
                    Log::critical("OneLogin::Role [" . $id . "] assign user was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');
                    $scimInfo["message"] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
                }
            }

            if (empty($deleteUserIds) === false) {
                $this->data->removeUserFromRole($externalId, $deleteUserIds);
                if (empty($this->data->getError()) === false) {
                    Log::critical("OneLogin::Role [" . $id . "] remove role was failed."
                        . ' reason "' . $this->data->getErrorDescription() . '"');
                    $scimInfo["message"] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
                }
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }
}
