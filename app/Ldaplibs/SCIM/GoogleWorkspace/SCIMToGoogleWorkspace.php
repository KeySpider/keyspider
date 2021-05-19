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
    private $client;
    private $data;

    /**
     * SCIMToGoogleWorkspace constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->client = $this->getClient();
        $this->settingManagement = new SettingsManager();
    }

    private function getClient()
    {
        $client = new Client();
        $client->setAuthConfig(storage_path(Consts::JSON_PATH . $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["credentialJson"]));

        $tokenPath = storage_path(Consts::JSON_PATH . $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["tokenJson"]);
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

        // TODO：コメント行を変更してください
        $scimInfo = array(
            'provisoning' => 'Google Workspace',
            'scimMethod' => 'create',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
            if ($resourceType == "User") {
                // test source
                return;
                // $this->data = new User($this->client);
            } else if ($resourceType == "Group") {
                $this->data = new Group($this->client);
            } else if ($resourceType == "Organization") {
                $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
                $this->data = new Organization($this->client, $customerId);
            } else if ($resourceType == "Role") {
                $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
                $this->data = new Role($this->client, $customerId);
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
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    public function updateResource($resourceType, $item)
    {
        $item = $this->replaceResource($resourceType, $item);

        // TODO：コメント行を変更してください
        $scimInfo = array(
            'provisoning' => 'Google Workspace',
            'scimMethod' => 'update',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
            if ($resourceType == "User") {
                $this->data = new User($this->client);
            } else if ($resourceType == "Group") {
                $this->data = new Group($this->client);
            } else if ($resourceType == "Organization") {
                $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
                $this->data = new Organization($this->client, $customerId);
            } else if ($resourceType == "Role") {
                $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
                $this->data = new Role($this->client, $customerId);
            }

            $result = $this->getResourceDetail($item["externalGWID"], $resourceType);
            if ($result == null) {
                return null;
            }

            $this->data->setResource($result);
            $this->data->setAttributes($item);
            $result = $this->data->update($item["externalGWID"]);
            if ($result) {
                $this->settingManagement->detailLogger($scimInfo);
            return $this->data->getPrimaryKey($result);
            }
        } catch (\Exception $exception) {
            Log::critical("GoogleWorkspace::" . $resourceType . " [" . $item["ID"] . "] update was failed.");
            Log::critical($exception);
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    public function deleteResource($resourceType, $item)
    {
        // TODO：コメント行を変更してください
        $scimInfo = array(
            'provisoning' => 'Google Workspace',
            'scimMethod' => 'delete',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
            if ($resourceType == "User") {
                // test source
                return;
                // $this->data = new User($this->client);
            } else if ($resourceType == "Group") {
                $this->data = new Group($this->client);
            } else if ($resourceType == "Organization") {
                $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
                $this->data = new Organization($this->client, $customerId);
            } else if ($resourceType == "Role") {
                $this->userRole($resourceType, $item["ID"], $item["externalGWID"]);
                $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
                $this->data = new Role($this->client, $customerId);
            }

            $this->data->delete($item['externalGWID']);
            $this->settingManagement->detailLogger($scimInfo);
            return $item['externalGWID'];
        } catch (\Exception $exception) {
            Log::critical("GoogleWorkspace::" . $resourceType . " [" . $item["ID"] . "] delete was failed.");
            Log::critical($exception);

            // TODO：コメント行を変更してください
            $scimInfo = array(
                'provisoning' => 'Google Workspace',
                'scimMethod' => 'delete',
                'table' => ucfirst(strtolower($resourceType)),
                'itemId' => $item['ID'],
                'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                'message' => $exception->getMessage(),
            );

            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    public function replaceResource($resourceType, $item)
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

    public function getResourceDetail($resourceId, $resourceType)
    {
        try {
            $result = $this->data->get($resourceId);
        } catch (\Exception $exception) {
            if ($exception->getCode() == 404 && strpos($exception->getMessage(), 'Resource Not Found') !== false) {
                Log::error('GoogleWorkspace::' . $resourceType . ' [' . $resourceId . '] was not found.');

                $scimInfo = array(
                    'provisoning' => 'Google Workspace',
                    'scimMethod' => 'getResourceDetail',
                    'table' => ucfirst(strtolower($resourceType)),
                    'itemId' => '',  // $item['ID'],
                    'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                    'message' => $exception->getMessage(),
                );
                $this->settingManagement->faildLogger($scimInfo);
                return null;
            }
            $scimInfo = array(
                'provisoning' => 'Google Workspace',
                'scimMethod' => 'getResourceDetail',
                'table' => ucfirst(strtolower($resourceType)),
                'itemId' => '',  // $item['ID'],
                'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            throw $exception;
        }
        return $result;
    }

    public function userGroup($resourceType, $item, $id)
    {
        $this->data = new Member($this->client);

        if ($resourceType == "User") {
            $result = $this->userGroupByUser($item, $id);
        } else if ($resourceType == "Group") {
            $result = $this->userGroupByGroup($item, $id);
        }
    }

    public function userGroupByUser($item, $id)
    {
        $reg = new RegExpsManager();
        $groupIds = $reg->getGroupsInUser($item["ID"]);
        if (empty($groupIds)) {
            return;
        }

        foreach ($groupIds as $groupId) {
            $extGroupId = $reg->getAttrFromID("Group", $groupId, "externalGWID");
            if (empty($extGroupId) || empty($extGroupId[0])) {
                // skip
                continue;
            }
            $deleteFlag = $reg->getDeleteFlagFromUserToGroup($item["ID"], $groupId);
            $result = $this->hasMember($extGroupId, $id);
            if ($result->getIsMember() != true && $deleteFlag != true) {
                // add
                $this->insertMember($extGroupId, $id);
            } else if ($result->getIsMember() == true && $deleteFlag == true) {
                // delete
                $this->deleteMember($extGroupId, $id);
            }
        }
    }

    public function userGroupByGroup($item, $id)
    {
        $reg = new RegExpsManager();
        $userIds = $reg->getUsersInGroup($item["ID"]);
        if (empty($userIds)) {
            $externalUserIds = array();
        } else {
            $externalUserIds = $reg->getAttrFromID("User", $userIds, "externalGWID");
        }
        $addUserIds = $externalUserIds;
        $deleteUserIds = array();

        foreach ($userIds as $userId) {
            $deleteFlag = $reg->getDeleteFlagFromUserToGroup($userId, $item["ID"]);
            if ($deleteFlag == true) {
                $externalUserId = $reg->getAttrFromID("User", $userId, "externalGWID");
                $key = array_search($externalUserId, $addUserIds);
                unset($addUserIds[$key]);
                array_push($deleteUserIds, $externalUserId);
            }
        }

        $result = $this->getResourceDetail($id, "Member");
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
            $this->insertMember($id, $value);
        }

        foreach ($deleteUserIds as $key => $value) {
            $this->deleteMember($id, $value);
        }
    }

    public function hasMember($groupKey, $userKey)
    {
        try {
            $result = $this->data->hasMember($groupKey, $userKey);
        } catch (\Exception $exception) {
            if ($exception->getCode() == 404 && strpos($exception->getMessage(), 'Resource Not Found') !== false) {
                Log::error('GoogleWorkspace::Member [' . $groupKey . '] was not found.');
                $scimInfo = array(
                    'provisoning' => 'Google Workspace',
                    'scimMethod' => 'hasMember',
                    'table' => '',  // ucfirst(strtolower($resourceType)),
                    'itemId' => '',  // $item['ID'],
                    'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                    'message' => $exception->getMessage(),
                );
                $this->settingManagement->faildLogger($scimInfo);
                return;
            }
            $scimInfo = array(
                'provisoning' => 'Google Workspace',
                'scimMethod' => 'hasMember',
                'table' => '',  // ucfirst(strtolower($resourceType)),
                'itemId' => '',  // $item['ID'],
                'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            throw $exception;
        }
        return $result;
    }

    public function insertMember($groupKey, $userKey)
    {
        try {
            $this->data->insert($groupKey, $userKey);
        } catch (\Exception $exception) {
            if ($exception->getCode() == 409 && strpos($exception->getMessage(), 'Member already exists') !== false) {
                // Member already exists
                $scimInfo = array(
                    'provisoning' => 'Google Workspace',
                    'scimMethod' => 'insertMember',
                    'table' => '',  // ucfirst(strtolower($resourceType)),
                    'itemId' => '',  // $item['ID'],
                    'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                    'message' => $exception->getMessage(),
                );
                $this->settingManagement->faildLogger($scimInfo);
                return;
            }
            $scimInfo = array(
                'provisoning' => 'Google Workspace',
                'scimMethod' => 'insertMember',
                'table' => '',  // ucfirst(strtolower($resourceType)),
                'itemId' => '',  // $item['ID'],
                'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            throw $exception;
        }
    }

    public function deleteMember($groupKey, $userKey)
    {
        try {
            $this->data->delete($groupKey, $userKey);
        } catch (\Exception $exception) {
            if ($exception->getCode() == 404 && strpos($exception->getMessage(), 'Resource Not Found') !== false) {
                // deleted
                $scimInfo = array(
                    'provisoning' => 'Google Workspace',
                    'scimMethod' => 'deleteMember',
                    'table' => '',  // ucfirst(strtolower($resourceType)),
                    'itemId' => '',  // $item['ID'],
                    'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                    'message' => $exception->getMessage(),
                );
                $this->settingManagement->faildLogger($scimInfo);
                return;
            }
            $scimInfo = array(
                'provisoning' => 'Google Workspace',
                'scimMethod' => 'deleteMember',
                'table' => '',  // ucfirst(strtolower($resourceType)),
                'itemId' => '',  // $item['ID'],
                'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                'message' => $exception->getMessage(),
            );
            $this->settingManagement->faildLogger($scimInfo);

            throw $exception;
        }
    }

    public function userRole($resourceType, $id, $externalId)
    {
        $reg = new RegExpsManager();
        if ($resourceType == "User") {
            $this->userRoleByUser($resourceType, $id, $externalId);
        } else if ($resourceType == "Role") {
            $this->userRoleByRole($resourceType, $id, $externalId);
        }
    }

    public function userRoleByUser($resourceType, $id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "User_ID", $id);
        if (empty($result)) {
            return;
        }

        $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
        $roleAssignment = new RoleAssignment($this->client, $customerId);
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
                $extRoleId = $reg->getAttrFromID("Role", $value["Role_ID"], "externalGWID");
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
                    if ($exception->getCode() == 404 && strpos($exception->getMessage(), 'Role not found') !== false) {
                        Log::error('GoogleWorkspace::UserToRole [' . $value['Role_ID'] . '] Role not found.');
                        $scimInfo = array(
                            'provisoning' => 'Google Workspace',
                            'scimMethod' => 'userRoleByUser',
                            'table' => 'UserToRole',
                            'itemId' => '',  // $item['ID'],
                            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                            'message' => $exception->getMessage(),
                        );
                        $this->settingManagement->faildLogger($scimInfo);
                        continue;
                    }
                    $scimInfo = array(
                        'provisoning' => 'Google Workspace',
                        'scimMethod' => 'userRoleByUser',
                        'table' => 'UserToRole',
                        'itemId' => '',  // $item['ID'],
                        'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                        'message' => $exception->getMessage(),
                    );
                    $this->settingManagement->faildLogger($scimInfo);

                    throw $exception;
                }
            } else if (empty($value["Name"]) === false && $value["DeleteFlag"] != "0") {
                // delete
                try {
                    $roleAssignment->delete($value["Name"]);
                } catch (\Exception $exception) {
                    if ($exception->getCode() == 404 && strpos($exception->getMessage(), 'RoleAssignment not found') !== false) {
                        Log::error('GoogleWorkspace::UserToRole [' . $value['Name'] . '] RoleAssignment not found.');
                        $scimInfo = array(
                            'provisoning' => 'Google Workspace',
                            'scimMethod' => 'userRoleByUser',
                            'table' => 'UserToRole',
                            'itemId' => '',  // $item['ID'],
                            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                            'message' => $exception->getMessage(),
                        );
                        $this->settingManagement->faildLogger($scimInfo);

                    } else {
                        $scimInfo = array(
                            'provisoning' => 'Google Workspace',
                            'scimMethod' => 'userRoleByUser',
                            'table' => 'UserToRole',
                            'itemId' => '',  // $item['ID'],
                            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                            'message' => $exception->getMessage(),
                        );
                        $this->settingManagement->faildLogger($scimInfo);

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

    public function userRoleByRole($resourceType, $id, $externalId)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "Role_ID", $id);
        if (empty($result)) {
            return;
        }

        $customerId = $this->setting[Consts::SCIM_AUTHENTICATION_CONFIGURATION]["customerId"];
        $roleAssignment = new RoleAssignment($this->client, $customerId);
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
                $extUserId = $reg->getAttrFromID("User", $value["User_ID"], "externalGWID");
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
                    if ($exception->getCode() == 400 && strpos($exception->getMessage(), 'Required parameter: [resource.identity.identity_id]') !== false) {
                        Log::error('GoogleWorkspace::UserToRole [' . $value['User_ID'] . '] User not found.');
                        $scimInfo = array(
                            'provisoning' => 'Google Workspace',
                            'scimMethod' => 'userRoleByRole',
                            'table' => 'UserToRole',
                            'itemId' => '',  // $item['ID'],
                            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                            'message' => $exception->getMessage(),
                        );
                        $this->settingManagement->faildLogger($scimInfo);

                        continue;
                    }
                    $scimInfo = array(
                        'provisoning' => 'Google Workspace',
                        'scimMethod' => 'userRoleByRole',
                        'table' => 'UserToRole',
                        'itemId' => '',  // $item['ID'],
                        'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                        'message' => $exception->getMessage(),
                    );
                    $this->settingManagement->faildLogger($scimInfo);
                    throw $exception;
                }
            } else if (empty($value["Name"]) === false && $value["DeleteFlag"] != "0") {
                // delete
                try {
                    $roleAssignment->delete($value["Name"]);
                } catch (\Exception $exception) {
                    if ($exception->getCode() == 404 && strpos($exception->getMessage(), 'RoleAssignment not found') !== false) {
                        Log::error('GoogleWorkspace::UserToRole [' . $value['Name'] . '] RoleAssignment not found.');
                        $scimInfo = array(
                            'provisoning' => 'Google Workspace',
                            'scimMethod' => 'userRoleByRole',
                            'table' => 'UserToRole',
                            'itemId' => '',  // $item['ID'],
                            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                            'message' => $exception->getMessage(),
                        );
                        $this->settingManagement->faildLogger($scimInfo);

                    } else {
                        $scimInfo = array(
                            'provisoning' => 'Google Workspace',
                            'scimMethod' => 'userRoleByRole',
                            'table' => 'UserToRole',
                            'itemId' => '',  // $item['ID'],
                            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
                            'message' => $exception->getMessage(),
                        );
                        $this->settingManagement->faildLogger($scimInfo);

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
