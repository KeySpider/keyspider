<?php

namespace App\Console\Commands;

use bjsmasth\Salesforce\Authentication\PasswordAuthentication;
use Illuminate\Console\Command;

class TestSalesForceViaSCIM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:testSF';

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
        $options = [
            'grant_type' => 'password',
            'client_id' => 'CONSUMERKEY',
            'client_secret' => 'CONSUMERSECRET',
            'username' => 'SALESFORCE_USERNAME',
            'password' => 'SALESFORCE_PASSWORD AND SECURITY_TOKEN'
        ];

        $salesforce = new PasswordAuthentication($options);
//        $salesforce = new bjsmasth\Salesforce\Authentication\PasswordAuthentication($options);
        $salesforce->authenticate();

        $access_token = $salesforce->getAccessToken();
        $instance_url = $salesforce->getInstanceUrl();

        $query = 'SELECT Id,Name FROM ACCOUNT LIMIT 100';

        $crud = new \bjsmasth\Salesforce\CRUD();
        $crud->query($query);
    }
}
