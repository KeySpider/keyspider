<?php
/**
 * Project = Key Spider
 * Year = 2019
 * Organization = Key Spider Japan LLC
 */

/**
 * Created by PhpStorm.
 * User: anhtuan
 * Date: 1/17/19
 * Time: 7:23 AM
 */

namespace Tests\Unit;


use App\Ldaplibs\Import\ImportSettingsManager;
use Tests\TestCase;

class ReadScimImportSettingsTest extends TestCase
{
    public function testUserInfoSCIMInput()
    {
        $filePath = '/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/settings/scim/UserInfoSCIMInput.ini';
        $importSettingsManager = new ImportSettingsManager();
        print ("\r\n Do unit test for reading Scim settings on file: ".$filePath);
        try {
            $scim_settings = $importSettingsManager->getSCIMImportSettings($filePath);
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
        $expected_result = array (
            'SCIM Input Bacic Configuration' =>
                array (
                    'ImportTable' => 'User',
                ),
            'SCIM Input Format Conversion' =>
                array (
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
                ),
        );
        $this->assertTrue($scim_settings== $expected_result);
    }

    public function testRoleInfoSCIMInput()
    {
        $filePath = '/Applications/MAMP/htdocs/LDAP_ID/storage/unittest/settings/scim/RoleInfoSCIMInput.ini';
        $importSettingsManager = new ImportSettingsManager();
        print ("\r\n Do unit test for reading Scim settings on file: ".$filePath);
        try {
            $scim_settings = $importSettingsManager->getSCIMImportSettings($filePath);
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
//        print (json_encode($scim_settings));
        $expected_result = array (
            'SCIM Input Bacic Configuration' =>
                array (
                    'ImportTable' => 'Role',
                ),
            'SCIM Input Format Conversion' =>
                array (
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
                ),
        );
        $this->assertTrue($scim_settings== $expected_result);
    }
}