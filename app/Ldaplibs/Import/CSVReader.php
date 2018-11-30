<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 6:50 PM
 *
 * Updated by Le Ba Ngu
 * Date: 2108/11/28
 */

namespace App\Ldaplibs\Import;
use App\Ldaplibs\Import\DataInputReader;
use App\Ldaplibs\SettingsManager;
use DB;
use Carbon\Carbon;
use Mockery\Exception;

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

    public function get_list_file_csv_setting()
    {
        // get name table from file setting
        $data_csv = [];
        $settings = $this->setting->get_rule_of_import();

        if (!empty($settings)) {
            foreach ($settings as $setting) {
                // Scan file csv from path, setting
                $path = $setting[self::CONFIGURATION]['FilePath'];

                $options = [
                    'file_type' => 'csv',
                    'pattern' => $setting[self::CONFIGURATION]['FileName'],
                ];

                $pattern = $options['pattern'];
                $list_file_csv = [
                    "setting" => $setting,
                    "file_csv" => [],
                ];
                $pathDir = storage_path("{$path}");
                $validate_file = ['csv'];

                if (is_dir($pathDir)) {
                    foreach (scandir($pathDir) as $key => $file) {
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        if (in_array($ext, $validate_file)) {
                            if (preg_match("/{$this->remove_ext($pattern)}/", $this->remove_ext($file))) {
                                array_push($list_file_csv['file_csv'], "{$path}/{$file}");
                            }
                        }
                    }

                    array_push($data_csv, $list_file_csv);
                }
            }
            return $data_csv;
        } else {
            dump('ko ton tai file setting');
        }
    }

    /**
     * @param $setting
     * @return string
     */
    public function get_name_table_from_setting($setting)
    {
        $name_table = $setting[self::CONFIGURATION]['TableNameInDB'];
        return $name_table;
    }

    /**
     * @param $setting
     * @return array
     */
    public function get_all_column_from_setting($setting)
    {
        $name_table = $this->get_name_table_from_setting($setting);
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
                $sql .= "{$col} VARCHAR (250) NULL,";
            } else {
                $sql .= "{$col} VARCHAR (250) NULL";
            }
        }
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$name_table}(
                {$sql}
            );
        ");
    }

    /**
     * @param $file_csv
     * @param array $options
     * @return array
     */
    public function get_data_from_one_file($file_csv, $options = [])
    {
        $data = [];
        $path = $pathDir = storage_path("{$file_csv}");
        foreach (file($path) as $line) {
            $data_line = str_getcsv($line);
            $data[] = $this->get_data_after_process($data_line, $options);
        }
        return $data;
    }

    /**
     * @param $data_line
     * @param array $options
     * @return array
     */
    protected function get_data_after_process($data_line, $options = [])
    {
        $data = [];
        $conversions = $options['CONVERSATION'];

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
            $group = $match['exp3'];
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
                        return $this->processGroup($str, $group);
                    }
                }
            }
        }
    }

    protected function processGroup($str, $group)
    {
        if ($group === "$1") {
            return $str[1];
        }

        if ($group === "$2") {
            return $str[2];
        }

        if ($group === "$3") {
            return $str[3];
        }

        if ($group === "$1/$2/$3") {
            return "{$str[1]}/{$str[2]}/{$str[3]}";
        }

        return '';
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
