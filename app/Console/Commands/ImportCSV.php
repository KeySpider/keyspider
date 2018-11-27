<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class ImportCSV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ImportCSV';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reader setting import file and process it';

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
     * @throws \Exception
     */
    public function handle()
    {
        DB::insert('insert into users (id, name, email, password) values (?, ?, ?, ?)', [
            random_int(1,1000),
            'Dayle',
            random_int(1,1000).'lebangu@gmail.com',
            '123123'
        ]);
    }
}
