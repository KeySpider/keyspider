<?php

namespace Tests\Feature;

use Microsoft\Graph\Graph;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestMSGraphAuth extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {
        $tenantId = '100aa1d3-76bd-4ec2-b686-e7e3aefdbc63';
        $clientId = '962bc0fa-2611-4e3f-889d-a298d8179ae2';
        $clientSecret = '/T_3v[ZGHVUaBRe4EAtiOqp6dNxL*u0p';
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
        $accessToken = $token->access_token;

        var_dump($accessToken);
        $graph = new Graph();
        $graph->setAccessToken($accessToken);

        $user = $graph->createRequest("GET", "/users")
            ->setReturnType(Model\User::class)
            ->execute();


        echo "Hello, I am $user->getGivenName() ";
//        $response = $this->get('/');

//        $response->assertStatus(200);
    }
}
