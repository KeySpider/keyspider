<?php

namespace App\Console\Commands;

use App\Http\Models\AAA;
use App\Http\Models\BBB;
use App\Http\Models\CCC;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ClearDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ClearDatabase';

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
        if (Schema::hasTable('AAA')) {
            AAA::truncate();
        }

        if (Schema::hasTable('BBB')) {
            BBB::truncate();
        }

        if (Schema::hasTable('CCC')) {
            CCC::truncate();
        }
    }
}
