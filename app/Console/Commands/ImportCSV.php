<?php

namespace App\Console\Commands;

use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DBImporter;
use App\Ldaplibs\SettingsManager;
use Illuminate\Console\Command;
use DB;
use Marquine\Etl\Job;

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
        $csv_reader = new CSVReader(new SettingsManager());
        $list_file_csv = $csv_reader->get_list_file_csv_setting();

        foreach ($list_file_csv as $item) {
            $setting = $item['setting'];
            $list_file = $item['file_csv'];

            foreach ($list_file as $file) {
                $db_importer = new DBImporter($setting, $file);
                $db_importer->import();
            }
        }
    }
}
