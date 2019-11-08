<?php


namespace App\Ldaplibs;


use Faker\Factory;
use Illuminate\Support\Facades\Config;
use Microsoft\Graph\Graph;
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
        echo ($this->accessToken);
    }

    private function createUserObject($userAttibutes=[]): User
    {
        $faker = Factory::create();

        $userJson = Config::get('GraphAPISchemas.createUserJson');
        $newUser = new User(json_decode($userJson, true));
        $newUser->setDisplayName($userAttibutes->ID??null);
        $userName = 'faker_' . $faker->userName;
        //        Required attributes
        $newUser->setGivenName($userAttibutes->givenName);
        $newUser->setMailNickname($userAttibutes->mail);
        $newUser->setUserPrincipalName($userAttibutes->mailNickname??$userAttibutes->Name);
        //        Optional attributes
        $newUser->setCountry($faker->country);
        $newUser->setMobilePhone($faker->phoneNumber);
        $newUser->setStreetAddress($faker->streetAddress);
        return $newUser;
    }

    public function createUser($userAttibutes)
    {
        echo "- \t\tcreating User: \n";
        unset($userAttibutes['ID']);
        $password = $userAttibutes['password'];

        unset($userAttibutes['password']);
        $name = $userAttibutes['Name'];
        unset($userAttibutes['Name']);

        $newUser = new User($userAttibutes);
        $newUser->setUserPrincipalName($name);
        $newUser->setPasswordProfile([  "password"=> 'test1234A!',
                                        "forceChangePasswordNextSignIn"=> false
                                    ]);


        $newUser->setAccountEnabled(true);
        var_dump($newUser);
        $this->graph->createRequest("POST", "/users")
                ->attachBody($newUser)
                ->execute();

            //Get back to test
            $userCreated = $this->graph->createRequest("GET", "/users/".$newUser->getUserPrincipalName())->setReturnType(User::class)
                ->execute();
            //Check they're having same UserPrincipalName
//            $newUserPrincipalName = $newUser->getId();
//            $createdPrincipalName = $userCreated->getId();
//            $this->assertEquals($newUserPrincipalName, $createdPrincipalName);
        echo "- \t\tcreated User \n";
    }
}