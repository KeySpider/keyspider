<?php

namespace App\Ldaplibs\SCIM\Salesforce;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\Salesforce\Authentication\PasswordAuthentication;
use App\Ldaplibs\SCIM\Salesforce\Exception\SalesforceAuthentication;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SCIMToSalesforce
{
    public function __construct()
    {
        $options = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['Salesforce Keys'];
        $salesforce = new PasswordAuthentication($options);
        try {
            $salesforce->authenticate();
        } catch (SalesforceAuthentication $e) {
        }

        $access_token = $salesforce->getAccessToken();
        echo "\n";
        var_dump($access_token);
        echo "\n";

        $this->crud = new \App\Ldaplibs\SCIM\Salesforce\CRUD();
    }

    public function createUserWithData(array $data = null)
    {
        if ($data == null) {
            $data = json_decode(Config::get('schemas.createUser'));
        }
        try {
            return $this->crud->create('USER', $data);  #returns id
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            echo ($exception->getMessage());
            return -1;
        }
    }

    public function createResource($resourceType, array $data = null)
    {
        $data = $this->replaceResource($resourceType, $data);
        $resourceType = strtolower($resourceType);

        if ($resourceType == 'user') {
            $dataSchema = json_decode(Config::get('schemas.createUser'), true);
            // $data['IsActive'] = isset($data['IsActive']) && $data['IsActive'] ? false : true;
            if (isset($data['IsActive']) && $data['IsActive']) {
                $data['IsActive'] = isset($data['IsActive']) && $data['IsActive'] ? false : true;
                $dataSchema['IsActive'] = isset($data['IsActive']) && $data['IsActive'] ? false : true;
            }

            $resourceType = 'USER';
            foreach ($dataSchema as $key => $value) {
                if (in_array($key, array_keys($data))) {
                    if ($key == 'Alias') {
                        $data[$key] = substr($data[$key], 0, 8);
                    }
                    $dataSchema[$key] = $data[$key];
                }
            }
        } elseif (($resourceType == 'role') || (strtolower($resourceType) == 'group')) {
            $dataSchema = json_decode(Config::get('schemas.createGroup'), true);
            foreach ($dataSchema as $key => $value) {
                if (in_array($key, array_keys($data))) {
                    if ($key == 'Alias') {
                        $data[$key] = substr($data[$key], 0, 8);
                    }
                    $dataSchema[$key] = $data[$key];
                }
            }
            // $dataSchema = json_decode(Config::get('schemas.createGroup'), true);
            $resourceType = 'GROUP';
        }
        echo ("Create [$resourceType] with data: \n");
        echo (json_encode($dataSchema, JSON_PRETTY_PRINT));
        try {
            $response = $this->crud->create($resourceType, $dataSchema);
            echo "\nCreate user Response: [$response]\n";
            $this->updateGroupMemebers($resourceType, $data, $response);
            return $response;  #returns id
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            echo ($exception->getMessage());
            return null;
        }
    }

    public function createGroupWithData(array $data = null)
    {
        if ($data == null) {
            $data = json_decode(Config::get('schemas.createGroup'));
        }
        try {
            return $this->crud->create('GROUP', $data);  #returns id
        } catch (\Exception $exception) {
            Log::erroe($exception->getMessage());
            echo ($exception->getMessage());
            return -1;
        }
    }

    public function getUser($id)
    {
        return $this->crud->getResourceDetail('Users', $id);
    }

    public function getGroup($id)
    {
        return $this->crud->getResourceDetail('Groups', $id);
    }

    public function getResourceDetails($resourceId, $resourceType)
    {
        $resourceType = $this->getResourceTypeOfSF($resourceType, true);
        return $this->crud->getResourceDetail($resourceType, $resourceId);
    }

    public function getUsersList()
    {
        return $this->crud->getResourceList('Users');
    }

    public function getGroupsList()
    {
        return $this->crud->getResourceList('Groups');
    }

    public function addMemberToGroup($memberId, $groupId)
    {
        try {
            return ($this->crud->addMemberToGroup($memberId, $groupId));
        } catch (\Exception $exception) {
            return [];
        }
    }

    public function deleteResource($resourceType, $data)
    {
        $resourceType = strtolower($this->getResourceTypeOfSF($resourceType, true));
        if ($resourceType == 'user') {
            $this->updateResource($resourceType, $data);
        } elseif (($resourceType == 'group') || ($resourceType == 'role')) {
            $resourceId = $data['externalSFID'];
            try {
                return ($this->crud->delete($resourceType, $resourceId));
            } catch (\Exception $exception) {
                Log::error("\n$exception");
                var_dump("\n$exception");
                return false;
            }
        }
    }

    public function updateResource($resourceType, $data)
    {
        $data = $this->replaceResource($resourceType, $data);

        $oriData = $data;
        $resourceType = strtolower($this->getResourceTypeOfSF($resourceType, true));
        $resourceId = $data['externalSFID'];
        unset($data['externalSFID']);
        if ($resourceType == 'user') {
            // $data['IsActive'] = $data['IsActive']?false:true;

            // if (!isset($data['IsActive'])) {
            //     $data['IsActive'] = true;
            // } else {
            //     $data['IsActive'] = $data['IsActive'] ? false : true;
            // }

            $dataSchema = json_decode(Config::get('schemas.createUser'), true);

            $resourceType = 'USER';
            foreach ($data as $key => $value) {
                if ($key === 'IsActive') {
                    $isActive = 'true';
                    if ( $value == '1') $isActive = 'false';
                    $data[$key] = $isActive;
                    continue;
                }

                if (!in_array($key, array_keys($dataSchema))) {
                    unset($data[$key]);
                }
                if ($key == 'Alias') {
                    $data[$key] = substr($data[$key], 0, 8);
                }
            }
        } elseif (($resourceType == 'group') || ($resourceType == 'role')) {
            $dataSchema = json_decode(Config::get('schemas.createGroup'), true);
            foreach ($data as $key => $value) {
                if (!in_array($key, array_keys($dataSchema))) {
                    unset($data[$key]);
                }
            }
        }
        echo ("\nUpdate $resourceType with data: \n");
        echo (json_encode($data, JSON_PRETTY_PRINT));

        $update = $this->crud->update($resourceType, $resourceId, $data);
        if (empty($update)) {
            return null;
        }

        $this->updateGroupMemebers($resourceType, $oriData, $resourceId);
        echo ("\nUpdate: $update");
        return $update;
    }

    /**
     * @param $resourceType
     * @return string
     */
    private function getResourceTypeOfSF($resourceType, $isREST = false): string
    {
        if ($resourceType == 'User') {
            $resourceType = $isREST ? 'User' : 'Users';
        } elseif ($resourceType == 'Role') {
            $resourceType = $isREST ? 'Group' : 'Groups';
        }
        return $resourceType;
    }

    public function getListOfGroupsUserBelongedTo($userAttibutes, $scims = ''): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($userAttibutes['ID'], $scims);
        return $memberOf;
    }


    public function getMemberOfsSF($uID)
    {
        $query = "SELECT userorgroupid From GROUPMEMBER where UserOrGroupId='$uID'";
        var_dump($this->crud->query($query));
        return [];
    }

    /**
     * @param $resourceType
     * @param array $data
     * @param $response
     */
    private function updateGroupMemebers($resourceType, array $data, $response): void
    {
        if (strtolower($resourceType) == 'user') {
            $userExtId = $data['externalSFID'];
            $this->removeMemberToSFGroup($userExtId);

            $memberOf = $this->getListOfGroupsUserBelongedTo($data, 'SF');
            foreach ($memberOf as $groupID) {
                $addMemberResult = $this->addMemberToGroup($response, $groupID);
                echo "\nAdd member to group result:\n";
                var_dump($addMemberResult);
            }
        }
    }

    private function removeMemberToSFGroup($sfUid)
    {
        $query = sprintf("SELECT GroupId FROM GroupMember WHERE UserOrGroupId = '%s'", $sfUid);
        $groupMembers = $this->crud->query($query);

        if ((int)$groupMembers['totalSize'] > 0) {
            foreach ($groupMembers['records'] as $record) {
                $subQuery = sprintf("SELECT ID FROM GroupMember WHERE GroupId = '%s' AND UserOrGroupId = '%s'",
                    $record['GroupId'], $sfUid);

                $groupMember = $this->crud->query($subQuery);
                if ($groupMember['totalSize'] == 1) {
                    $resourceId = $groupMember['records'][0]['Id'];
                    $this->crud->delete('GroupMember', $resourceId);
                    echo "\nRemove member to group result:\n";
                }
            }
        }
    }

    public function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        foreach ($item as $kv => $iv) {
            $twColumn = "$resourceType.$kv";
            if (in_array($twColumn, $getEncryptedFields)) {
                $item[$kv] = $settingManagement->passwordDecrypt($iv);
            }
        }
        return $item;
    }

    public function passwordResource($resourceType, $data)
    {
        $data = $this->replaceResource($resourceType, $data);

        $oriData = $data;
        $resourceType = strtolower($this->getResourceTypeOfSF($resourceType, true));
        $resourceId = $data['externalSFID'];
        unset($data['externalSFID']);
        if ($resourceType != 'user' || empty($data['Password'])) {
            return;
        }
        $item['NewPassword'] = $data['Password'];
        $this->crud->password($resourceType, $resourceId, $item);
    }
}
