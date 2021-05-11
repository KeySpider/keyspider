<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PrepareImportData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:copydata';

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
        echo "Copy sample data to test import\n";
        $source = storage_path('unittest/import_csv');
        $destination = storage_path('import_csv');
        echo "Source: $source\n";
        echo "Dest  : $destination\n";

        if (\File::copyDirectory($source, $destination)) {
            echo "Success\n";
        } else {
            echo "Failed\n";
        }
    }
}
