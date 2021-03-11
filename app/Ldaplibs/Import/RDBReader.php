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

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use Flow\JSONPath\JSONPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class RDBReader
{
    /**
     * define const
     */
    public const RDB_IMPORT_DATABASE_CONFIGRATION = 'RDB Import Database Configuration';
    public const RDB_INPUT_BASIC_CONFIGURATION = 'RDB Input Basic Configuration';
    public const CONVERSION = 'RDB Input Format Conversion';

    protected $prefix;
    protected $rdbRecords;

    public function __construct()
    {
        $this->prefix = null;
        $this->rdbRecords = [];
    }

    /**
     * Import From RDB Data
     * 
     */
    public function importFromRDBData($dataPost, $setting)
    {
        $this->prefix = $dataPost[self::RDB_INPUT_BASIC_CONFIGURATION]['Prefix'];
        var_dump($this->prefix . ' is processing now');

        // get real config
        $setting = parse_ini_file(storage_path('ini_configs/import/'.$this->prefix.'InfoRDBImport.ini'), true);

        try {
            // Get data from Oracle.
            $conn = $setting[self::RDB_IMPORT_DATABASE_CONFIGRATION]['Connection'];
            $table = $setting[self::RDB_IMPORT_DATABASE_CONFIGRATION]['ImportTable'];
            $pkColumn = $setting[self::RDB_IMPORT_DATABASE_CONFIGRATION]['PrimaryColmn'];
            $conversions = $setting[self::CONVERSION];

            // Initialize
            $this->rdbRecords = [];
            DB::connection($conn)->table($table)->orderBy($pkColumn, 'ASC')
                ->chunk(1000, function ($bulkDatas) use ($conversions, $pkColumn) {
                foreach ($bulkDatas as $bulkData) {
                    $array = json_decode(json_encode($bulkData), true);
                    $this->rdbRecords[] = $this->createWorkFromRdb($pkColumn, $conversions, $array);
                }
            });

            // Insert record to DiffNew
            $this->insertTodayData('New', $setting, $this->rdbRecords);

            // CRUD process
            $this->CRUDRecord($setting);

            // Insert record to DiffOld
            $this->insertTodayData('Old', $setting, $this->rdbRecords);

            return true;
        } catch (\Exception $e) {
            echo("Import from RDB failed: $e\n");
            Log::error($e->getMessage());
            return false;
        }
    }

     /**
     * Create work from RDB
     * TODO : Set the required columns
     * 
     */
    private function createWorkFromRdb($pkColumn, $conversions, $array)
    {
        $fields = [];

        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $regExpManagement = new RegExpsManager();

        foreach ($conversions as $key => $item) {
            $explodeKey = explode('.', $key);
            $key = $explodeKey[1];

            if ($item === 'admin') {
                $fields[$key] = 'admin';
            } elseif (0 === strpos($item, 'nameJoin')) {
                $fields[$key] = $this->nameJoin($item, $array);
            } elseif ($item === 'TODAY()') {
                $fields[$key] = Carbon::now()->format('Y/m/d');
            } elseif ($item === '0') {
                $fields[$key] = '0';
            } else {

                $itemValue = $item;

                $slow = strtolower($item);
                if (array_key_exists($slow, $array)) {
                    $itemValue = $array[$slow];
                }

                // EncryptedFields?
                $realColumnName = $this->prefix . '.' . $key;
                if (in_array($realColumnName, $getEncryptedFields)) {
                    $itemValue = $settingManagement->passwordEncrypt($itemValue);
                }

                // process with regular expressions?
                $columnName = $regExpManagement->checkRegExpRecord($itemValue);
                if (isset($columnName)) {
                    $slow = strtolower($columnName);
                    if (array_key_exists($slow, $array)) {
                        $recValue = $array[$slow];
                        $fields[$key] = $regExpManagement->convertDataFollowSetting($itemValue, $recValue);
                    }
                } else {
                    if (0 === strpos($item, '(')) {
                        preg_match('/\((.*)\)/', $item, $matches);
                        $slow = strtolower($matches[1]);
                        $fields[$key] = $array[$slow];
                    } else {
                    $fields[$key] = $itemValue;
                }
            }
        }
        }
        return $fields;
    }

     /**
     * concat sirName + lastName
     * 
     */
    private function nameJoin($item, $data){
        $pattern = '/nameJoin\((.*)\)/';
        preg_match($pattern, $item, $matches);

        $explodeColumns = explode('.', strtolower($matches[1]));
        $name = null;
        foreach ($explodeColumns as $colmun) {
            if (!empty($name)) {
                if (!empty($data[$colmun])) {
                    $name = $name . ' ';
                }
            }
            $name = $name . $data[$colmun];
        }
        return $name;
    }

    /**
     * Import RDB record(s) to kispider
     * 
     */
    private function insertTodayData($mode, $setting, $records){
        $table = $setting[self::RDB_INPUT_BASIC_CONFIGURATION]['OutputTable'];
        try {
            DB::beginTransaction();
            DB::statement('ALTER TABLE "'.$table.$mode.'" ALTER COLUMN "jsonColumn" TYPE text;');
            DB::table($table.$mode)->truncate();
            foreach ($records as $record) {
                ksort($record);
                $data = [
                    'ID' => $record['ID'],
                    'jsonColumn' => json_encode($record, JSON_UNESCAPED_UNICODE),
                ];
                DB::table($table.$mode)->insert($data);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
        }
    }

    private function CRUDRecord($setting) {
        $table = $setting[self::RDB_INPUT_BASIC_CONFIGURATION]['OutputTable'];

        // DB::enableQueryLog();
        // var_dump(DB::getQueryLog());

        $inserts = DB::table($table."New")->select($table."New.ID")
            ->leftJoin($table."Old", $table."New.ID", '=', $table."Old.ID")
            ->whereNull($table."Old.ID")->get();
            // Intentionally indented
            if (count($inserts) != 0) {
                $this->doCudOperation('ins', $setting, $inserts);
                echo "debug ==== insert (".count($inserts).")\n";
                Log::info("== ".$this->prefix." import insert record(s) = ".count($inserts));
            }

        $deletes = DB::table($table."New")->select($table."Old.ID")
            ->rightJoin($table."Old", $table."New.ID", '=', $table."Old.ID")
            ->whereNull($table."New.ID")->get();
            // Intentionally indented
            if (count($deletes) != 0) {
                $this->doCudOperation('del', $setting, $deletes);
                echo "debug ==== delete (".count($deletes).")\n";
                Log::info("== ".$this->prefix." import delete record(s) = ".count($deletes));
            }
    
        $updates = DB::table($table."New")->select($table."New.ID")
            ->join($table."Old", $table."New.ID", '=', $table."Old.ID")
            ->whereColumn($table.'New.jsonColumn', '<>', $table.'Old.jsonColumn')->get();
            // Intentionally indented
            if (count($updates) != 0) {
                $this->doCudOperation('upd', $setting, $updates);
                echo "debug ==== update (".count($updates).")\n";
                Log::info("== ".$this->prefix." import update record(s) = ".count($updates));
            }
    }

    /**
     * Create and update and delete process
     * 
     */
    private function doCudOperation($mode, $setting, $sarray)
    {
        $diffTable = $setting[self::RDB_INPUT_BASIC_CONFIGURATION]['OutputTable'];
        $itemTable = $setting[self::RDB_INPUT_BASIC_CONFIGURATION]['Prefix'];

        // Sanitize ID array
        $varray = json_decode(json_encode($sarray), true);
        $validNumber = array_column($varray, 'ID');

        try {
            // Master table CUD -CU process
            if ($mode == 'del') {
                foreach ($validNumber as $id) {
                    DB::beginTransaction();
                    //DB::enableQueryLog();
                    DB::table($itemTable)->where("ID", $id)
                        ->update(['DeleteFlag' => '1']);
                    //var_dump(DB::getQueryLog());

                    // Add 'LDAP status' to UpdateFlags
                    $this->setUpdateFlags($id, $itemTable);
                    DB::commit();
                }
                return;
            }

            // Master table CUD -D process
            foreach ($this->rdbRecords as $rdbRecord) {
                // Target of processing 
                if (in_array($rdbRecord['ID'], $validNumber)) {
                    DB::beginTransaction();

                    $data = DB::table($itemTable)->where("ID", $rdbRecord['ID'])->first();
                    if ($data) {
                        DB::table($itemTable)->where("ID", $rdbRecord['ID'])->update($rdbRecord);
                    } else {
                        DB::table($itemTable)->insert($rdbRecord);
                    }

                    // Add 'LDAP status' to UpdateFlags
                    $this->setUpdateFlags($rdbRecord['ID'], $itemTable);
                    DB::commit();
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
        }
    }

    /**
     * Update flag reset process
     * 
     */
    private function setUpdateFlags($id, $itemTable)
    {
        $settingManagement = new SettingsManager();
        $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($itemTable);
        $setValues[$colUpdateFlag] = $settingManagement->makeUpdateFlagsJson($itemTable);

        DB::table($itemTable)->where("ID", $id)
            ->update($setValues);
    }
}
