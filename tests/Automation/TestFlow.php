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

    public function testScenario()
    {
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
        $this->getResourcesList("$baseDirectory/step5", $token);
        $this->getDetailResource("$baseDirectory/step6", $token);
        $this->patchResource("$baseDirectory/step7", $token);

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
        foreach ($allFiles as $fileName) {
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);

            echo("Creating user from file: $fileName\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('POST', 'api/Users', $inputUserData);

            $response->assertStatus(201);

            $output = $response->decodeResponseJson();

            $this->assertTrue(check_similar($expectedResponse, $output, ['meta']));        }
    }

    public function testCreateGroups(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName) {
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $allGroups[] = $inputUserData;
            echo("Creating group from file: $fileName\n");
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('POST', 'api/Groups', $inputUserData);

            $response->assertStatus(201);

            $output = $response->decodeResponseJson();
            $this->assertTrue(check_similar($expectedResponse, $output, ['meta']));        }

    }

    public function addMembersToGroup(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName) {
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
            $this->assertTrue(check_similar($expectedResponse, $output, ['meta']));        }

    }

    public function deleteUsers(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName) {
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
            $this->assertTrue(check_similar($expectedResponse, $output, ['meta']));        }

    }

    public function getResourcesList(string $flowDirectory, $token): void
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName) {
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $resourceType = $expectedResponse['Resources'][0]['meta']['resourceType']??null;
            if(!$resourceType) continue;
            $uri = "api/$resourceType".'s';
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('GET', $uri, $inputUserData);

            $response->assertStatus(200);

            $output = $response->decodeResponseJson();

//            $isCompare = check_similar($expectedResponse, $output, ['location']);
            $isCompare = array_diff_assoc_recursive($expectedResponse, $output);
            //var_dump($output);
            if (empty($isCompare)) {
                $this->assertTrue(true);
                echo("Get users success!\n");
            } else {
                var_dump($isCompare);
                $this->assertTrue(false);
            }
        }

    }


    public function getDetailResource(string $flowDirectory, $token): array
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName) {
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $resouceId = $expectedResponse['id'];
            $resouceType = $expectedResponse['meta']['resourceType'] . 's';
            $uri = "api/$resouceType/$resouceId";
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('GET', $uri, $inputUserData);

            $response->assertStatus(200);

            $output = $response->decodeResponseJson();
            $this->assertTrue(check_similar($expectedResponse, $output, ['meta']));        }
        return $output;
    }

    public function patchResource(string $flowDirectory, $token): array
    {
        $allFiles = getFiles("$flowDirectory/requests");
        $allGroups = [];
        foreach ($allFiles as $fileName) {
            $inputUserData = json_decode(file_get_contents("$flowDirectory/requests/$fileName"), true);
            $expectedResponse = json_decode(file_get_contents("$flowDirectory/responses/$fileName"), true);
            $resouceId = $expectedResponse['id'];
            $resouceType = $expectedResponse['meta']['resourceType'] . 's';
            $uri = "api/$resouceType/$resouceId";
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->json('PATCH', $uri, $inputUserData);

            $response->assertStatus(200);

            $output = $response->decodeResponseJson();
            //var_dump($output);
            $this->assertTrue(check_similar($expectedResponse, $output, ['meta']));
        }
        return $output;
    }

    public function testSummary()
    {
        $baseDirectory = storage_path(self::DATA_TEST_FLOWS);
        $expectedSettings = parse_ini_file($baseDirectory . '/' . 'expected_settings.ini', true);
        $query = DB::table('User');

        $allFlags = array_keys($expectedSettings['User']);
        $selectedColumns = array_merge($allFlags, ['ID']);
        $query->select($selectedColumns);
        $result = $query->get()->toArray();
        $allFlagsData = [];

        foreach ($result as $record) {
            foreach ($allFlags as $column) {
                if (isset($record->{$column}) and $record->{$column} == "1") {
                    $allFlagsData[$column][] = $record->ID;
                }
            }
        }
        var_dump($allFlagsData);
        $compare = array_diff_assoc_recursive($allFlagsData, $expectedSettings['User'], []);
        if ((empty($compare))) {
            $this->assertTrue(true);
            echo("Check database ok!\n");
        } else {
            echo("Check database but something went wrong!\nThe different is: \n");
            var_dump($compare);
            $this->assertTrue(false);
        }
    }
}
