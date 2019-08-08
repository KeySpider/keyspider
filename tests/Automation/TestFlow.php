<?php

namespace Tests\Automation;
use App\Ldaplibs\SettingsManager;
use FlowTest;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TestFlow extends TestCase
{
    const DATA_TEST_FLOWS = 'data_test/flows';
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->allGroups = [];
    }

    public function testScenario(){
        DB::table('User')->truncate();
        DB::table('Role')->truncate();
        $baseDirectory = storage_path(self::DATA_TEST_FLOWS);
        $directories = getDirectories($baseDirectory);

        $settingManagement = new SettingsManager();
        $token = $settingManagement->getAzureADAPItoken();


//        foreach ($directories as $directory){
//            $flowDirectory = "$baseDirectory/$directory";
//            $this->testAddMemberToGroup($flowDirectory, $token);
//        }

        $this->testCreateUsers("$baseDirectory/step1", $token);
        $this->testCreateGroups("$baseDirectory/step2", $token);
        $this->addMembersToGroup("$baseDirectory/step3", $token);
        $this->deleteUsers("$baseDirectory/step4", $token);
        $this->testSummary();
    }

    /**
     * @param string $flowDirectory
     * @param \App\Ldaplibs\Bearer $token
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testCreateUsers(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        foreach ($allFiles as $fileName){
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);

            echo("Creating user from file: $fileName\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('POST', 'api/Users', $inputUserData);

            $response->assertStatus(201);

            $output = $response->decodeResponseJson();
            $isCompare = check_similar($expectedResponse, $output, ['location']);

            if (($isCompare)) {
                $this->assertTrue(true);
                echo("Created user success!\n");
            } else {

                $this->assertTrue(false);
            }
        }
    }
    public function testCreateGroups(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName){
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $allGroups[] = $inputUserData;
            echo("Creating user from file: $fileName\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('POST', 'api/Groups', $inputUserData);

            $response->assertStatus(201);

            $output = $response->decodeResponseJson();
            $isCompare = check_similar($expectedResponse, $output, ['location']);

            if (($isCompare)) {
                $this->assertTrue(true);
                echo("Created group success!\n");
            } else {

                $this->assertTrue(false);
            }
        }

    }

    public function addMembersToGroup(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName){
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $resouceId = $expectedResponse['id'];
            $allGroups[] = $inputUserData;

            $uri = "api/Groups/$resouceId";
            echo("Adding user to group: $resouceId\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('PATCH', $uri, $inputUserData);

//            $response->assertStatus(201);

            $output = $response->decodeResponseJson();
            $isCompare = check_similar($expectedResponse, $output, ['location']);
            if (($isCompare)) {
                $this->assertTrue(true);
                echo("Added user to group success!\n");
            } else {

                $this->assertTrue(false);
            }
        }

    }

    public function deleteUsers(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName){
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $resouceId = $expectedResponse['id'];
            $allGroups[] = $inputUserData;

            $uri = "api/Users/$resouceId";
            echo("Deleting user: $resouceId\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('PATCH', $uri, $inputUserData);

            $response->assertStatus(200);

            $output = $response->decodeResponseJson();
            $isCompare = check_similar($expectedResponse, $output, ['location']);
            if (($isCompare)) {
                $this->assertTrue(true);
                echo("Delete user success!\n");
            } else {

                $this->assertTrue(false);
            }
        }

    }

    public function testSummary(){
        $baseDirectory = storage_path(self::DATA_TEST_FLOWS);
        $expectedSettings = parse_ini_file($baseDirectory.'/'.'expected_settings.ini', true);
        $query = DB::table('User');

        $allFlags  = array_keys($expectedSettings['User']);
        $selectedColumns = array_merge($allFlags, ['ID']);
        $query->select($selectedColumns);
        $result = $query->get()->toArray();
        $allFlagsData = [];

        foreach ($result as $record){
            foreach ($allFlags as $column){
                if(isset($record->{$column}) and $record->{$column} == "1"){
                    $allFlagsData[$column][] = $record->ID;
                }
            }
        }
        var_dump($allFlagsData);
        $compare = array_diff_assoc_recursive($allFlagsData, $expectedSettings['User'], []);
        if ((self::isEmpty($compare))) {
            $this->assertTrue(true);
            echo("Check database ok!\n");
        } else {
            echo("Check database but something went wrong!\n");
            $this->assertTrue(false);
        }
    }
}
