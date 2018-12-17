<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class DropAllTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:DropAllTable';

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
        $environment = config('app.env');
        if ($environment === "testing" || $environment === "develop" || $environment === "localhost") {
            DB::statement("DROP SCHEMA public CASCADE;");
            DB::statement("CREATE SCHEMA public;");
        }
    }
}
