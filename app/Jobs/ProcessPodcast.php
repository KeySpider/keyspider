<?php

namespace App\Jobs;

use App\Ldaplibs\Import\CSVReader;
use App\Ldaplibs\Import\DBImporter;
use App\Ldaplibs\SettingsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use File;
use DB;

class ProcessPodcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data_setting;

    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Bacic Configuration";

    /**
     * Create a new job instance.
     *
     * @param $data_setting
     */
    public function __construct($data_setting)
    {
        $this->data_setting = $data_setting;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data_process = $this->data_setting;

        if (!empty($data_process)) {
            foreach ($data_process as $one_file) {
                $csv_reader = new CSVReader(new SettingsManager());
                $setting    = $one_file['setting'];
                $files      = $one_file['files'];

                $table = $csv_reader->get_name_table_from_setting($setting);
                $columns = $csv_reader->get_all_column_from_setting($setting);
                $csv_reader->create_table($table, $columns);

                $columns = implode(",", $columns);

                $params = [
                    'CONVERSATION' => $setting[self::CONVERSION],
                ];

                foreach ($files as $file) {
                    $data = $csv_reader->get_data_from_one_file($file, $params);
                    foreach ($data as $key2 => $item2) {
                        $tmp = [];
                        foreach ($item2 as $key3 => $item3) {
                            array_push($tmp, "'{$item3}'");
                        }
                        $tmp = implode(",", $tmp);

                        DB::statement("
                                    INSERT INTO {$table}({$columns}) values ({$tmp});
                                ");
                    }
                }
            }
        }
    }
}
