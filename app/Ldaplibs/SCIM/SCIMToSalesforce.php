<?php


namespace App\Ldaplibs\SCIM;


use bjsmasth\Salesforce\Authentication\PasswordAuthentication;
use bjsmasth\Salesforce\Exception\SalesforceAuthentication;
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
//        $instance_url = $salesforce->getInstanceUrl();
        var_dump($access_token);

//        $query = 'SELECT Id,Name FROM USER LIMIT 10';
        $this->crud = new \bjsmasth\Salesforce\CRUD();
    }

    public function createUserWithData(array $data=null)
    {
        if($data == null) {
            $data = json_decode(Config::get('schemas.createUser'));
        }

//        dd(json_encode($data));
        try{
            return $this->crud->create('USER', $data);  #returns id
        }
        catch (\Exception $exception){
            echo ($exception->getMessage());
            return -1;
        }
    }

    public function createGroupWithData(array $data=null)
    {
        if($data == null) {
            $data = json_decode(Config::get('schemas.createGroup'));
        }

//        dd(json_encode($data));
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

    public function getUsersList(){
        return $this->crud->getResourceList('Users');
    }

    public function getGroupsList(){
        return $this->crud->getResourceList('Groups');
    }

    public function addMemberToGroup($memberId, $groupId){
        dd($this->crud->addMemberToGroup($memberId, $groupId));
    }

    public function updateResource($resourceType, $resourceId, $data){
        $this->crud->update($resourceType, $resourceId, $data);
    }
}