<?php
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

class GroupsFromAzureADTest extends TestCase
{
    use RefreshDatabase;

    public const ROLE_SETTING_DEFAULT = [
        "SCIM Input Bacic Configuration" => [
            "ImportTable" => "Role"
        ],
        "SCIM Input Format Conversion" => [
            "# Role.Attribute1" => "3",
            "CCC.001" => "(externalId)",
            "CCC.002" => "TODAY()",
            "CCC.003" => "(displayName)",
            "CCC.009" => "TODAY()",
            "CCC.010" => "admin",
            "CCC.011" => "0",
            "CCC.005" => "",
            "CCC.006" => "",
            "CCC.007" => "",
            "CCC.008" => ""
        ]
    ];

    public function testImportDataRoleIntoMasterDB()
    {
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostRole.json')), true);
        $inputSetting = self::ROLE_SETTING_DEFAULT;

        $scimReader = new SCIMReader();
        $scimReader->addColumns($inputSetting);
        $scimReader->getFormatData($inputUserData, $inputSetting);
        $this->assertTrue(true);
    }

    /**
     * Test api get groups list
     */
    public function testApiGetGroups()
    {
        $this->testImportDataRoleIntoMasterDB();

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.config('app.azure_token'),
            ])
            ->get('api/Groups?excludedAttributes=members&filter=displayName eq "KS 9"');

        $response->assertStatus(200);
    }

    /**
     * Test api create group
     */
    public function testApiCreateGroup()
    {
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostRole.json')), true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.config('app.azure_token'),
        ])->json('POST', 'api/Groups', $inputUserData);

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
        $this->testImportDataRoleIntoMasterDB();
        $externalID = "ea7ef37b-4cf2-45e8-8016-8178e4f7898f";
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataUpdateGroup.json')), true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.config('app.azure_token'),
        ])->json('PATCH', "api/Groups/{$externalID}", $inputUserData);

        $response->assertStatus(200);
    }
}
