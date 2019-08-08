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
            $allGroups[] = $inputUserData;
            echo("Creating user from file: $fileName\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('PATCH', 'api/Groups', $inputUserData);

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

}
