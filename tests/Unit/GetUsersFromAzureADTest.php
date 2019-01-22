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
    public const LOCALHOST_8000_API_USERS = 'localhost:8000/api/Users';

    /**
     *
     */
    public function testGetUsersList(): void
    {
        $ch = curl_init();
        $headers[] = 'Authorization:Bearer token';
        curl_setopt($ch, CURLOPT_URL, '' . self::LOCALHOST_8000_API_USERS . '?filter=userName+eq+%223d461654-0fc4-4dc8-8aa2-3dcf64837452%22');
        // SSL important
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);
        curl_close($ch);
        $expected_response = '{"totalResults":0,"itemsPerPage":10,"startIndex":1,"schemas":["urn:ietf:params:scim:api:messages:2.0:ListResponse"],"Resources":[]}';
        self::assertTrue($output === $expected_response);
    }
}