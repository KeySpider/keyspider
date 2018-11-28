<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 6:50 PM
 */

namespace App\Ldaplibs\Import;
use App\Ldaplibs\Import\DataInputReader;
use App\Ldaplibs\SettingsManager;
use DB;
use Carbon\Carbon;

class CSVReader implements DataInputReader
{
    protected $setting;

    /**
     * define const
     */
    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Bacic Configuration";

    public function __construct(SettingsManager $setting)
    {
        $this->setting = $setting;
    }

    /**
     * Process reader file csv
     */
    public function process()
    {
        // get name table from file setting
        $setting = $this->setting->get_rule_of_import();
        $name_table = $this->get_name_table_from_setting($setting);

        // get all columns from file setting
        $columns = $this->get_all_column_from_setting($setting);

        // New table from file setting
        $this->create_table($name_table, $columns);

        // Scan file csv from path, setting
        $path = $setting[self::CONFIGURATION]['FilePath'];
        $options = [
            'file_type' => 'csv',
            'pattern' => $setting[self::CONFIGURATION]['FileName'],
        ];
        $list_file_csv = $this->scan_file($path, $options);

        // get all data from all file csv
        $all_data = $this->get_data_from_all_file_csv($list_file_csv);

        // finish , insert data into pgsql
        $this->insert_all_data_DB($all_data);
    }

    /**
     * @param $setting
     * @return string
     */
    public function get_name_table_from_setting($setting)
    {
        $name_table = 'AAA';
        return $name_table;
    }

    /**
     * @param $setting
     * @return array
     */
    public function get_all_column_from_setting($setting)
    {
        $name_table = $this->get_name_table_from_setting($setting);
        $setting = $this->setting->get_rule_of_import();
        $params = $setting[self::CONVERSION];

        $fields = [];
        foreach ($params as $key => $item) {
            if ($key !== "" && preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $key) !== 1) {
                $search = "{$name_table}.";
                $newKey = str_replace($search, '', $key);
                array_push($fields, "\"{$newKey}\"");
            }
        }
        return $fields;
    }

    /**
     * @param $name_table
     * @param array $columns
     */
    public function create_table($name_table, $columns = [])
    {
        $sql = "";
        foreach ($columns as $key => $col) {
            if ($key < count($columns) - 1) {
                $sql .= "{$col} VARCHAR (50) NULL,";
            } else {
                $sql .= "{$col} VARCHAR (50) NULL";
            }
        }
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$name_table}(
                {$sql}
            );
        ");
    }

    /**
     * @param $path
     * @param array $options
     * @return array
     */
    public function scan_file($path, $options = [])
    {
        $pattern = $options['pattern'];
        $files = [];
        $pathDir = storage_path("{$path}");
        foreach (scandir($pathDir) as $key => $file) {
            if (preg_match("/{$this->remove_ext($pattern)}/", $this->remove_ext($file))) {
                array_push($files, "{$path}/{$file}");
            }
        }
        return $files;
    }

    /**
     * @param array $list_file_csv
     * @return array
     */
    public function get_data_from_all_file_csv($list_file_csv = [])
    {
        $all_data = [];
        foreach ($list_file_csv as $file_csv) {
            $all_data[] = $this->get_data_from_one_file_csv($file_csv);
        }
        return $all_data;
    }

    /**
     * @param $file_csv
     * @return array
     */
    public function get_data_from_one_file_csv($file_csv)
    {
        $data = [];
        $path = $pathDir = storage_path("{$file_csv}");
        foreach (file($path) as $line) {
            $data_line = str_getcsv($line);
            $data[] = $this->get_data_after_process($data_line);
        }
        return $data;
    }

    /**
     * @param $big_data
     */
    public function insert_all_data_DB($big_data)
    {
        $setting = $this->setting->get_rule_of_import();

        $name_table = $this->get_name_table_from_setting($setting);
        $columns = $this->get_all_column_from_setting($setting);
        $columns = implode(",", $columns);

        // bulk insert
        foreach ($big_data as $key => $item) {
            foreach ($item as $key2 => $item2) {
                $tmp = [];
                foreach ($item2 as $key3 => $item3) {
                    array_push($tmp, "'{$item3}'");
                }

                $tmp = implode(",", $tmp);

                // insert
                DB::statement("
                    INSERT INTO {$name_table}({$columns}) values ({$tmp});
                ");
                $tmp = [];
            }
        }
    }

    /**
     * @param $data_line
     * @return array
     */
    protected function get_data_after_process($data_line)
    {
        $data = [];
        $setting     = $this->setting->get_rule_of_import();
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
                $data[$col] = $this->convert_data_follow_setting($pattern, $data_line);
            }
        }

        return $data;
    }

    /**
     * @param $pattern
     * @param $data
     * @return mixed|string
     */
    protected function convert_data_follow_setting($pattern, $data)
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

    /**
     * @param $file_name
     * @return string|string[]|null
     */
    protected function remove_ext($file_name)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
        return $file;
    }
}
