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
    private $client;
    private $data;

    /**
     * SCIMToGoogleWorkspace constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["OneLogin Keys"];
        $clientId = $scimOptions["clientId"];
        $clientSecret = $scimOptions["clientSecret"];
        $region = $scimOptions["region"];
        $this->client = new Client($clientId, $clientSecret, $region);
        $this->settingManagement = new SettingsManager();
    }

    public function createResource($resourceType, $item)
    {
        $item = $this->replaceResource($resourceType, $item);

        if ($resourceType == "User") {
            $this->data = new User($this->client);
        } else if ($resourceType == "Role") {
            $this->data = new Role($this->client);
        }

        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'create',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
        $this->data->setAttributes($item);
        $result = $this->data->create();

        if (empty($this->data->getError()) === false) {
            Log::critical("OneLogin::" . $resourceType . " [" . $item["ID"] . "] create was failed."
                . ' reason "' . $this->data->getErrorDescription() . '"');

                $scimInfo['message'] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
    
            return null;
        }

        $primary = $this->data->getPrimaryKey($result);
        if ($resourceType == "User") {
            $this->passwordResource($primary, $item);
            $this->userToRoleFromUser($primary, $item);
        } else if ($resourceType == "Role") {
            $this->userToRoleFromRole($primary, $item);
        }

            $this->settingManagement->detailLogger($scimInfo);
        return $primary;
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
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

        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'update',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
            $result = $this->getResourceDetail($item['externalOLID'], $resourceType);
        if ($result == null) {
            return null;
        }
        $this->data->setResource($result);
        $this->data->setAttributes($item);
        $result = $this->data->update($item["externalOLID"]);

        if (empty($this->data->getError()) === false) {
            Log::critical("OneLogin::" . $resourceType . " [" . $item["ID"] . "] update was failed."
                . ' reason "' . $this->data->getErrorDescription() . '"');

                $scimInfo['message'] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
    
            return null;
        }

        $primary = $this->data->getPrimaryKey($result);
        if ($resourceType == "User") {
            $this->passwordResource($primary, $item);
            $this->userToRoleFromUser($primary, $item);
        } else if ($resourceType == "Role") {
            $this->userToRoleFromRole($primary, $item);
        }

            $this->settingManagement->detailLogger($scimInfo);
        return $primary;

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
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

        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'delete',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
            $this->data->delete($item['externalOLID']);

        if (empty($this->data->getError()) === false) {
            Log::critical("OneLogin::" . $resourceType . " [" . $item["ID"] . "] delete was failed."
                . ' reason "' . $this->data->getErrorDescription() . '"');
            return null;
        }

            $this->settingManagement->detailLogger($scimInfo);
            return $item['externalOLID'];
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }
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
        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'getResourceDetail',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $resourceId,
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
        $result = $this->data->get($resourceId);
        if (empty($this->data->getError()) === false) {
            Log::critical("OneLogin::" . $resourceType . " [" . $resourceId . "] was not found."
                . ' reason "' . $this->data->getErrorDescription() . '"');

                $scimInfo['message'] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
    
            return null;
        }

        if ($resourceType == "User") {
            return $result->getUserParams();
        } else if ($resourceType == "Role") {
            return array(
                "name" => $result->getName(),
            );
        }
        
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }
    }

    public function passwordResource($primary, $item)
    {
        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'passwordResource',
            'table' => 'User',
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
            $password = $item['password'];
        $this->data->password($primary, $password);
        if (empty($this->data->getError()) === false) {
            Log::critical("OneLogin::User [" . $item["ID"] . "] password set was failed."
                . ' reason "' . $this->data->getErrorDescription() . '"');

                $scimInfo['message'] = $this->data->getErrorDescription();
                $this->settingManagement->faildLogger($scimInfo);
        }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
    }
    }

    public function userToRoleFromUser($primary, $item)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "User_ID", $item["ID"]);
        if (empty($result) === true) {
            return;
        }

        $assignedRoleIds = $this->data->getUserRoles($primary);
        $addRoleIds = array();
        $deleteRoleIds = array();
        foreach ($result as $value) {
            $extRoleId = $reg->getAttrFromID("Role", $value["Role_ID"], "externalOLID");
            if (empty($extRoleId[0]) === true) {
                continue;
            }
            if (in_array($extRoleId[0], $assignedRoleIds) === false && $value["DeleteFlag"] == "0") {
                array_push($addRoleIds, (int) $extRoleId[0]);
            } else if (in_array($extRoleId[0], $assignedRoleIds) === true && $value["DeleteFlag"] == "1") {
                array_push($deleteRoleIds, (int) $extRoleId[0]);
            }
        }

        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'userToRoleFromUser',
            'table' => 'UserToRole',
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
        if (empty($addRoleIds) === false) {
            $this->data->assignRoleToUser($primary, $addRoleIds);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::User [" . $item["ID"] . "] assign role was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');

                    $scimInfo['message'] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
        
            }
        }

        if (empty($deleteRoleIds) === false) {
            $this->data->removeRoleFromUser($primary, $deleteRoleIds);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::User [" . $item["ID"] . "] remove role was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');

                    $scimInfo['message'] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);

            }
        }
                
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }

    }

    public function userToRoleFromRole($primary, $item)
    {
        $reg = new RegExpsManager();
        $result = $reg->getAttrs("UserToRole", "Role_ID", $item["ID"]);
        if (empty($result) === true) {
            return;
        }

        $assignedUserIds = array();
        $tmp = $this->data->getRoleUsers($primary);
        if (empty($tmp) === false) {
            foreach ($tmp as $value) {
                array_push($assignedUserIds, (int) $value->id);
            }
        }

        $addUserIds = array();
        $deleteUserIds = array();
        foreach ($result as $value) {
            $extUserId = $reg->getAttrFromID("User", $value["User_ID"], "externalOLID");
            if (empty($extUserId[0]) === true) {
                continue;
            }
            if (in_array($extUserId[0], $assignedUserIds) === false && $value["DeleteFlag"] == "0") {
                array_push($addUserIds, (int) $extUserId[0]);
            } else if (in_array($extUserId[0], $assignedUserIds) === true && $value["DeleteFlag"] == "1") {
                array_push($deleteUserIds, (int) $extUserId[0]);
            }
        }

        $scimInfo = array(
            'provisoning' => 'OneLogin',
            'scimMethod' => 'userToRoleFromRole',
            'table' => 'UserToRole',
            'itemId' => $item['ID'],
            'itemName' => '',  // sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        try {
        if (empty($addUserIds) === false) {
            $this->data->assignUserToRole($primary, $addUserIds);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::Role [" . $item["ID"] . "] assign user was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');

                    $scimInfo['message'] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
            }
        }

        if (empty($deleteUserIds) === false) {
            $this->data->removeUserFromRole($primary, $deleteUserIds);
            if (empty($this->data->getError()) === false) {
                Log::critical("OneLogin::Role [" . $item["ID"] . "] remove role was failed."
                    . ' reason "' . $this->data->getErrorDescription() . '"');

                    $scimInfo['message'] = $this->data->getErrorDescription();
                    $this->settingManagement->faildLogger($scimInfo);
            }
        }
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;

    }
}
}
