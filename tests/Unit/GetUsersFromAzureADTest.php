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


use Tests\TestCase;

class GetUsersFromAzureADTest extends TestCase
{
    /** @test
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    const LOCALHOST_8000_API_USERS = "localhost:8000/api/Users";

    public function getUsersList()
    {
        $ch = curl_init();
        $headers[] = 'Authorization:Bearer Token';
        curl_setopt($ch, CURLOPT_URL, "" . self::LOCALHOST_8000_API_USERS . "?filter=externalId+eq+%22aa219b54-dc68-4c68-a81f-b1866a75c80c%22");
        // SSL important
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);
        var_dump($output);
        curl_close($ch);
        $expected_response = '{"totalResults":0,"itemsPerPage":10,"startIndex":1,"schemas":["urn:ietf:params:scim:api:messages:2.0:ListResponse"],"Resources":[]}';
//        var_dump(json_encode($expected_response, JSON_PRETTY_PRINT));
        self::assertTrue($output==$expected_response);
    }
}