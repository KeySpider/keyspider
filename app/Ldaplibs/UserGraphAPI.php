<?php


namespace App\Ldaplibs;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Exception;
use Faker\Factory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Group;
use Microsoft\Graph\Model\User;
use MongoDB\Driver\Exception\ExecutionTimeoutException;

class UserGraphAPI
{
    public function __construct()
    {
        $options = parse_ini_file(
            storage_path('ini_configs/GeneralSettings.ini'),
            true
        )['AzureAD Keys'];

        $tenantId = $options['tenantId'];
        $clientId = $options['clientId'];
        $clientSecret = $options['clientSecret'];
        $this->initialPassword = $options['initialPassword'];

        $guzzle = new \GuzzleHttp\Client();
        $url = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/token?api-version=1.0';
        $token = json_decode($guzzle->post($url, [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'resource' => 'https://graph.microsoft.com/',
                'grant_type' => 'client_credentials',
            ],
        ])->getBody()->getContents());

        $this->accessToken = $token->access_token;
        $this->graph = new Graph();
        $this->graph->setAccessToken($this->accessToken);
        $this->user_attributes = json_decode(Config::get('GraphAPISchemas.userAttributes'), true);
        $this->group_attributes = json_decode(Config::get('GraphAPISchemas.groupAttributes'), true);

        $this->licenses = parse_ini_file(
            storage_path('office365license/365Licenses.ini'),
            true
        )['Microsoft Office 365 Licenses'];

