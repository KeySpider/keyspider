<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace Tests\Unit;

use App\Ldaplibs\Import\SCIMReader;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsersFromAzureADTest extends TestCase
{
//    use RefreshDatabase;

    public const USER_SETTING_DEFAULT = [
        'SCIM Input Bacic Configuration' => [
            'ImportTable' => 'User'
        ],
        'SCIM Input Format Conversion' => [
            '#User.hogehogi' => '',
            'AAA.001' => "(userName,([A-Za-z0-9\._+]+)@(.*),$1)",
            'AAA.002' => 'TODAY()',
            'AAA.003' => '(password)',
            'AAA.004' => '(department)',
            '' => '',
            'AAA.005' => '(roles[0])',
            'AAA.013' => 'TODAY()',
            'AAA.014' => 'admin',
            'AAA.015' => '0',
            'AAA.006' => '(displayName,(.+) (.+),$1)',
            'AAA.007' => '(displayName,(.+) (.+),$2)',
            'AAA.008' => "(displayName,\s,)",
            'AAA.009' => "(mail,\w,\l)",
            'AAA.010' => 'hogehoge',
            'AAA.011' => 'hogehoga',
        ]
    ];

    public function testImportDataUserIntoMasterDB()
    {
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostUser.json')), true);
        $inputSetting = self::USER_SETTING_DEFAULT;

        $scimReader = new SCIMReader();
        $scimReader->addColumns($inputSetting);
        $scimReader->getFormatData($inputUserData, $inputSetting);
        $this->assertTrue(true);
    }

    /**
     * Test api get users list
     */
    public function testApiGetUsers()
    {
        $this->testImportDataUserIntoMasterDB();
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.config('app.azure_token'),
            ])
            ->get('api/Users?filter=userName eq "test@gmail.com"');

        $response->assertStatus(200);
    }

    /**
     * Test api create user
     */
    public function testApiCreateUser()
    {
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostUser.json')), true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.config('app.azure_token'),
        ])->json('POST', 'api/Users', $inputUserData);

        $response->assertStatus(201);

        $output = $response->decodeResponseJson();
        $isCompare = array_diff_assoc_recursive($inputUserData, $output);

        if (empty($isCompare)) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }
    }

    public function testApiUpdateUser()
    {
        $this->testImportDataUserIntoMasterDB();
        $userName = 'montes.nascetur.ridiculus';
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataUpdateUser.json')), true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.config('app.azure_token'),
        ])->json('PATCH', "api/Users/{$userName}", $inputUserData);

        $response->assertStatus(200);
    }
}
