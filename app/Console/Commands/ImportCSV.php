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

    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Bacic Configuration";

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
        dd($list_file_csv);

        foreach ($list_file_csv as $item) {
            $setting = $item['setting'];
            $list_file = $item['file_csv'];

            $table = $csv_reader->get_name_table_from_setting($setting);
            $columns = $csv_reader->get_all_column_from_setting($setting);
            $csv_reader->create_table($table, $columns);

            $params = [
                'CONVERSATION' => $setting[self::CONVERSION],
            ];
            
            foreach ($list_file as $file) {
                $data = $csv_reader->get_data_from_one_file($file, $params);
                $db_importer = new DBImporter($table, implode(",", $columns), $data);
                $db_importer->import();
            }
        }

        $this->info('**======= Import Data CSV is successfully generated! ======****');
    }
}
