<?php

namespace Tests\Feature;

use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\Import\SCIMReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApplyingSCIMImporting extends TestCase
{
//    use RefreshDatabase;

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

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }

    /**
     * Read SCIM follow setting file
     * @author ngulb@tech.est-rouge.com
     */
    public function testUserReadSCIMSettingNormal()
    {
        $filePath = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($filePath);

        $isCompare = array_diff_assoc_recursive(self::USER_SETTING_DEFAULT, $setting);

        if (empty($isCompare)) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }
    }

    /**
     * Read SCIM follow setting file
     * @author ngulb@tech.est-rouge.com
     */
    public function testRoleReadSCIMSettingNormal()
    {
        $filePath = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');
        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($filePath);

        $isCompare = array_diff_assoc_recursive(self::ROLE_SETTING_DEFAULT, $setting);

        if (empty($isCompare)) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }
    }

    /**
     * Test create name table from file user setting SCIM.
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function testCreateNameTableFromUserSCIMSetting()
    {
        $input = self::USER_SETTING_DEFAULT;
        $outputExpect = $input[config('const.scim_input')]['ImportTable'] === "User" ? "AAA" : null;

        if (!$outputExpect) {
            $this->assertTrue(false);
        }

        $scimReader = new SCIMReader();
        $nameTable = $scimReader->getTableName($input);

        $this->assertEquals($nameTable, $outputExpect);
    }

    /**
     * Test create name table from file role setting SCIM.
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function testCreateNameTableFromRoleSCIMSetting()
    {
        $input = self::ROLE_SETTING_DEFAULT;
        $outputExpect = $input[config('const.scim_input')]['ImportTable'] === "Role" ? "CCC" : null;

        if (!$outputExpect) {
            $this->assertTrue(false);
        }

        $scimReader = new SCIMReader();
        $nameTable = $scimReader->getTableName($input);

        $this->assertEquals($nameTable, $outputExpect);
    }

    /**
     * Test add new columns from user SCIm setting
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function testAddColumnsFromUserSCIMSetting()
    {
        $scimReader = new SCIMReader();

        $input = self::USER_SETTING_DEFAULT;
        $inputColumns = $scimReader->getAllColumnFromSetting($input[config('const.scim_format')]);
        $inputTable = "AAA";

        $scimReader->addColumns($input);

        $outputColumns = DB::getSchemaBuilder()->getColumnListing($inputTable);
        foreach ($outputColumns as $key => $column) {
            $outputColumns[$key] = "\"{$column}\"";
        }

        $compareColumn = arrays_are_similar($inputColumns, $outputColumns);

        $this->assertTrue($compareColumn);
    }

    /**
     * Test add new columns from role SCIm setting
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function testAddColumnsFromRoleSCIMSetting()
    {
        $scimReader = new SCIMReader();

        $input = self::ROLE_SETTING_DEFAULT;
        $inputColumns = $scimReader->getAllColumnFromSetting($input[config('const.scim_format')]);
        $inputTable = "CCC";

        $scimReader->addColumns($input);

        $outputColumns = DB::getSchemaBuilder()->getColumnListing($inputTable);
        foreach ($outputColumns as $key => $column) {
            $outputColumns[$key] = "\"{$column}\"";
        }

        $compareColumn = arrays_are_similar($inputColumns, $outputColumns);

        $this->assertTrue($compareColumn);
    }

    /**
     * Test parse data from pattern file user setting.
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function testParseDataFromPatternFileUserSetting()
    {
        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $output = [];

        $inputSetting = self::USER_SETTING_DEFAULT;
        $scimInputFormat = $inputSetting[config('const.scim_format')];
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostUser.json')), true);

        $outputExpect = [
            "montes.nascetur.ridiculus",
            Carbon::now()->format('Y/m/d'),
            null,
            "Office of the Director of Public Prosecutions",
            "admin",
            Carbon::now()->format('Y/m/d'),
            "admin",
            "0",
            "Barrera",
            "Scarlet",
            "BarreraScarlet",
            "",
            "hogehoge",
            "hogehoga",
        ];

        foreach ($scimInputFormat as $key => $item) {
            if ($key === "" || preg_match($pattern, $key) === 1) {
                unset($scimInputFormat[$key]);
            }
        }

        $scimReader = new SCIMReader();
        foreach ($scimInputFormat as $key => $value) {
            $data = $scimReader->processGroup($value, $inputUserData);
            array_push($output, $data);
        }

        $this->assertTrue(arrays_are_similar($output, $outputExpect));
    }

    /**
     * Test parse data from pattern file role setting.
     *
     * @author ngulb@tech.est-rouge.com
     */
    public function testParseDataFromPatternFileRoleSetting()
    {
        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $output = [];

        $inputSetting = self::ROLE_SETTING_DEFAULT;
        $scimInputFormat = $inputSetting[config('const.scim_format')];
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostRole.json')), true);

        $outputExpect = [
            "ea7ef37b-4cf2-45e8-8016-8178e4f7898f",
            Carbon::now()->format('Y/m/d'),
            "KS 10",
            Carbon::now()->format('Y/m/d'),
            "admin",
            "0",
            null,
            null,
            null,
            null,
        ];

        foreach ($scimInputFormat as $key => $item) {
            if ($key === "" || preg_match($pattern, $key) === 1) {
                unset($scimInputFormat[$key]);
            }
        }

        $scimReader = new SCIMReader();
        foreach ($scimInputFormat as $key => $value) {
            $data = $scimReader->processGroup($value, $inputUserData);
            array_push($output, $data);
        }

        $this->assertTrue(arrays_are_similar($output, $outputExpect));
    }

    /**
     * Test import data user into master DB
     */
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
     * Test import data role into master DB
     */
    public function testImportDataRoleIntoMasterDB()
    {
        $inputUserData = json_decode(file_get_contents(storage_path('data_test/dataPostRole.json')), true);
        $inputSetting = self::ROLE_SETTING_DEFAULT;

        $scimReader = new SCIMReader();
        $scimReader->addColumns($inputSetting);
        $scimReader->getFormatData($inputUserData, $inputSetting);
        $this->assertTrue(true);
    }
}
