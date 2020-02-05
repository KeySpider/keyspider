<?php


namespace App\Ldaplibs\SCIM;


use bjsmasth\Salesforce\Authentication\PasswordAuthentication;
use bjsmasth\Salesforce\Exception\SalesforceAuthentication;
use Faker\Factory;
use Illuminate\Support\Facades\Config;

class SCIMToSalesforce
{
    public function __construct()
    {
        $options = [
            'grant_type' => 'password',
/*            'client_id' => '3MVG9G9pzCUSkzZtQhmyLq3TUSdPdWhKaUzMAr3Gyr73oUK4Kxf.JIjEt1t_Y8l4SAoHfoiH2GTsnc8WR8JX7',
            'client_secret' => 'F7F54A0096F01D6F7992DC7D4BEBF14599E0F6554FDCCCEB1987BC498D554E16',
            'username' => 'vntuanla@gmail.com',
            'password' => '1qs2wd3efVU8iz0SQY8Wml7bFWtAZUW1bI'*/
            'client_id' => '3MVG9G9pzCUSkzZtQhmyLq3TUSepG1qXYndCYGaDpR_Tcv0RnVYUBzhbRwsxjpjCfAwQqhg7hSFs4OABtbRLh',
            'client_secret' => '605E0082BC5A093DE4271525C3BC43710B7DA9BDA55E31A360AEEF068B17FF41',
            'username' => 'tuanla710@gmail.com',
            'password' => '1qs2wd3efMTfuHJaWpXRnfnOUim1it8BLo'
        ];

        $salesforce = new PasswordAuthentication($options);
        try {
            $salesforce->authenticate();
        } catch (SalesforceAuthentication $e) {
        }

        $access_token = $salesforce->getAccessToken();
        echo "\n";
        var_dump($access_token);
        echo "\n";

        $this->crud = new \bjsmasth\Salesforce\CRUD();
    }

    public function createUserWithData(array $data=null)
    {
        if($data == null) {
            $data = json_decode(Config::get('schemas.createUser'));
        }
        try{
            return $this->crud->create('USER', $data);  #returns id
        }
        catch (\Exception $exception){
            echo ($exception->getMessage());
            return -1;
        }
    }

    public function createResource($resourceType, array $data=null)
    {
        $faker = Factory::create();
        $dataSchema = json_decode(Config::get('schemas.createUser'), true);
        $data['IsActive'] = $data['IsActive']?false:true;
        if($resourceType=='User'){
            $resourceType = 'USER';
            foreach ($dataSchema as $key=>$value){
                if(in_array($key, array_keys($data))){
                    if($key=='Alias'){
                        $data[$key] = substr($data[$key], 0, 7);
                    }
                    $dataSchema[$key] = $data[$key];
                }
                elseif ($key=='Alias'){
                    $dataSchema[$key] = $faker->text(8);
                }
                elseif ($key=='LastName'){
                    $dataSchema[$key] = $faker->lastName;
                }
                elseif ($key=='Email'){
                    $dataSchema[$key] = $faker->freeEmail;
                }
            }

        }elseif ($resourceType=='Role'){
            $dataSchema = json_decode(Config::get('schemas.createGroup'), true);
            $resourceType = 'GROUP';
        }
        echo ("Create User with data: \n");
        echo(json_encode($dataSchema, JSON_PRETTY_PRINT));
        try{
            return $this->crud->create($resourceType, $dataSchema);  #returns id
        }
        catch (\Exception $exception){
            echo ($exception->getMessage());
            return null;
        }
    }

    public function createGroupWithData(array $data=null)
    {
        if($data == null) {
            $data = json_decode(Config::get('schemas.createGroup'));
        }
        try{
            return $this->crud->create('GROUP', $data);  #returns id
        }
        catch (\Exception $exception){
            echo ($exception->getMessage());
            return -1;
        }
    }

    public function getUser($id){
        return $this->crud->getResourceDetail('Users', $id);
    }
    public function getGroup($id){
        return $this->crud->getResourceDetail('Groups', $id);
    }

    public function getResourceDetails ($resourceId, $resourceType){
        $resourceType = $this->getResourceTypeOfSF($resourceType);
        return $this->crud->getResourceDetail($resourceType, $resourceId);
    }

    public function getUsersList(){
        return $this->crud->getResourceList('Users');
    }

    public function getGroupsList(){
        return $this->crud->getResourceList('Groups');
    }

    public function addMemberToGroup($memberId, $groupId){
        dd($this->crud->addMemberToGroup($memberId, $groupId));
    }

    public function updateResource($resourceType, $data){
        $resourceType = $this->getResourceTypeOfSF($resourceType, true);
        $resourceId = $data['externalSFID'];
        unset($data['externalSFID']);
        $data['IsActive'] = $data['IsActive']?false:true;
        $dataSchema = json_decode(Config::get('schemas.createUser'), true);
        if($resourceType=='User'){
            $resourceType = 'USER';
            foreach ($data as $key=>$value){
                if(!in_array($key, array_keys($dataSchema))){
                    unset($data[$key]);
                }
                if($key=='Alias'){
                    $data[$key] = substr($data[$key], 0, 7);
                }
            }
        }

        echo ("\nUpdate User with data: \n");
        echo(json_encode($data, JSON_PRETTY_PRINT));

        $update = $this->crud->update($resourceType, $resourceId, $data);
        echo("\nUpdate: $update");
        return $update;
    }

    /**
     * @param $resourceType
     * @return string
     */
    private function getResourceTypeOfSF($resourceType, $isREST=false): string
    {
        if ($resourceType == 'User') {
            $resourceType = $isREST?'User':'Users';
        } elseif ($resourceType == 'Role') {
            $resourceType = $isREST?'Group':'Groups';
        }
        return $resourceType;
    }
}