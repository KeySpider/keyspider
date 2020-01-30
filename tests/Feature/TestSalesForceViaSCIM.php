<?php

namespace Tests\Feature;

use bjsmasth\Salesforce\Authentication\PasswordAuthentication;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;


class TestSalesForceViaSCIM extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function setUp()
    {

        parent::setUp(); // TODO: Change the autogenerated stub
    }

    public function testExample()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $options = [
            'grant_type' => 'password',
//            'client_id' => '3MVG9n_HvETGhr3CdmqWTiR77Q_qn8pnO8XeKx1p6Pu7C_5Mz3nCRHDlKUHHReVrmJM1gTjDBhKS.wkrQrPhf',
            'client_id' => '3MVG9G9pzCUSkzZtQhmyLq3TUSdPdWhKaUzMAr3Gyr73oUK4Kxf.JIjEt1t_Y8l4SAoHfoiH2GTsnc8WR8JX7',
//            'client_secret' => '27530A5695008D8D6B064EE431B8EEA63FE7D8566E8525964286D5B45313C513',
            'client_secret' => 'F7F54A0096F01D6F7992DC7D4BEBF14599E0F6554FDCCCEB1987BC498D554E16',
            'username' => 'vntuanla@gmail.com',
            'password' => '1qs2wd3efVU8iz0SQY8Wml7bFWtAZUW1bI'
        ];

        $salesforce = new PasswordAuthentication($options);
//        $salesforce = new bjsmasth\Salesforce\Authentication\PasswordAuthentication($options);
        $salesforce->authenticate();

        $access_token = $salesforce->getAccessToken();
        $instance_url = $salesforce->getInstanceUrl();
        var_dump($access_token);

        $query = 'SELECT Id,Name FROM USER LIMIT 10';

        $crud = new \bjsmasth\Salesforce\CRUD();

        $data = [
            'userName' => '20201@gmail.com',
            'Email' => '20201@gmail.com',
            'Alias' => '2020',
            'TimeZoneSidKey' => 'Asia/Ho_Chi_Minh',
            'LocaleSidKey' => 'en_GB',
            'EmailEncodingKey' => 'UTF-8',
            'LanguageLocaleKey' => 'ja',
            'LastName' => '20201',
            'ProfileId' => '00e2v000004V1oR',
//'Name'=>'2020'
        ];
        $crud->create('USER', $data);  #returns id

        var_dump(json_encode($crud->query($query), JSON_PRETTY_PRINT));

//        $crud->create('Account', $data);  #returns id
/*



        $query = 'SELECT Id,Name FROM ACCOUNT LIMIT 100';

        $crud = new \bjsmasth\Salesforce\CRUD();
        $crud->query($query);*/
    }
}
