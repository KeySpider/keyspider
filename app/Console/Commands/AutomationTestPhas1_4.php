<?php

namespace App\Console\Commands;

use App\Ldaplibs\UserGraphAPI;
use App\Role;
use App\User;
use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\Statement;

function readCSV($csvFile, $array)
{
    $file_handle = fopen($csvFile, 'r');
    while (!feof($file_handle)) {
        $line_of_text[] = fgetcsv($file_handle, 0, $array['delimiter']);
    }
    fclose($file_handle);
    return $line_of_text;
}

function getArrayFromCSV($filePath){
    $allData = [];
    $csv_data = array_map('str_getcsv', file($filePath));// reads the csv file in php array
    $csv_header = $csv_data[0];//creates a copy of csv header array
    unset($csv_data[0]);//removes the header from $csv_data since no longer needed
    foreach($csv_data as $row){
        $row = array_combine($csv_header, $row);// adds header to each row as key
//        var_dump($row);//do something here with each row
        $allData[] = $row;
    }
    return $allData;
}

class AutomationTestPhas1_4 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:test_phase_1_4';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
/*        //step 8
        (new ResetDataToTestPhase4())->handle();
        //step 9
        $checkUser = $this->checkResourceWithCSV(User::class, storage_path('data_test/graph_flows/step9_user.csv'));
        $checkRole = $this->checkResourceWithCSV(Role::class, storage_path('data_test/graph_flows/step9_role.csv'));
        //step 10 - 11
        (new ExportAzureAD())->handle();*/
        //step 12 -14
        $checkUser = $this->checkResourceWithCSV(User::class, storage_path('data_test/graph_flows/step10_user.csv'), ['externalID', 'UpdateFlags']);
        //step 13
        $checkRole = $this->checkResourceWithCSV(Role::class, storage_path('data_test/graph_flows/step11_role.csv'), ['externalID', 'UpdateFlags']);
        //step 15: remove user03 from group g1
        $graphLib = new UserGraphAPI();
        try{
            $user03ID = User::select('externalID')->where('ID', 'user03')->first()->toArray()['externalID'];
            $g1ID = Role::select('externalID')->where('ID', 'g1')->first()->toArray()['externalID'];
            $graphLib->removeMemberOfGroup($user03ID, $g1ID);
        }
        catch (\Exception $exception){
            echo $exception->getMessage();
        }
        $groupsList = $graphLib->getMemberOfsAD($user03ID);
        echo 'test';

    }

    /**
     * @param string $class
     * @param string $userCsvFilePath
     * @return bool
     */
    private function checkResourceWithCSV(string $class, string $userCsvFilePath, $except): bool
    {
        $users = ($class::all()->toArray());
        $users = $this->unsetUnexpectedKeys($except, $users);
        $usersFromCSV = getArrayFromCSV($userCsvFilePath);
        $usersFromCSV = $this->unsetUnexpectedKeys($except, $usersFromCSV);

        $diff = array_diff_assoc_recursive_ignore($users, $usersFromCSV, ['externalID']);
//        if (check_similar($users, $usersFromCSV)) {
        if (empty($diff)) {
            echo "\nPass CSV check of table $class";
            $check = true;
        } else {
            echo "\nFAIL CSV check of table $class";
            var_dump($diff);
            $check = false;
        }
        return $check;
    }

    /**
     * @param $except
     * @param $users
     * @return mixed
     */
    private function unsetUnexpectedKeys($except, $users)
    {
        foreach ($users as &$user) {
            foreach ($except as $e) {
                unset($user[$e]);
            }
        }
        return $users;
    }
}
