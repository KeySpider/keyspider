<?php

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Ldaplibs\Import;

use App\Ldaplibs\SettingsManager;
use http\Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
    public const CONVERSION = 'CSV Import Process Format Conversion';
    public const CONFIGURATION = 'CSV Import Process Basic Configuration';

    /**
     * CSVReader constructor.
     * @param SettingsManager $setting
     */
    public function __construct(SettingsManager $setting)
    {
        $this->setting = $setting;
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
     * @param $setting
     * @return mixed
     */
    public function getNameTableBase($setting)
    {
        return $setting[self::CONFIGURATION]['TableNameInDB'];
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
            if ($key !== '' && preg_match($pattern, $key) !== 1) {
                $newstring = substr($key, -3);
                $fields[] = "{$newstring}";
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
        foreach ($columns as $key => $col) {
            if (!Schema::hasColumn($nameTable, $col)) {
                Schema::table($nameTable, function (Blueprint $table) use ($col) {
                    $table->string($col)->nullable();
                });
            }
        }
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
        try {
            DB::beginTransaction();

            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($nameTable);
            $primaryKey = $settingManagement->getTableKey();

            // get data from csv file
            $csv = Reader::createFromPath($fileCSV);
            $stmt = new Statement();
            $records = $stmt->process($csv);

            foreach ($records as $key => $record) {
                $getDataAfterConvert = $this->getDataAfterProcess($record, $options);
//                $getDataAfterConvert[$colUpdateFlag] = json_encode(config('const.updated_flag_default'));

                if (!empty($getEncryptedFields)) {
                    foreach ($getDataAfterConvert as $cl => $item) {
                        $tableColumn = $nameTable.'.'.$cl;

                        if (in_array($tableColumn, $getEncryptedFields)) {
                            $getDataAfterConvert[$cl] = $settingManagement->passwordEncrypt($item);
                        }
                    }
                }
                $primaryKeyValue = array_get($getDataAfterConvert, $primaryKey, null);
                if ($primaryKeyValue)
                    $data = DB::table($nameTable)->where("{$primaryKey}", $primaryKeyValue)->first();
                else {
                    throw (new \Exception("Not found the key $primaryKey in MasterDBConf.ini"));
                }
                if ($data) {
                    DB::table($nameTable)->where($primaryKey, $getDataAfterConvert[$primaryKey])
                        ->update($getDataAfterConvert);
                } else {
                    DB::table($nameTable)->insert($getDataAfterConvert);
                }
            }

            // move file
            $now = Carbon::now()->format('Ymdhis') . random_int(1000, 9999);
            $fileName = "hogehoge_{$now}.csv";
            moveFile($fileCSV, $processedFilePath . '/' . $fileName);

            $deleteColumn = $settingManagement->getDeleteFlagColumnName($nameTable);
            DB::table($nameTable)->whereNull($deleteColumn)->update(["{$deleteColumn}"=>'0']);
            DB::commit();
        } catch (\Exception $e) {
            Log::error($e);
        }
    }

    /**
     * Get data after process
     *
     * @param $dataLine
     * @param array $options
     *
     * @return array
     */
    protected function getDataAfterProcess($dataLine, $options = []): array
    {
        $fields = [];
        $data = [];
        $conversions = $options['CONVERSATION'];

        foreach ($conversions as $key => $item) {
            if ($key === '' || preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $key) === 1) {
                unset($conversions[$key]);
            }

            if (isset($conversions[$key])) {
                $explodeKey = explode('.', $key);
                $key = $explodeKey[1];
                $fields[$key] = $item;
            }
        }

        foreach ($fields as $col => $pattern) {
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
                if ($regx === '') {
                    return $item;
                }

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

    /**
     * Process group from pattern
     *
     * @param string $str
     * @param array $group
     *
     * @return string
     */
    protected function processGroup($str, $group): string
    {
        if ($group === '$1') {
            return $str[1];
        }

        if ($group === '$2') {
            return $str[2];
        }

        if ($group === '$3') {
            return $str[3];
        }

        if ($group === '$1/$2/$3') {
            return "{$str[1]}/{$str[2]}/{$str[3]}";
        }

        return '';
    }
}