        $this->settingManagement = new SettingsManager();
        $this->getOfficeLicenseField = $this->settingManagement->getOfficeLicenseFields();
    }

    private function createUserObject($userAttibutes = []): User
    {
        $faker = Factory::create();

        $userJson = Config::get('GraphAPISchemas.createUserJson');
        $newUser = new User(json_decode($userJson, true));
        $newUser->setDisplayName($userAttibutes->ID ?? null);

        // Required attributes
        $newUser->setGivenName($userAttibutes->givenName);
        $newUser->setMailNickname($userAttibutes->mail);
        $newUser->setUserPrincipalName($userAttibutes->mailNickname ?? $userAttibutes->Name);
        $newUser->setUsageLocation('JP');
        return $newUser;
    }

    public function createUser($userAttibutes)
    {
        $scimInfo = array(
            'provisoning' => 'AzureAD',
            'scimMethod' => 'create',
            'table' => 'User',
            'itemId' => $userAttibutes['ID'],
            'itemName' => sprintf("%s %s", $userAttibutes['surname'], $userAttibutes['givenName']),
            'message' => '',
        );

        $swapUser = array();
        foreach ($userAttibutes as $attr => $value) {
            if (!empty($value)) {
                $swapUser[$attr] = $value;
            } elseif ($value === "0") {
                $swapUser[$attr] = $value;
            }
        }
        $userAttibutes = $swapUser;

        try {
            Log::info("Create user: " . json_encode($userAttibutes));
            echo "\n- \t\tcreating User: \n";
            $newUser = new User($this->getAttributesAfterRemoveUnused($userAttibutes));
            $newUser->setPasswordProfile([
                "password" => $this->initialPassword,
                "forceChangePasswordNextSignIn" => false
            ]);

            $newUser->setUsageLocation('JP');

            if ( array_key_exists('accountEnabled', $userAttibutes) ) {
                $newUser->setAccountEnabled($userAttibutes['LockFlag'] == 0 ? True : False);
            }
            // $newUser->setAccountEnabled($userAttibutes['LockFlag'] == 0 ? true : false);
            $userCreated = $this->graph->createRequest("POST", "/users")
                ->attachBody($newUser)
                ->setReturnType(User::class)
                ->execute();
            $uID = $userCreated->getId();

            if (!empty($this->getOfficeLicenseField)) {
                $item = explode('.', $this->getOfficeLicenseField);
                if (!empty($userAttibutes[$item[1]])) {
                    $license = $this->licenses[$userAttibutes[$item[1]]];
                    $this->updateUserAssignLicense($uID, $license, $this->getOfficeLicenseField);
                }
            }

            echo "- \t\tcreated User \n";
            $this->addMemberToGroups($userAttibutes, $uID);
            $this->settingManagement->detailLogger($scimInfo);

            return $userCreated;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);

            return null;
        }
    }

    public function updateUser($userAttibutes)
    {
        $scimInfo = array(
            'provisoning' => 'AzureAD',
            'scimMethod' => 'update',
            'table' => 'User',
            'itemId' => $userAttibutes['ID'],
            'itemName' => sprintf("%s %s", $userAttibutes['surname'], $userAttibutes['givenName']),
            'message' => '',
        );

        try {
            Log::info("Update user: " . json_encode($userAttibutes));
            echo "\n- \t\tupdating User: \n";
            // $accountEnable = $userAttibutes['DeleteFlag'] == 0 ? true : false;
            $uPN = $userAttibutes['userPrincipalName'];
            $uID = $userAttibutes['externalID'];
            //Can not update userPrincipalName
            unset($userAttibutes['userPrincipalName']);
            $newUser = new User($this->getAttributesAfterRemoveUnused($userAttibutes));

            if ( array_key_exists('accountEnabled', $userAttibutes) ) {
                $newUser->setAccountEnabled($userAttibutes['LockFlag'] == 0 ? True : False);
            }
            // $newUser->setAccountEnabled($accountEnable);
            var_dump($newUser);
            $this->graph->createRequest("PATCH", "/users/$uPN")
                ->attachBody($newUser)
                ->execute();
            echo "\n- \t\t User[$uPN] updated \n";
            $userAttibutes['userPrincipalName'] = $uPN;
            $this->addMemberToGroups($userAttibutes, $uID);

            if (!empty($this->getOfficeLicenseField)) {
                $item = explode('.', $this->getOfficeLicenseField);
                if (!empty($userAttibutes[$item[1]])) {
                    $license = $this->licenses[$userAttibutes[$item[1]]];
                    $this->updateUserAssignLicense($uID, $license, $this->getOfficeLicenseField);
                }
            }
            $this->settingManagement->detailLogger($scimInfo);

            return $newUser;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);

            return null;
        }
    }

    /**
     * @param $userAttibutes
     * @return mixed
     */
    private function getAttributesAfterRemoveUnused($userAttibutes)
    {
        foreach ($userAttibutes as $key => $value) {
            if (!in_array($key, $this->user_attributes)) {
                unset($userAttibutes[$key]);
            }
        }
        return $userAttibutes;
    }

    public function deleteUser($userPrincipalName): void
    {
        $scimInfo = array(
            'provisoning' => 'AzureAD',
            'scimMethod' => 'delete',
            'table' => 'User',
            'itemId' => $userPrincipalName,
            'itemName' => '',
            'message' => '',
        );

        try {
            $userDeleted = $this->graph->createRequest("DELETE", "/users/" . $userPrincipalName)
                ->setReturnType(User::class)
                ->execute();
            echo "\nDeleted user: $$userPrincipalName";
            $this->settingManagement->detailLogger($scimInfo);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }        
    }

    public function getUserDetail(string $userId, $uPN)
    {
        try {
            $user = $this->graph->createRequest("GET", "/users/{$userId}")
                ->setReturnType(User::class)
                ->execute();
            return $user;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            try {
                $user = $this->graph->createRequest("GET", "/users/{$uPN}")
                    ->setReturnType(User::class)
                    ->execute();
                return $user;
            } catch (\Exception $exception) {
                Log::error($exception->getMessage());
                return null;
            }
        }
    }

    public function createGroup($groupAttributes, $ext = null)
    {
        $scimInfo = array(
            'provisoning' => 'AzureAD',
            'scimMethod' => 'create',
            'table' => 'Group',
            'itemId' => $groupAttributes['ID'],
            'itemName' => $groupAttributes['displayName'],
            'message' => '',
        );

        Log::info("creating Group: " . json_encode($groupAttributes));
        echo "\n- \t\tcreating Group: \n";
        $groupAttributes = $this->getGroupAttributesAfterRemoveUnused($groupAttributes);
        if (empty($ext)) {
            $newGroup = $this->createGroupObject($groupAttributes);
        } else {
            $newGroup = $this->createGroupObjectExt($groupAttributes);
        }

        try {
            $group = $this->graph->createRequest("POST", "/groups")
                ->attachBody($newGroup)
                ->setReturnType(Group::class)
                ->execute();
            echo "\n- \t\tGroup created: \n";
            $this->settingManagement->detailLogger($scimInfo);

            return $group;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    private function createGroupObject($groupAttributes): Group
    {
        $userJson = Config::get('GraphAPISchemas.createGroupJson');
        $userArray = json_decode($userJson, true);
        $newGroup = new Group($groupAttributes);
        $newGroup->setMailEnabled(false);
        $newGroup->setGroupTypes(['Unified']);
        $newGroup->setSecurityEnabled(false);
        return $newGroup;
    }

    private function createGroupObjectExt($groupAttributes): Group
    {
        $storage_path = storage_path('office365license/365Licenses.ini');
        if (empty($groupAttributes['groupTypes'])) {
            // Magic word is useless...
            $groupAttributes['groupTypes'] = 'Microsoft 365';
        }
        $section = 'Group type ' . $groupAttributes['groupTypes'];
        $groupConf = parse_ini_file($storage_path, true)[$section];

        $userJson = Config::get('GraphAPISchemas.createGroupJson');
        $userArray = json_decode($userJson, true);
        $newGroup = new Group($groupAttributes);

        $newGroup->setGroupTypes([]);
        if (!empty($groupConf['groupTypes'])) {
            $newGroup->setGroupTypes((array)$groupConf['groupTypes']);
        }
        $newGroup->setMailEnabled($groupConf['mailEnabled']);
        $newGroup->setSecurityEnabled($groupConf['securityEnabled']);
        return $newGroup;
    }

    private function getGroupAttributesAfterRemoveUnused($groupAttibutes)
    {
        foreach ($groupAttibutes as $key => $value) {
            if (!in_array($key, $this->group_attributes)) {
                unset($groupAttibutes[$key]);
            }
        }
        return $groupAttibutes;
    }

    public function createResource($attributes, $tableName)
    {
        if ($tableName == 'User') {
            return $this->createUser($attributes);
        } elseif ($tableName == 'Group') {
            return $this->createGroup($attributes, 'ext');
        } else {
            return null;
        }
    }

    public function updateResource($attributes, $tableName)
    {
        if ($tableName == 'User') {
            return $this->updateUser($attributes);
        } elseif ($tableName == 'Group') {
            unset($attributes['mail']);
            return $this->updateGroup($attributes);
        } else {
            return null;
        }
    }

    public function getResourceDetails($id, $tableName, $uPN = null)
    {
        if ($tableName == 'User') {
            return $this->getUserDetail($id, $uPN);
        } elseif ($tableName == 'Group') {
            return $this->getGroupDetails($id);
        } else {
            return null;
        }
    }

    public function getGroupDetails($id)
    {
        try {
            $group = $this->graph->createRequest("GET", "/groups/$id")
                ->setReturnType(Group::class)
                ->execute();
            var_dump($group);
            return $group;
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            echo ($exception->getMessage());
            return null;
        }
    }

    /**
     * @param $userAttibutes
     * @return array
     */
    public function getListOfGroupsUserBelongedTo($userAttibutes): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($userAttibutes['ID'], '');
        return $memberOf;
    }

    private function addMemberToGroup($uPCN, $groupId): void
    {
        Log::info("Add member [$uPCN] to group [$groupId]");
        $body = json_decode('{"@odata.id": "https://graph.microsoft.com/v1.0/users/' . $uPCN . '"}', true);
        $response = $this->graph->createRequest("POST", "/groups/$groupId/members/\$ref")
            ->attachBody($body)
            ->setReturnType("GuzzleHttp\Psr7\Stream")
            ->execute();
        echo "\nadd member to group: $uPCN\n\n";
        var_dump($response);
    }

    /**
     * @param $userAttibutes
     */
    private function addMemberToGroups($userAttibutes, $uID): void
    {
        // Import data role info
        $memberOf = $this->getListOfGroupsUserBelongedTo($userAttibutes);
        $uPN = $userAttibutes['userPrincipalName'];

        // Now stored role info
        $groupIDListOnAD = $this->getMemberOfsAD($uPN);

        foreach ($memberOf as $groupID) {
            if (!in_array($groupID, $groupIDListOnAD)) {
                $this->addMemberToGroup($uPN, $groupID);
            }
        }

        foreach ($groupIDListOnAD as $groupID) {
            if (!in_array($groupID, $memberOf)) {
                $this->removeMemberOfGroup($uID, $groupID);
            }
        }
    }

    public function getMemberOfsAD($uPN)
    {
        $groupList = $this->graph->createRequest("GET", "/users/$uPN/memberOf/")
            ->setReturnType(Group::class)
            ->execute();
        $groupIDList = [];
        foreach ($groupList as $group) {
            $groupIDList[] = $group->getId();
        }
        return $groupIDList;
    }

    public function removeMemberOfGroup($uPCN, $groupId)
    {
        Log::info("Remove member [$uPCN] from group [$groupId]");
        echo "\n Remove member [$uPCN] from group $groupId\n";

        $this->graph->createRequest("DELETE", "/groups/$groupId/members/$uPCN/\$ref")
            ->setReturnType("GuzzleHttp\Psr7\Stream")
            ->execute();
    }

    public function updateGroup($groupAttibutes)
    {
        $scimInfo = array(
            'provisoning' => 'AzureAD',
            'scimMethod' => 'update',
            'table' => 'group',
            'itemId' => $groupAttibutes['ID'],
            'itemName' => $groupAttibutes['displayName'],
            'message' => '',
        );

        try {
            Log::info("Update group: " . json_encode($groupAttibutes));
            echo "\n- \t\tupdating User: \n";
            $accountEnable = $groupAttibutes['DeleteFlag'] == 0 ? true : false;
            $uID = $groupAttibutes['externalID'];
            $groupAttibutes = array_only($groupAttibutes, ['displayName']);
            $newGroup = new Group($this->getGroupAttributesAfterRemoveUnused($groupAttibutes));
            var_dump($newGroup);
            $this->graph->createRequest("PATCH", "/groups/$uID")
                ->attachBody($newGroup)
                ->execute();
            echo "\n- \t\t Group [$uID] updated \n";
            $this->settingManagement->detailLogger($scimInfo);

            return $newGroup;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);

            return null;
        }
    }

    // public function deleteResource($id, $table): void
    public function deleteResource($id, $table)
    {
        $scimInfo = array(
            'provisoning' => 'AzureAD',
            'scimMethod' => 'delete',
            'table' => $table,
            'itemId' => $id,
            'itemName' => '',
            'message' => '',
        );

        if ($table == 'User') {
            $resource = 'users';
        } elseif ($table == 'Group') {
            $resource = 'groups';
        } else return null;

        try {
            $userDeleted = $this->graph->createRequest("DELETE", "/$resource/" . $id)
            // ->setReturnType(User::class)
            ->execute();
            $this->settingManagement->detailLogger($scimInfo);
            return $userDeleted;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        return null;
    }

    public function getUsersList(): array
    {
        echo "- getUserList\n";
        $users = $this->graph->createRequest("GET", "/users")
            ->setReturnType(User::class)
            ->execute();
        // $this->assertNotNull($users);
        return $users;
    }

    public function getGroupsList(): array
    {
        echo "- getGroupList\n";
        $groups = $this->graph->createRequest("GET", "/groups")
            ->setReturnType(Group::class)
            ->execute();
        return $groups;
    }

    public function removeLicenseDetail($uID)
    {
        echo "--- RemoveUserLicense ---\n";
        // $data = Config::get('GraphAPISchemas.updateUserAssignLicenseJson');
        // $data = str_replace("($field)", $license, $data);

        $licenseDetail = $this->graph->createRequest("GET", "/users/{$uID}/licenseDetails")
            ->setReturnType(User::class)
            ->execute();

        $cnv = json_decode(json_encode($licenseDetail), true);

        foreach ($cnv as $data) {
            $license = $data['skuId'];
            $this->updateUserAssignLicense($uID, $license, 'User.OfficeLicense', 'ext');
        }
    }

    private function updateUserAssignLicense($uID, $license, $field, $ext = null)
    {
        if (empty($ext)) {
            echo "- UserAssignLicense\n";
            $data = Config::get('GraphAPISchemas.updateUserAssignLicenseJson');
        } else {
            $data = Config::get('GraphAPISchemas.removeOfficeLicenseJson');
        }
        $data = str_replace("($field)", $license, $data);

        $url = 'https://graph.microsoft.com/v1.0/users/' . $uID . '/assignLicense';
        $auth = 'Bearer ' . $this->accessToken;
        $contentType = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $contentType")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Assign License faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }
}
