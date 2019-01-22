<?php
/**
 * Project = Key Spider
 * Year = 2019
 * Organization = Key Spider Japan LLC
 */

/**
 * Created by PhpStorm.
 * User: anhtuan
 * Date: 1/21/19
 * Time: 11:43 PM
 */

namespace Tests\Unit;


use App\Ldaplibs\Import\SCIMReader;
use Tests\TestCase;

class LibForWorkingWithAzureADTest extends TestCase
{
    /** @test
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public const LOCALHOST_8000_API_USERS = 'localhost:8000/api/Users';

    /**
     *
     */

    public const USER_SETTING_DEFAULT = [
        "SCIM Input Bacic Configuration" => [
            "ImportTable" => "User"
        ],
        "SCIM Input Format Conversion" => [
            "#User.hogehogi" => "",
            "AAA.001" => "(userName,([A-Za-z0-9\._+]+)@(.*),$1)",
            "AAA.002" => "TODAY()",
            "AAA.003" => "(password)",
            "AAA.004" => "(department)",
            "" => "",
            "AAA.005" => "(roles[0])",
            "AAA.013" => "TODAY()",
            "AAA.014" => "admin",
            "AAA.015" => "0",
            "AAA.006" => "(displayName,(.+) (.+),$1)",
            "AAA.007" => "(displayName,(.+) (.+),$2)",
            "AAA.008" => "(displayName,\s,)",
            "AAA.009" => "(mail,\w,\l)",
            "AAA.010" => "hogehoge",
            "AAA.011" => "hogehoga",
        ]
    ];

    public function testImportDataUserIntoMasterDB()
    {
        $inputUserData = json_decode(file_get_contents(storage_path('unittest/import/dataPostUser.json')), true);
        $inputSetting = self::USER_SETTING_DEFAULT;

        $scimReader = new SCIMReader();
        $scimReader->addColumns($inputSetting);
        $scimReader->getFormatData($inputUserData, $inputSetting);
        $this->assertTrue(true);
    }


    public function testGetUsersList(): void
    {
        $ch = curl_init();
        $headers[] = 'Authorization:Bearer token';
        curl_setopt($ch, CURLOPT_URL, '' . self::LOCALHOST_8000_API_USERS . '?filter=userName+eq+%223d461654-0fc4-4dc8-8aa2-3dcf64837452%22');
        // SSL important
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);
        curl_close($ch);
        $expected_response = '{"totalResults":0,"itemsPerPage":10,"startIndex":1,"schemas":["urn:ietf:params:scim:api:messages:2.0:ListResponse"],"Resources":[]}';
        self::assertTrue($output === $expected_response);
    }

    public function testGetUsersListWithCorrectFilter(): void
    {
        $ch = curl_init();
        $headers[] = 'Authorization:Bearer token';
        curl_setopt($ch, CURLOPT_URL, '' . self::LOCALHOST_8000_API_USERS . '?filter=userName+eq+"montes.nascetur.ridiculus@keyspider.onmicrosoft.com"');
        // SSL important
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);
        curl_close($ch);
        $expected_response = '{
    "totalResults": 1,
    "itemsPerPage": 10,
    "startIndex": 1,
    "schemas": [
        "urn:ietf:params:scim:api:messages:2.0:ListResponse"
    ],
    "Resources": [
        {
            "id": "montes.nascetur.ridiculus",
            "externalId": "montes.nascetur.ridiculus",
            "userName": "montes.nascetur.ridiculus@keyspider.onmicrosoft.com",
            "active": true,
            "displayName": "BarreraScarlet",
            "meta": {
                "resourceType": "User"
            },
            "name": {
                "formatted": "",
                "familyName": "",
                "givenName": ""
            },
            "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User": {
                "department": "Office of the Director of Public Prosecutions"
            }
        }
    ]
}';
        self::assertTrue(json_decode($output) == json_decode($expected_response));
    }
}