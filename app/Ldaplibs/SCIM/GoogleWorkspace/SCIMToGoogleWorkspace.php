<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use App\Commons\Consts;
use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\GoogleWorkspace\Group;
use App\Ldaplibs\SCIM\GoogleWorkspace\Member;
use App\Ldaplibs\SCIM\GoogleWorkspace\Organization;
use App\Ldaplibs\SCIM\GoogleWorkspace\Role;
use App\Ldaplibs\SCIM\GoogleWorkspace\RoleAssignment;
use App\Ldaplibs\SCIM\GoogleWorkspace\User;
use Google\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SCIMToGoogleWorkspace
{
    protected $setting;

    private $externalIdName;
    private $client;
    private $data;
    private $customerId;

    /**
     * SCIMToGoogleWorkspace constructor.
     * @param $setting
     */
    public function __construct()
    {
    }

    public function initialize($setting, $externalIdName)
    {
        $this->setting = $setting;
        $this->externalIdName = $externalIdName;
        $this->settingManagement = new SettingsManager();

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["GoogleWorkspace Keys"];
        $credentialJson = $scimOptions["credentialJson"];
        $tokenJson = $scimOptions["tokenJson"];
        $this->customerId = $scimOptions["customerId"];
        $this->client = $this->getClient($credentialJson, $tokenJson);
    }

    public function getServiceName() {
        return "Google Workspace";
    }

    private function getClient($credentialJson, $tokenJson)
    {
        $client = new Client();
        $client->setAuthConfig(storage_path(Consts::JSONS_PATH . $credentialJson));

        $tokenPath = storage_path(Consts::JSONS_PATH . $tokenJson);
        if (file_exists($tokenPath) === false) {
            throw new Exception("token json file is not found.");
        }

        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                throw new Exception("refresh token is not found.");
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

    public function createResource($resourceType, $item)
    {
        $item = $this->replaceResource($resourceType, $item);

        $scimInfo = $this->settingManagement->makeScimInfo(
            "Google Workspace", "create", ucfirst(strtolower($resourceType)), $item["ID"], "", ""
        );

        try {
            if ($resourceType == "User") {
                $this->data = new User($this->client);
            } else if ($resourceType == "Group") {
                $this->data = new Group($this->client);
            } else if ($resourceType == "Organization") {
                $this->data = new Organization($this->client, $this->customerId);
            } else if ($resourceType == "Role") {
                $this->data = new Role($this->client, $this->customerId);
            }

            $this->data->setAttributes($item);
            $result = $this->data->insert();
            if ($result) {
                $this->settingManagement->detailLogger($scimInfo);
                return $this->data->getPrimaryKey($result);
            }
        } catch (\Exception $exception) {
            Log::critical("GoogleWorkspace::" . $resourceType . " [" . $item["ID"] . "] create was failed.");
            Log::critical($exception);
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    public function updateResource($resourceType, $item)
    {
        $item = $this->replaceResource($resourceType, $item);

        $scimInfo = $this->settingManagement->makeScimInfo(
            "Google Workspace", "update", ucfirst(strtolower($resourceType)), $item["ID"], "", ""
        );

        try {
            if ($resourceType == "User") {
                $this->data = new User($this->client);
            } else if ($resourceType == "Group") {
                $this->data = new Group($this->client);
            } else if ($resourceType == "Organization") {
                $this->data = new Organization($this->client, $this->customerId);
            } else if ($resourceType == "Role") {
                $this->data = new Role($this->client, $this->customerId);
            }

            $result = $this->getResourceDetail($item[$this->externalIdName], $resourceType);
            if ($result == null) {
                return null;
            }

            $this->data->setResource($result);
            $this->data->setAttributes($item);
            $result = $this->data->update($item[$this->externalIdName]);
            if ($result) {
                $this->settingManagement->detailLogger($scimInfo);
                return $this->data->getPrimaryKey($result);
            }
        } catch (\Exception $exception) {
            Log::critical("GoogleWorkspace::" . $resourceType . " [" . $item["ID"] . "] update was failed.");
            Log::critical($exception);
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    public function deleteResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "Google Workspace", "delete", ucfirst(strtolower($resourceType)), $item["ID"], "", ""
        );

        try {
            if ($resourceType == "User") {
                $this->data = new User($this->client);
            } else if ($resourceType == "Group") {
                $this->data = new Group($this->client);
            } else if ($resourceType == "Organization") {
                $this->data = new Organization($this->client, $this->customerId);
            } else if ($resourceType == "Role") {
                $this->data = new Role($this->client, $this->customerId);
            }

            $this->data->delete($item[$this->externalIdName]);
            $this->settingManagement->detailLogger($scimInfo);
            if ($resourceType == "User") {
                $this->unbindRole($item["ID"], $item[$this->externalIdName]);
            }
            return true;
        } catch (\Exception $exception) {
            Log::critical("GoogleWorkspace::" . $resourceType . " [" . $item["ID"] . "] delete was failed.");
            Log::critical($exception);
            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    private function unbindRole($id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "User_ID", $id);
        if (empty($result)) {
            return;
        }

        $roleAssignment = new RoleAssignment($this->client, $this->customerId);
        foreach ($result as $value) {
            if (empty($value["Name"]) === false) {
                try {
                    $roleAssignment->delete($value["Name"]);
                } catch (\Exception $exception) {}
            }
        }
    }

    public function passwordResource($resourceType, $item, $externalId)
    {
        return;
    }

    public function statusResource($resourceType, $item, $externalId)
    {
        return;
    }

    public function userGroup($resourceType, $item, $externalId)
    {
        $this->data = new Member($this->client);

        if ($resourceType == "User") {
            $result = $this->userGroupByUser($item["ID"], $externalId);
        } else if ($resourceType == "Group") {
            $result = $this->userGroupByGroup($item["ID"], $externalId);
        }
    }

    public function userRole($resourceType, $item, $externalId)
    {
        if ($resourceType == "User") {
            $this->userRoleByUser($item["ID"], $externalId);
        } else if ($resourceType == "Role") {
            $this->userRoleByRole($item["ID"], $externalId);
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
        try {
            $result = $this->data->get($resourceId);
        } catch (\Exception $exception) {
            $scimInfo = $this->settingManagement->makeScimInfo(
                "Google Workspace", "getResourceDetail", ucfirst(strtolower($resourceType)),
                "", "", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);
            if ($exception->getCode() == 404 && strpos($exception->getMessage(), "Resource Not Found") !== false) {
                Log::error("GoogleWorkspace::" . $resourceType . " [" . $resourceId . "] was not found.");
                return null;
            }
            throw $exception;
        }
        return $result;
    }

    private function userGroupByUser($id, $externalId)
    {
        $reg = new RegExpsManager();
        $groupIds = $reg->getGroupsInUser($id);
        if (empty($groupIds)) {
            return;
        }

        foreach ($groupIds as $groupId) {
            $extGroupId = $reg->getAttrFromID("Group", $groupId, $this->externalIdName);
            if (empty($extGroupId) || empty($extGroupId[0])) {
                // skip
                continue;
            }
            $deleteFlag = $reg->getDeleteFlagFromUserToGroup($id, $groupId);
            $result = $this->hasMember($extGroupId, $externalId);
            if ($result->getIsMember() != true && $deleteFlag != true) {
                // add
                $this->insertMember($extGroupId, $externalId);
            } else if ($result->getIsMember() == true && $deleteFlag == true) {
                // delete
                $this->deleteMember($extGroupId, $externalId);
            }
        }
    }

    private function userGroupByGroup($id, $externalId)
    {
        $reg = new RegExpsManager();
        $userIds = $reg->getUsersInGroup($externalId);
        if (empty($userIds)) {
            $externalUserIds = array();
        } else {
            $externalUserIds = $reg->getAttrFromID("User", $userIds, $this->externalIdName);
        }
        $addUserIds = $externalUserIds;
        $deleteUserIds = array();

        foreach ($userIds as $userId) {
            $deleteFlag = $reg->getDeleteFlagFromUserToGroup($userId, $externalId);
            if ($deleteFlag == true) {
                $externalUserId = $reg->getAttrFromID("User", $userId, $this->externalIdName);
                $key = array_search($externalUserId, $addUserIds);
                unset($addUserIds[$key]);
                array_push($deleteUserIds, $externalUserId);
            }
        }

        $result = $this->getResourceDetail($externalId, "Member");
        if ($result != null) {
            foreach ($result->getMembers() as $key => $value) {
                if (in_array($value->getId(), $externalUserIds) === true) {
                    $key = array_search($value->getId(), $addUserIds);
                    unset($addUserIds[$key]);
                } else {
                    array_push($deleteUserIds, $value->getId());
                }
            }
        }

        foreach ($addUserIds as $key => $value) {
            $this->insertMember($externalId, $value);
        }

        foreach ($deleteUserIds as $key => $value) {
            $this->deleteMember($externalId, $value);
        }
    }

    private function hasMember($groupKey, $userKey)
    {
        try {
            $result = $this->data->hasMember($groupKey, $userKey);
        } catch (\Exception $exception) {
            $scimInfo = $this->settingManagement->makeScimInfo(
                "Google Workspace", "hasMember", "", "", "", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);
            if ($exception->getCode() == 404 && strpos($exception->getMessage(), "Resource Not Found") !== false) {
                Log::error("GoogleWorkspace::Member [" . $groupKey . "] was not found.");
                return;
            }
            throw $exception;
        }
        return $result;
    }

    private function insertMember($groupKey, $userKey)
    {
        try {
            $this->data->insert($groupKey, $userKey);
        } catch (\Exception $exception) {
            $scimInfo = $this->settingManagement->makeScimInfo(
                "Google Workspace", "insertMember", "", "", "", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);
            if ($exception->getCode() == 409 && strpos($exception->getMessage(), "Member already exists") !== false) {
                // Member already exists
                return;
            }
            throw $exception;
        }
    }

    private function deleteMember($groupKey, $userKey)
    {
        try {
            $this->data->delete($groupKey, $userKey);
        } catch (\Exception $exception) {
            $scimInfo = $this->settingManagement->makeScimInfo(
                "Google Workspace", "deleteMember", "", "", "", $exception->getMessage()
            );
            $this->settingManagement->faildLogger($scimInfo);
            if ($exception->getCode() == 404 && strpos($exception->getMessage(), "Resource Not Found") !== false) {
                // deleted
                return;
            }
            throw $exception;
        }
    }

    private function userRoleByUser($id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "User_ID", $id);
        if (empty($result)) {
            return;
        }

        $roleAssignment = new RoleAssignment($this->client, $this->customerId);
        $params = array("userKey" => $externalId);
        $list = (array) $roleAssignment->list($params);
        $items = array();
        if (isset($list["items"]) === true) {
            foreach ($list["items"] as $item) {
                array_push($items, ((array) $item)["roleId"]);
            }
        }

        foreach ($result as $value) {
            $roleAssignmentId = null;
            if ($value["DeleteFlag"] == "0") {
                $extRoleId = $reg->getAttrFromID("Role", $value["Role_ID"], $this->externalIdName);
                if (empty($extRoleId) || empty($extRoleId[0])) {
                    continue;
                }

                // update
                if (in_array($extRoleId[0], $items) === true) {
                    continue;
                }

                // insert
                try {
                    $ra = $roleAssignment->insert($externalId, $extRoleId[0]);
                    $roleAssignmentId = $ra->getRoleAssignmentId();
                } catch (\Exception $exception) {
                    $scimInfo = $this->settingManagement->makeScimInfo(
                        "Google Workspace", "userRoleByUser", "UserToRole", "", "", $exception->getMessage()
                    );
                    $this->settingManagement->faildLogger($scimInfo);
                    if ($exception->getCode() == 404 && strpos($exception->getMessage(), "Role not found") !== false) {
                        Log::error("GoogleWorkspace::UserToRole [" . $value["Role_ID"] . "] Role not found.");
                        continue;
                    }
                    throw $exception;
                }
            } else if (empty($value["Name"]) === false && $value["DeleteFlag"] != "0") {
                // delete
                try {
                    $roleAssignment->delete($value["Name"]);
                } catch (\Exception $exception) {
                    $scimInfo = $this->settingManagement->makeScimInfo(
                        "Google Workspace", "userRoleByUser", "UserToRole", "", "", $exception->getMessage()
                    );
                    $this->settingManagement->faildLogger($scimInfo);
                    if ($exception->getCode() == 404 && strpos($exception->getMessage(), "RoleAssignment not found") !== false) {
                        Log::error("GoogleWorkspace::UserToRole [" . $value["Name"] . "] RoleAssignment not found.");
                    } else {
                        throw $exception;
                    }
                }
            } else {
                // update or deleted
                continue;
            }
            DB::beginTransaction();
            $query = DB::table("UserToRole")
                ->where("User_ID", $value["User_ID"])
                ->where("Role_ID", $value["Role_ID"])
                ->update(["Name" => $roleAssignmentId]);
            DB::commit();
        }
    }

    private function userRoleByRole($id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "Role_ID", $id);
        if (empty($result)) {
            return;
        }

        $roleAssignment = new RoleAssignment($this->client, $this->customerId);
        $params = array("roleId" => $externalId);
        $list = (array) $roleAssignment->list($params);
        $items = array();
        if (isset($list["items"]) === true) {
            foreach ($list["items"] as $item) {
                array_push($items, ((array) $item)["assignedTo"]);
            }
        }

        foreach ($result as $value) {
            $roleAssignmentId = null;
            if ($value["DeleteFlag"] == "0") {
                $extUserId = $reg->getAttrFromID("User", $value["User_ID"], $this->externalIdName);
                if (empty($extUserId) || empty($extUserId[0])) {
                    continue;
                }

                // update
                if (in_array($extUserId[0], $items) === true) {
                    continue;
                }

                // insert
                try {
                    $ra = $roleAssignment->insert($extUserId[0], $externalId);
                    $roleAssignmentId = $ra->getRoleAssignmentId();
                } catch (\Exception $exception) {
                    $scimInfo = $this->settingManagement->makeScimInfo(
                        "Google Workspace", "userRoleByRole", "UserToRole", "", "", $exception->getMessage()
                    );
                    $this->settingManagement->faildLogger($scimInfo);
                    if ($exception->getCode() == 400 && strpos($exception->getMessage(), "Required parameter: [resource.identity.identity_id]") !== false) {
                        Log::error("GoogleWorkspace::UserToRole [" . $value["User_ID"] . "] User not found.");
                        continue;
                    }
                    throw $exception;
                }
            } else if (empty($value["Name"]) === false && $value["DeleteFlag"] != "0") {
                // delete
                try {
                    $roleAssignment->delete($value["Name"]);
                } catch (\Exception $exception) {
                    $scimInfo = $this->settingManagement->makeScimInfo(
                        "Google Workspace", "userRoleByRole", "UserToRole", "", "", $exception->getMessage()
                    );
                    $this->settingManagement->faildLogger($scimInfo);
                    if ($exception->getCode() == 404 && strpos($exception->getMessage(), "RoleAssignment not found") !== false) {
                        Log::error("GoogleWorkspace::UserToRole [" . $value["Name"] . "] RoleAssignment not found.");
                    } else {
                        throw $exception;
                    }
                }
            } else {
                // update or deleted
                continue;
            }

            DB::beginTransaction();
            $query = DB::table("UserToRole")
                ->where("User_ID", $value["User_ID"])
                ->where("Role_ID", $value["Role_ID"])
                ->update(["Name" => $roleAssignmentId]);
            DB::commit();
        }
    }
}
