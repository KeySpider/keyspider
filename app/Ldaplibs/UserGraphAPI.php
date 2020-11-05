<?php


namespace App\Ldaplibs;

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
        $options = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'),
         true) ['AzureAD Keys'];

        // $tenantId = '77b7b616-2300-4f84-8406-a5155f307dbf';
        // $clientId = '7a1f26c0-1261-4e5a-90fb-716ffbc9d923';
        // $clientSecret = 'eTOMjY.klFs-YV_-jm3Eb5C3ZT~3dbX-v6';

        $tenantId = $options['tenantId'];
        $clientId = $options['clientId'];
        $clientSecret = $options['clientSecret'];;

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
        // echo($this->accessToken);
        // Log::debug('=====token =====');
        // Log::debug(print_r($this->accessToken, true));
        // Log::debug('=====token =====');
        $this->user_attributes = json_decode(Config::get('GraphAPISchemas.userAttributes'), true);
        $this->group_attributes = json_decode(Config::get('GraphAPISchemas.groupAttributes'), true);
    }

    private function createUserObject($userAttibutes = []): User
    {
        $faker = Factory::create();

        $userJson = Config::get('GraphAPISchemas.createUserJson');
        $newUser = new User(json_decode($userJson, true));
        $newUser->setDisplayName($userAttibutes->ID ?? null);
        // $userName = 'faker_' . $faker->userName;
        //        Required attributes
        $newUser->setGivenName($userAttibutes->givenName);
        $newUser->setMailNickname($userAttibutes->mail);
        $newUser->setUserPrincipalName($userAttibutes->mailNickname ?? $userAttibutes->Name);
        $newUser->setUsageLocation('JP');
        //        Optional attributes
        // $newUser->setCountry($faker->country);
        // $newUser->setMobilePhone($faker->phoneNumber);
        // $newUser->setStreetAddress($faker->streetAddress);
        return $newUser;
    }

    public function createUser($userAttibutes)
    {

        try {
            Log::info("Create user: ".json_encode($userAttibutes));
            echo "\n- \t\tcreating User: \n";
            $newUser = new User($this->getAttributesAfterRemoveUnused($userAttibutes));
            $newUser->setPasswordProfile(["password" => 'test1234A!',
                "forceChangePasswordNextSignIn" => false
            ]);

            $newUser->setUsageLocation('JP');
            $newUser->setAccountEnabled($userAttibutes['DeleteFlag'] == 0 ? true : false);
            // var_dump($newUser);
            $userCreated = $this->graph->createRequest("POST", "/users")
                ->attachBody($newUser)
                ->setReturnType(User::class)
                ->execute();
            $uID = $userCreated->getId();

            $this->updateUserAssignLicense($uID);

            echo "- \t\tcreated User \n";
            $this->addMemberToGroups($userAttibutes, $uID);
            return $userCreated;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
//            echo $exception;
            return null;
        }
    }

    public function updateUser($userAttibutes)
    {

        try {
            Log::info("Update user: ".json_encode($userAttibutes));
            echo "\n- \t\tupdating User: \n";
            $accountEnable = $userAttibutes['DeleteFlag'] == 0 ? true : false;
            $uPN = $userAttibutes['userPrincipalName'];
            $uID = $userAttibutes['externalID'];
            //Can not update userPrincipalName
            unset($userAttibutes['userPrincipalName']);
            $newUser = new User($this->getAttributesAfterRemoveUnused($userAttibutes));
            $newUser->setAccountEnabled($accountEnable);
            var_dump($newUser);
            $this->graph->createRequest("PATCH", "/users/$uPN")
                ->attachBody($newUser)
                ->execute();
            echo "\n- \t\t User[$uPN] updated \n";
            $userAttibutes['userPrincipalName'] = $uPN;
            $this->addMemberToGroups($userAttibutes, $uID);

            $this->updateUserAssignLicense($uID);

            return $newUser;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
//            echo $exception;
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
        $userDeleted = $this->graph->createRequest("DELETE", "/users/" . $userPrincipalName)
            ->setReturnType(User::class)
            ->execute();
        echo "\nDeleted user: $$userPrincipalName";
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

    public function createGroup($groupAttributes)
    {
        Log::info("creating Group: ".json_encode($groupAttributes));
        echo "\n- \t\tcreating Group: \n";
        $groupAttributes = $this->getGroupAttributesAfterRemoveUnused($groupAttributes);
        $newGroup = $this->createGroupObject($groupAttributes);
        var_dump($newGroup);
        try {
            $group = $this->graph->createRequest("POST", "/groups")
                ->attachBody($newGroup)
                ->setReturnType(Group::class)
                ->execute();
            var_dump($group);
            echo "\n- \t\tGroup created: \n";
            return $group;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            var_dump($exception->getMessage());
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
        //        Optional attributes

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
        } elseif ($tableName == 'Role') {
            return $this->createGroup($attributes);
            sleep(1);
        } else {
            return null;
        }
    }

    public function updateResource($attributes, $tableName)
    {
        if ($tableName == 'User') {
            return $this->updateUser($attributes);
        } elseif ($tableName == 'Role') {
            unset($attributes['mail']);
            return $this->updateGroup($attributes);
            sleep(1);
        } else {
            return null;
        }
    }

    public function getResourceDetails($id, $tableName, $uPN = null)
    {
        if ($tableName == 'User') {
            return $this->getUserDetail($id, $uPN);
        } elseif ($tableName == 'Role') {
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
            echo($exception->getMessage());
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
        $roleMap = (new SettingsManager())->getRoleMapInExternalID('Role');
        foreach ($userAttibutes as $roleFlag => $value) {
            if ((strpos($roleFlag, 'RoleFlag-') !== false) && ($value == 1)) {
                $temp = explode('-', $roleFlag);
                $memberOf[] = $roleMap[(int)$temp[1]];

            }
        }
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
            echo "-------";

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
            if(!in_array($groupID, $groupIDListOnAD)){
                $this->addMemberToGroup($uPN, $groupID);
            }
        }
        foreach ($groupIDListOnAD as $groupID) {
            if(!in_array($groupID, $memberOf)){
                $this->removeMemberOfGroup($uID, $groupID);
            }
        }
    }

    public function getMemberOfsAD($uPN){
        $groupList = $this->graph->createRequest("GET", "/users/$uPN/memberOf/")
            ->setReturnType(Group::class)
            ->execute();
        $groupIDList = [];
        foreach ($groupList as $group){
            $groupIDList[] = $group->getId();
        }
        return $groupIDList;
    }

    public function removeMemberOfGroup($uPCN, $groupId){
        Log::info("Remove member [$uPCN] from group [$groupId]");
        echo "\n Remove member [$uPCN] from group $groupId\n";

            $this->graph->createRequest("DELETE", "/groups/$groupId/members/$uPCN/\$ref")
                ->setReturnType("GuzzleHttp\Psr7\Stream")
                ->execute();
    }

    public function updateGroup($groupAttibutes)
    {

        try {
            Log::info("Update group: ".json_encode($groupAttibutes));
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
            return $newGroup;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
//            echo $exception;
            return null;
        }

    }

    public function deleteResource($id, $table): void
    {
        if($table=='User')
            $resource = 'users';
        elseif ($table=='Role'){
            $resource = 'groups';
        }
        else return;
        $userDeleted = $this->graph->createRequest("DELETE", "/$resource/" . $id)
//            ->setReturnType(User::class)
            ->execute();
    }

    public function getUsersList(): array
    {
        echo "- getUserList\n";
        $users = $this->graph->createRequest("GET", "/users")
            ->setReturnType(User::class)
            ->execute();
//        $this->assertNotNull($users);
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

    private function updateUserAssignLicense($uID)
    {
        echo "- UserAssignLicense\n";
        $data = Config::get('GraphAPISchemas.updateUserAssignLicenseJson');

        $url = 'https://graph.microsoft.com/v1.0/users/' . $uID . '/assignLicense';
        $auth = 'Bearer ' . $this->accessToken;
        $contentType = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $data);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
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
            Log::debug('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;


    }

}