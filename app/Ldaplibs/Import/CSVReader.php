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

use App\Ldaplibs\SettingsManager;
use DB;
use Carbon\Carbon;

use League\Csv\Reader;
use League\Csv\Statement;

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
        $dataCSV = [];
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

                if (is_dir($pathDir)) {
                    foreach (scandir($pathDir) as $key => $file) {
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        if (in_array($ext, ['csv'])) {
                            $newPattern = removeExt($pattern);
                            $newFile = removeExt($file);
                            if (preg_match("/{$newPattern}/", $newFile)) {
                                array_push($listFileCSV['file_csv'], "{$path}/{$file}");
                            }
                        }
                    }

                    array_push($dataCSV, $listFileCSV);
                }
            }
            return $dataCSV;
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
        $nameTable = $setting[self::CONFIGURATION]['TableNameInDB'];
        $nameTable = "\"{$nameTable}\"";
        return $nameTable;
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
                $key = explode('.', $key);
                array_push($fields, "\"{$key[1]}\"");
            }
        }
        return $fields;
    }

    /**
     * Create table from setting file
     *
     * @param $nameTable
     * @param array $columns
     *
     * @return void
     */
    public function createTable($nameTable, $columns = [])
    {
        $sql = "";

        foreach ($columns as $key => $col) {
            if ($key < count($columns) - 1) {
                $sql .= "ADD COLUMN if not exists {$col} VARCHAR (250) NULL,";
            } else {
                $sql .= "ADD COLUMN if not exists {$col} VARCHAR (250) NULL";
            }
        }

        $query = "ALTER TABLE {$nameTable} {$sql};";
        DB::statement($query);
    }

    /**
     * Get data from one csv file
     *
     * @param $fileCSV
     * @param array $options
     *
     * @param $columns
     * @param $nameTable
     * @param $processedFilePath
     * @return void
     */
    public function getDataFromOneFile($fileCSV, $options, $columns, $nameTable, $processedFilePath)
    {
        $csv = Reader::createFromPath($fileCSV, 'r');
        $columns = implode(",", $columns);

        $stmt = (new Statement());
        $records = $stmt->process($csv);

        foreach ($records as $key => $record) {
            $getDataAfterConvert = $this->getDataAfterProcess($record, $options);

            $dataTmp = [];
            foreach ($getDataAfterConvert as $item) {
                array_push($dataTmp, "\$\${$item}\$\$");
            }

            $stringValue = implode(",", $dataTmp);
            DB::statement("
                INSERT INTO {$nameTable}({$columns}) values ({$stringValue});
            ");
        }

        // move file
        $now = Carbon::now()->format('Ymdhis') . rand(1000, 9999);
        $fileName = "hogehoge_{$now}.csv";
        moveFile($fileCSV, $processedFilePath . '/' . $fileName);
    }

    /**
     * Get data after process
     *
     * @param $dataLine
     * @param array $options
     *
     * @return array
     */
    protected function getDataAfterProcess($dataLine, $options = [])
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
            } elseif ($pattern === 'TODAY()') {
                $data[$col] = Carbon::now()->format('Y/m/d');
            } elseif ($pattern === '0') {
                $data[$col] = '0';
            } else {
                $data[$col] = $this->convertDataFollowSetting($pattern, $dataLine);
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
}
