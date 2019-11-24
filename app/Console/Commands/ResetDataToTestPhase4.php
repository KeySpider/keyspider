<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\Feature\TestMSGraphGroup;
use Tests\Feature\TestMSGraphUser;

class ResetDataToTestPhase4 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:reset_data_ph4';

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
        $testMSGraphUser = new TestMSGraphUser();
        $testMSGraphUser->setUp();
        $testMSGraphUser->testDeleteAllUser();
        $testMSGraphGroup = new TestMSGraphGroup();
        $testMSGraphGroup->setUp();
        $testMSGraphGroup->testDeleteAllGroups();
        (new \Tests\Automation\TestFlow())->testScenario();
    }
}
