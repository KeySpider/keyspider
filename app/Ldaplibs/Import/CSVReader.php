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

/**
 * Class CSVReader
 *
 * @package App\Ldaplibs\Import
 */
class CSVReader implements DataInputReader
{
    protected $setting;

    /**
     * define const
     */
    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Basic Configuration";

    /**
     * CSVReader constructor.
     * @param SettingsManager $setting
     */
    public function __construct(SettingsManager $setting)
    {
        $this->setting = $setting;
    }

    /**
     * Get all csv file from setting
     *
     * @return array
     */
    public function getListFileCsvSetting()
    {
        // get name table from file setting
        $data_csv = [];
        $settings = $this->setting->getRuleOfImport();

        if (!empty($settings)) {
            foreach ($settings as $setting) {
                // Scan file csv from path, setting
                $path = $setting[self::CONFIGURATION]['FilePath'];

                $options = [
                    'file_type' => 'csv',
                    'pattern' => $setting[self::CONFIGURATION]['FileName'],
                ];

                $pattern = $options['pattern'];
                $listFileCSV = [
                    "setting" => $setting,
                    "file_csv" => [],
                ];
                $pathDir = $path;
                $validate_file = ['csv'];

                if (is_dir($pathDir)) {
                    foreach (scandir($pathDir) as $key => $file) {
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        if (in_array($ext, $validate_file)) {
                            if (preg_match("/{$this->removeExt($pattern)}/", $this->removeExt($file))) {
                                array_push($listFileCSV['file_csv'], "{$path}/{$file}");
                            }
                        }
                    }

                    array_push($data_csv, $listFileCSV);
                }
            }
            return $data_csv;
        }
    }

    /** Get name table from setting file
     *
     * @param array $setting
     *
     * @return string
     */
    public function getNameTableFromSetting($setting)
    {
        $name_table = $setting[self::CONFIGURATION]['TableNameInDB'];
        $name_table = "\"{$name_table}\"";
        return $name_table;
    }

    /**
     * Get all column from setting file
     *
     * @param array $setting
     *
     * @return array
     */
    public function getAllColumnFromSetting($setting)
    {
        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        $fields = [];
        foreach ($setting[self::CONVERSION] as $key => $item) {
            if ($key !== "" && preg_match($pattern, $key) !== 1) {
                array_push($fields, "\"{$key}\"");
            }
        }
        return $fields;
    }

    /**
     * Create table from setting file
     *
     * @param string $name_table
     * @param array $columns
     *
     * @return void
     */
    public function createTable($name_table, $columns = [])
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
     * Get data from one csv file
     *
     * @param string $file_csv
     * @param array $options
     *
     * @return array
     */
    public function getDataFromOneFile($file_csv, $options = [])
    {
        $data = [];
        if (is_file($file_csv)) {
            foreach (file($file_csv) as $line) {
                $data_line = str_getcsv($line);
                $data[] = $this->getDataAfterProcess($data_line, $options);
            }
        }
        return $data;
    }

    /**
     * Get data after process
     *
     * @param array $data_line
     * @param array $options
     *
     * @return array
     */
    protected function getDataAfterProcess($data_line, $options = [])
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
                $data[$col] = $this->convertDataFollowSetting($pattern, $data_line);
            }
        }

        return $data;
    }

    /**
     * Covert data follow from setting
     *
     * @param string $pattern
     * @param array $data
     *
     * @return mixed|string
     */
    protected function convertDataFollowSetting($pattern, $data)
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

    /**
     * Process group from pattern
     *
     * @param string $str
     * @param array $group
     *
     * @return string
     */
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
     * Remove extension of file
     *
     * @param string $file_name
     *
     * @return string
     */
    protected function removeExt($file_name)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
        return $file;
    }
}
