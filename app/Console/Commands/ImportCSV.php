<?php

namespace App\Console\Commands;

use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Storage;
use Marquine\Etl\Job;
use function Symfony\Component\Console\Tests\Command\createClosure;

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
        $this->renderAllFileCSV($table, $list_file);
    }

    /**
     * @param $table
     * @param $params
     * @return array
     */
    protected function getColumn($table, $params)
    {
        $fields = [];
        foreach ($params as $key => $item) {
            if ($key !== "" && preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $key) !== 1) {
                $search = "{$table}.";
                $newKey = str_replace($search, '', $key);
                array_push($fields, $newKey);
            }
        }
        return $fields;
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
        $fields = $this->getColumn($table, $params);
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
    protected function renderAllFileCSV($table, $files = [])
    {
        foreach ($files as $fileName) {
            $data[] = $this->processFileCSV($fileName);
        }

        $setting = $this->setting();
        $columns = $this->getColumn($table, $setting[self::CONVERSION]);

        $prColumn = [];
        foreach ($columns as $cl) {
            array_push($prColumn, "\"{$cl}\"");
        }
        $columns = implode(",", $prColumn);

        // bulk insert
        foreach ($data as $key => $item) {
            foreach ($item as $key2 => $item2) {
                $tmp = [];
                foreach ($item2 as $key3 => $item3) {
                    array_push($tmp, "'{$item3}'");
                }

                $tmp = implode(",", $tmp);

                // insert
                DB::statement("
                    INSERT INTO {$table}({$columns}) values ({$tmp});
                ");
                $tmp = [];
            }
        }
    }

    /**
     * @param $fileName
     * @return array
     */
    protected function processFileCSV($fileName)
    {
        $path = $pathDir = storage_path("{$fileName}");

        foreach (file($path) as $line) {
            $data_line = str_getcsv($line);
            $data[] = $this->getDataMaster($data_line);
        }

        return $data;
    }

    /**
     * @param $dataRows
     * @return array
     */
    protected function getDataMaster($dataRows)
    {
        $data = [];
        $setting     = $this->setting();
        $conversions = $setting[self::CONVERSION];

        foreach ($conversions as $key => $item) {
            if ($key === "" || preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $key) === 1) {
                unset($conversions[$key]);
            }
        }

        foreach ($conversions as $col => $pattern) {
            if ($pattern === 'admin') {
                $data[$col] = 'admin';
            } else if ($pattern === 'TODAY()') {
                $data[$col] = Carbon::now()->format('Y/m/d');
            } else if ($pattern === '0') {
                $data[$col] = '0';
            } else {
                $data[$col] = $this->convertData($pattern, $dataRows);
            }
        }

        return $data;
    }

    /**
     * @param $pattern
     * @param $data
     * @return mixed|string
     */
    protected function convertData($pattern, $data)
    {
        $stt = null;
        $group = null;
        $regx = null;

        $success = preg_match('/\(\s*(?<exp1>\d+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/', $pattern, $match);
        if ($success) {
            $stt = (int)$match['exp1'];
            $regx = $match['exp2'];
            $group = (int) str_replace('$','',$match['exp3']);
        } else {
            print_r('Error');
        }

        foreach ($data as $key => $item) {
            if ($stt === $key + 1) {
                if ($regx === "") {
                    return $item;
                } else {
                    if ($regx === '\s') {
                        return str_replace(' ', '', $item);
                    }
                    if ($regx === '\w') {
                        return strtolower($item);
                    }
                    $check = preg_match("/{$regx}/", $item, $str);
                    if ($check) {
                        return $str[$group];
                    }
                }
            }
        }
    }
}
