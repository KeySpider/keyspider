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
use Illuminate\Support\Facades\Storage;

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

        foreach ($data_process as $one_file) {
            $this->process($one_file);
        }
        die;
    }

    /**
     * @param $one_file
     */
    protected function process($one_file)
    {
        $csv_reader = new CSVReader(new SettingsManager());
        $setting = $one_file['setting'];
        $files = $one_file['files'];

        $table = $csv_reader->get_name_table_from_setting($setting);
        $columns = $csv_reader->get_all_column_from_setting($setting);
        $csv_reader->create_table($table, $columns);

        $params = [
            'CONVERSATION' => $setting[self::CONVERSION],
        ];

        foreach ($files as $file) {
            $data = $csv_reader->get_data_from_one_file($file, $params);
            $db_importer = new DBImporter($table, implode(",", $columns), $data);
            $check_import = $db_importer->import();

            if ($check_import) {
                // move file with new name
                $this->move_file_after_process($setting[self::CONFIGURATION]['ProcessedFilePath'], $file);
            }
        }
    }

    protected function move_file_after_process($file_path, $file)
    {
        // check exit dir
        $file_path = storage_path($file_path);

        if (!is_dir($file_path)) {
            File::makeDirectory($file_path, 0755, true);
        }

        dump($file);
        dump($file_path);
        Storage::move($file, "{$file_path}/abc.csv");
        die;
    }
}
