<?php


namespace App\Ldaplibs;


use Exception;
use Faker\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Group;
use Microsoft\Graph\Model\User;

class UserGraphAPI
{
    public function __construct()
    {
        $tenantId = 'd40093bb-a186-4f71-8331-36cca3f165f8';
        $clientId = 'eb827075-42c3-4d23-8df0-ec135b46b5a6';
        $clientSecret = 'BtnF@kN3.?k.HA3raQBMasXiVOM3dNN0';
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
        echo($this->accessToken);
        $this->user_attributes = json_decode(Config::get('GraphAPISchemas.userAttributes'), true);
        $this->group_attributes = json_decode(Config::get('GraphAPISchemas.groupAttributes'), true);
    }

    private function createUserObject($userAttibutes = []): User
    {
        $faker = Factory::create();

        $userJson = Config::get('GraphAPISchemas.createUserJson');
        $newUser = new User(json_decode($userJson, true));
        $newUser->setDisplayName($userAttibutes->ID ?? null);
        $userName = 'faker_' . $faker->userName;
        //        Required attributes
        $newUser->setGivenName($userAttibutes->givenName);
        $newUser->setMailNickname($userAttibutes->mail);
        $newUser->setUserPrincipalName($userAttibutes->mailNickname ?? $userAttibutes->Name);
        //        Optional attributes
        $newUser->setCountry($faker->country);
        $newUser->setMobilePhone($faker->phoneNumber);
        $newUser->setStreetAddress($faker->streetAddress);
        return $newUser;
    }

    public function createUser($userAttibutes)
    {

        try {
            echo "\n- \t\tcreating User: \n";
            $newUser = new User($this->getAttributesAfterRemoveUnused($userAttibutes));
            $newUser->setPasswordProfile(["password" => 'test1234A!',
                "forceChangePasswordNextSignIn" => false
            ]);

            $newUser->setAccountEnabled(true);
            var_dump($newUser);
            $this->graph->createRequest("POST", "/users")
                ->attachBody($newUser)
                ->execute();

            //Get back to test
            $userCreated = $this->graph->createRequest("GET", "/users/" . $newUser->getUserPrincipalName())
                ->setReturnType(User::class)
                ->execute();
            echo "- \t\tcreated User \n";
            $this->addMembersToGroup($userAttibutes);
            return $userCreated;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            echo $exception;
            return null;
        }
    }

    public function updateUser($userAttibutes)
    {

        try {
            echo "\n- \t\tupdating User: \n";
            $accountEnable = $userAttibutes['DeleteFlag'] == 0 ? true : false;
            $uPN = $userAttibutes['userPrincipalName'];
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
            $this->addMembersToGroup($userAttibutes);
            return $newUser;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            echo $exception;
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
    private function getListOfGroupsUserBelongedTo($userAttibutes): array
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


    private function addMemberToGroup(string $uPCN, string $groupId): void
    {
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
    private function addMembersToGroup($userAttibutes): void
    {
        $memberOf = $this->getListOfGroupsUserBelongedTo($userAttibutes);
        foreach ($memberOf as $groupID) {
            $this->addMemberToGroup($userAttibutes['userPrincipalName'], $groupID);
        }
    }


}