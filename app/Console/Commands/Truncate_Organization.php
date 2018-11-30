<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class Truncate_Organization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:Truncate_Organization';

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
        DB::statement("TRUNCATE \"BBB\"");
        $this->info("Truncate table organization is successful");
    }
}
