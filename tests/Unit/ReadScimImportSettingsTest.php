<?php /** @noinspection ALL */

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

use App\Ldaplibs\Import\ImportSettingsManager;
use Tests\TestCase;

class ReadScimImportSettingsTest extends TestCase
{
    public function testUserInfoSCIMInput()
    {
        $filePath = storage_path('unittest/settings/scim/UserInfoSCIMInput.ini');
        $importSettingsManager = new ImportSettingsManager();
        print ("\r\n Do unit test for reading Scim settings on file: " . $filePath);
        try {
            $scim_settings = $importSettingsManager->getSCIMImportSettings($filePath);
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
        $expected_result = [
            'SCIM Input Bacic Configuration' =>
                [
                    'ImportTable' => 'User',
                ],
            'SCIM Input Format Conversion' =>
                [
                    '#User.hogehogi' => '',
                    'AAA.001' => '(userName,([A-Za-z0-9\\._+]+)@(.*),$1)',
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
                    'AAA.008' => '(displayName,\\s,)',
                    'AAA.009' => '(mail,\\w,\\l)',
                    'AAA.010' => 'hogehoge',
                    'AAA.011' => 'hogehoga',
                ],
        ];
        $this->assertTrue($scim_settings == $expected_result);
    }

    public function testRoleInfoSCIMInput()
    {
        $filePath = storage_path('unittest/settings/scim/RoleInfoSCIMInput.ini');
        $importSettingsManager = new ImportSettingsManager();
        print ("\r\n Do unit test for reading Scim settings on file: " . $filePath);
        try {
            $scim_settings = $importSettingsManager->getSCIMImportSettings($filePath);
        } catch (\Exception $e) {
            dd($e->getMessage());
        }

        $expected_result = [
            'SCIM Input Bacic Configuration' =>
                [
                    'ImportTable' => 'Role',
                ],
            'SCIM Input Format Conversion' =>
                [
                    '# Role.Attribute1' => '3',
                    'CCC.001' => '(externalId)',
                    'CCC.002' => 'TODAY()',
                    'CCC.003' => '(displayName)',
                    'CCC.009' => 'TODAY()',
                    'CCC.010' => 'admin',
                    'CCC.011' => '0',
                    'CCC.005' => '',
                    'CCC.006' => '',
                    'CCC.007' => '',
                    'CCC.008' => '',
                ],
        ];
        $this->assertTrue($scim_settings == $expected_result);
    }

    public function testSCIMFormat()
    {
        $importSettingsManager = new ImportSettingsManager();
        $resource = json_decode('{"001":"montes.nascetur.ridiculus","002":"2019/01/22","003":"","004":"Office of the Director of Public Prosecutions","005":"admin","013":"2019/01/22","014":"admin","015":"0","006":"Barrera","007":"Scarlet","008":"BarreraScarlet","009":"","010":"hogehoge","011":"hogehoga"}', true);
        $iniFilePath = '/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/settings/scim/UserInfoSCIMInput.ini';
        $formattedSCIM = $importSettingsManager->formatDBToSCIMStandard($resource, $iniFilePath);
        $expected_result = [
            "userName" => "montes.nascetur.ridiculus",
            "TODAY()" => "2019/01/22",
            "password" => "",
            "department" => "Office of the Director of Public Prosecutions",
            "roles" => "admin",
            "admin" => "admin",
            0 => "0",
            "displayName" => "BarreraScarlet",
            "mail" => "",
            "hogehoge" => "hogehoge",
            "hogehoga" => "hogehoga",
        ];

        self::assertTrue($formattedSCIM==$expected_result);

    }
}
