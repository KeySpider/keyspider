<?php

namespace App\Console\Commands;

use App\Ldaplibs\SettingsManager;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Storage;
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
     * define const
     */
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
        $setting = $this->setting();
        $table = 'AAA';

        // create table
        $this->createTable($table, $setting[self::CONVERSION]);

        $filePath = $setting[self::CONFIGURATION]['FilePath'];
        $fileName = $setting[self::CONFIGURATION]['FileName'];

        // scan csv file with path and regular
        $list_file = $this->scanFileCSV($filePath, $fileName);

        // reader data from list csv
        $this->readerDataFromListCSV($table, $list_file);
    }

    /**
     * Create table from setting file
     * @param $table
     * @param $params
     *
     * @author Le Ba Ngu
     */
    protected function createTable($table, $params)
    {
        $fields = [];
        foreach ($params as $key => $item) {
            if ($key !== "" && preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $key) !== 1) {
                $search = "{$table}.";
                $newKey = str_replace($search, '', $key);
                array_push($fields, $newKey);
            }
        }

        $sql = "";
        foreach ($fields as $key => $column) {
            if ($key < count($fields) - 1) {
                $sql .= "\"{$column}\" VARCHAR (50) NULL,";
            } else {
                $sql .= "\"{$column}\" VARCHAR (50) NULL";
            }
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$table}(
                {$sql}
            );
        ");
    }

    /**
     * Get data json from setting file
     * @return array
     *
     * @author Le Ba Ngu
     */
    protected function setting()
    {
        $import_settings = new SettingsManager();
        $setting = $import_settings->get_rule_of_import();
        return $setting;
    }

    /**
     * @param $filePath
     * @param $pattern
     * @return array
     */
    protected function scanFileCSV($filePath, $pattern)
    {
        $files = [];
        $pathDir = storage_path("{$filePath}");

        foreach (scandir($pathDir) as $key => $file) {
            if (preg_match("/{$this->remove_ext($pattern)}/", $this->remove_ext($file))) {
                array_push($files, "{$filePath}/{$file}");
            }
        }
        return $files;
    }

    /**
     * @param $fileName
     * @return string|string[]|null
     */
    protected function remove_ext($fileName)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileName);
        return $file;
    }

    /**
     * @param array $files
     */
    protected function readerDataFromListCSV($table, $files = [])
    {
        foreach ($files as $file) {
            $this->processFileCSV($table, $file);
        }
    }

    protected function processFileCSV($table, $fileName)
    {
        $path        = $pathDir = storage_path("{$fileName}");

        foreach (file($path) as $line) {
            $data_line = str_getcsv($line);
            $this->saveData($data_line);
        }
    }

    protected function saveData($dataRows)
    {
        $setting     = $this->setting();
        $conversions = $setting[self::CONVERSION];

        foreach ($conversions as $key => $item) {
            if ($key === "" || preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $key) === 1) {
                unset($conversions[$key]);
            }
        }

        $data = [];

        foreach ($conversions as $col => $pattern) {

            $success = preg_match('/\(\s*(?<exp1>\d+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/', $pattern, $match);
            if ($success) {
                foreach ($dataRows as $key => $value) {
                    $tmp = $conversions;

                }
            }
        }
    }
}
