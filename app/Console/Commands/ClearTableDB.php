<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class ClearTableDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:dropTable';

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
        $enviroment = config('app.env');
        
        if ($enviroment === "local" || $enviroment === "develop") {
            DB::statement("DROP SCHEMA public CASCADE;");
            DB::statement("CREATE SCHEMA public;");
            DB::statement("GRANT ALL ON SCHEMA public TO postgres;");
            DB::statement("GRANT ALL ON SCHEMA public TO public;");
            $this->info("Drop all table is successful");
        } else {
            $this->info("You are not author");
        }
    }
}
