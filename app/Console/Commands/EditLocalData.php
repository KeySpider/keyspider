<?php

namespace App\Console\Commands;

use App\User;
use Faker\Factory;
use Illuminate\Console\Command;

class EditLocalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:change_data';

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
        $faker = Factory::create();
        $domain = $faker->domainName;
        $users = User::all();
        foreach ($users as $user) {
            $id = $user->toArray()['ID'];
            $name = "$id@$domain";
            $user->where('ID', $id)->update(['Name' => $name, 'mail' => $name, 'displayName'=>$faker->lastName]);
        }

    }
}
