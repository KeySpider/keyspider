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

namespace App\Ldaplibs;

use App\Commons\Consts;
use App\Commons\Creator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SettingsManager
{
    private const GENERAL_SETTINGS_INI_PATH = 'ini_configs/GeneralSettings.ini';
    private const ENCRYPT_STANDARD_METHOD = 'aes-256-cbc';

    public $iniMasterDBFile;
    public $masterDBConfigData;
    public $generalKeys;
    public $keySpider;
    private $roleFlags;
    private $pathIniConfigs;

    public function __construct()
    {
        $this->pathIniConfigs = config('const.PATH_INI_CONFIGS') . config('const.INI_CONFIGS');

        if (!$this->validateKeySpider()) {
            $this->keySpider = null;
        } elseif ($this->isGeneralSettingsFileValid()) {
            $this->iniMasterDBFile = $this->keySpider[Consts::MASTER_DB_CONFIGURATION][Consts::MASTER_DB_CONFIG];
            $this->masterDBConfigData = parse_ini_file($this->iniMasterDBFile, true);
        }
    }

    /**
     * keyspider.ini の設定を全て取得します。
     */
    public function getAllConfigsFromKeyspiderIni(Bool $bool = true)
    {
        // 引数として false を渡すとセクション名無しの配列を返します。
        $keyspider = parse_ini_file($this->pathIniConfigs . Consts::FILENAME_KEYSPIDER_INI, $bool);
        return $keyspider;
    }

    /**
     * keyspider.ini の import_config に設定されているファイルパスを全て取得します。
     */
    public function getImportConfigsFromKeyspiderIni()
    {
        $keyspider = parse_ini_file($this->pathIniConfigs . Consts::FILENAME_KEYSPIDER_INI);
        return $keyspider[Consts::IMPORT_CONFIG];
    }

    /**
     * keyspider.ini の extract_config に設定されているファイルパスを全て取得します。
     */
    public function getExtractConfigsFromKeyspiderIni()
    {
        $keyspider = parse_ini_file($this->pathIniConfigs . Consts::FILENAME_KEYSPIDER_INI);
        return $keyspider[Consts::EXTRACT_CONFIG];
    }

    public function setRoleFlags($flags)
    {
        $this->roleFlags = $flags;
    }

    public function getRoleFlags()
    {
        $allRoleFlags = [];
        $roleMapCount = isset($this->masterDBConfigData['RoleMap']['RoleID']) ?
            count($this->masterDBConfigData['RoleMap']['RoleID']) : 0;
        $roleBasicName = $this->getBasicRoleFlagColumnName();
        for ($i = 0; $i < $roleMapCount; $i++) {
            $allRoleFlags[] = "$roleBasicName-$i";
        }
        return $allRoleFlags;
    }
    /**
     * @return bool|null
     */
    public function validateKeySpider(): ?bool
    {
        try {
            $this->keySpider = $this->getAllConfigsFromKeyspiderIni();

            $validate = Validator::make($this->keySpider, [
                'Master DB Configuration' => 'required',
            ]);
            if ($validate->fails()) {
                Log::error('Key spider INI is not correct!');
                throw new RuntimeException($validate->getMessageBag());
            }
        } catch (Exception $exception) {
            Log::error('Error on file KeySpider.ini');
            Log::error($exception->getMessage());
        }

        try {
            $filesPath = $this->getAllConfigsFromKeyspiderIni(false);

            $allFilesInKeySpider = array_merge(
                $filesPath[Consts::IMPORT_CONFIG],
                $filesPath[Consts::EXTRACT_CONFIG],
                [$filesPath[Consts::MASTER_DB_CONFIG]]
            );

            array_walk(
                $allFilesInKeySpider,
                function ($filePath) {
                    if (!file_exists($filePath)) {
                        throw new RuntimeException("[KeySpider validation error] The file: <$filePath> is not existed!");
                    }
                }
            );
        } catch (Exception $exception) {
            Log::error('Error on file KeySpider.ini');
            Log::error($exception->getMessage());
        }
        return true;
    }

    private function isGeneralSettingsFileValid(): bool
    {
        try {
            $this->generalKeys = parse_ini_file(storage_path(self::GENERAL_SETTINGS_INI_PATH), true);
            $validate = Validator::make($this->generalKeys, [
                'KeySettings' => 'required',
                'KeySettings.Azure_key' => 'required',
                'KeySettings.Encryption_key' => 'required',
                'KeySettings.Encrypted_fields' => 'required',
            ]);
            if ($validate->fails()) {
                throw new Exception($validate->getMessageBag());
            }
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
        }
        return true;
    }

    /** @noinspection MultipleReturnStatementsInspection */

    public function isFolderExisted($folderPath): bool
    {
        return is_dir($folderPath);
    }

    /**
     * @return Bearer token
     */
    public function getAzureADAPItoken()
    {
        return $this->generalKeys['KeySettings']['Azure_key'];
    }

    public function getOfficeLicenseFields()
    {
        $officeLicenseFields = $this->generalKeys['KeySettings']['Office_license_field'];
        return $officeLicenseFields;
    }

    /**
     * @return Array of fields encrypted
     */
    public function getEncryptedFields()
    {
        $result = [];
        $encryptedFields = $this->generalKeys['KeySettings']['Encrypted_fields'];

        return array_values($encryptedFields);

        // Compare for each encrypt key
        foreach ($encryptedFields as $encryptedField) {

            // Master DB Config has Tags, so traverse each tag
            foreach ($this->masterDBConfigData as $masterWithTag) {
                $filterdArray = array_filter(
                    $masterWithTag,
                    function ($k) use ($encryptedField) {
                        return $k === $encryptedField;
                    },
                    ARRAY_FILTER_USE_BOTH
                );
                if ($filterdArray) {
                    $result = array_merge($result, $filterdArray);
                }
            }
        }
        return array_values($result);
    }

    /**
     *
     * @param $data
     * @param $key
     * @return string encrypted already
     */
    public function passwordEncrypt($data, $key = null)
    {
        $encryptionKey = $key ? $key : $this->generalKeys['KeySettings']['Encryption_key'];

        // Generate an initialization vector
        $initializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPT_STANDARD_METHOD));

        // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
        $encrypted = openssl_encrypt(
            $data,
            self::ENCRYPT_STANDARD_METHOD,
            $encryptionKey,
            0,
            $initializationVector
        );

        // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
        return base64_encode($encrypted . '::' . $initializationVector);
    }

    /**
     * @param $data
     * * @param $key
     * @return string decrypted
     */
    public function passwordDecrypt($data, $key = null)
    {
        if (empty($data)) return;

        try {
            $encryptionKey = $key ? $key : $this->generalKeys['KeySettings']['Encryption_key'];
            // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
            list($encryptedData, $initializationVector) = explode('::', base64_decode($data), 2);
            return openssl_decrypt(
                $encryptedData,
                self::ENCRYPT_STANDARD_METHOD,
                $encryptionKey,
                0,
                $initializationVector
            );
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * @param $dataType : 'scim' or 'csv'
     * @param $keyString : string to search
     * @param $tableQuery : table to search
     * @return Array of key values of [Update Flags]
     */
    public function getUpdateFlags($dataType, $keyString, $tableQuery)
    {
        $results = $this->getUpdateFlagsField($keyString, $tableQuery);
        return isset($results[1][$dataType]['isUpdated']) ? $results[1][$dataType]['isUpdated'] : null;
    }

    /**
     * @param $keyString
     * @param $tableQuery
     * @return array
     */
    private function getUpdateFlagsField($keyString, $tableQuery)
    {
        $updateFlags = $this->getFlags()['updateFlags'];
        foreach ($updateFlags as $updateFlag) {
            $tableAndColumn = explode('.', $updateFlag);
            $tableName = $tableAndColumn[0];
            if ($tableName == $tableQuery) {
                $columnName = $tableAndColumn[1];
                $tableKey = $this->getTableKey();
                $flags = DB::table($tableName)->select($columnName)
                    ->where($tableKey, $keyString)
                    ->first();
                $results = (array)$flags;
                try {
                    return [$columnName, json_decode($results[$columnName], true)];
                } catch (Exception $exception) {
                    // TODO: $result is array, so this is a bug
                    Log::error("Data [$results] is not correct!");
                    Log::error($exception->getMessage());
                    return null;
                }
            }
        }
    }

    public function getFlags()
    {
        $deleteFlags = [];
        $updateFlags = [];
        foreach ($this->masterDBConfigData as $table) {
            $deleteFlags = array_merge($deleteFlags, array_filter(
                $table,
                function ($k) {
                    return strpos($k, '.DeleteFlag') !== false;
                },
                ARRAY_FILTER_USE_KEY
            ));

            $updateFlags = array_merge($updateFlags, array_filter(
                $table,
                function ($k) {
                    return strpos($k, '.UpdateFlags') !== false;
                },
                ARRAY_FILTER_USE_KEY
            ));
        }
        return [
            'deleteFlags' => array_values($deleteFlags),
            'updateFlags' => array_values($updateFlags)
        ];
    }

    public function getTableKey()
    {
        return 'ID';
    }

    /**
     * @param $dataType : 'scim' or 'csv'
     * @param $keyString : data to search
     * @param $tableQuery : table to search
     * @param $value : 0 or 1
     * @return int
     */
    public function setUpdateFlags($dataType, $keyString, $tableQuery, $value)
    {
        $results = $this->getUpdateFlagsField($keyString, $tableQuery);
        $columnName = $results[0];
        $updateFlagsValue = $results[1];
        $updateFlagsValue[$dataType] = $value;
        $tableKey = $this->getTableKey();

        return DB::table($tableQuery)->where($tableKey, $keyString)
            ->update([$columnName => json_encode($updateFlagsValue)]);
    }

    public function getFlagsUpdated($table)
    {
        $nameColumnUpdate = null;
        $getFlags = $this->getFlags();
        $updatedFlags = $getFlags['updateFlags'];
        foreach ($updatedFlags as $data) {
            $arrayColumn = explode('.', $data);
            if (in_array($table, $arrayColumn)) {
                $nameColumnUpdate = $arrayColumn[1];
                break;
            }
        }
        return $nameColumnUpdate;
    }

    protected function removeExt($file_name)
    {
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
    }

    protected function contains($needle, $haystack): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    public function getUpdateFlagsColumnName($table)
    {
        $nameColumnUpdate = null;
        $getFlags = $this->getFlags();
        $updatedFlags = $getFlags['updateFlags'];
        foreach ($updatedFlags as $data) {
            $arrayColumn = explode('.', $data);
            if (in_array($table, $arrayColumn)) {
                $nameColumnUpdate = $arrayColumn[1];
                break;
            }
        }
        return $nameColumnUpdate;
    }

    public function getDeleteFlagColumnName($table)
    {
        $column = null;
        $getFlags = $this->getFlags();
        $deleteFlags = $getFlags['deleteFlags'];
        foreach ($deleteFlags as $data) {
            $arrayColumn = explode('.', $data);
            if (in_array($table, $arrayColumn)) {
                $column = $arrayColumn[1];
                break;
            }
        }
        return $column;
    }

    public function getBasicRoleFlagColumnName()
    {
        $fullColummnName = $this->masterDBConfigData['User']['User.RoleFlag'] ?? null;
        if ($fullColummnName) {
            $arrayColumn = explode('.', $fullColummnName);
            return isset($arrayColumn[1]) ? $arrayColumn[1] : null;
        }
        return null;
    }

    /**
     * @param null $tableName
     * @return array|null
     * The MasterDBConf is using Role Name in [RoleMap], so Name is the key
     * But in the database, table User is using RoleId to indicate key of the Role
     * So need to map ID, Name to update table User based on Key.
     */
    public function getRoleMapInName($tableName = null)
    {
        if ($tableName == null) {
            $tableName = 'Role';
        }
        if (isset($this->masterDBConfigData['RoleMap'])) {
            $roleMap = $this->masterDBConfigData['RoleMap']['RoleID'];
            $query = DB::table($tableName)->select('ID', 'Name');
            $allRoleRecords = $query->get()->toArray();
            $arrayIdUserMap = [];

            foreach ($roleMap as $role) {
                $item['Name'] = $role;
                $item['ID'] = null;
                foreach ($allRoleRecords as $record) {
                    $record = (array)$record;
                    if ($record['Name'] == $role) {
                        $item['ID'] = $record['ID'];
                    }
                }
                $arrayIdUserMap[] = $item;
            }
            return $arrayIdUserMap;
        }
        return null;
    }
    public function getRoleMapInExternalID($tableName = null)
    {
        if ($tableName == null) {
            $tableName = 'Role';
        }
        if (isset($this->masterDBConfigData['RoleMap'])) {
            $roleMap = $this->masterDBConfigData['RoleMap']['RoleID'];
            $query = DB::table($tableName)->select('ID', 'externalID');
            $allRoleRecords = $query->get()->toArray();
            $arrayIdUserMap = [];
            foreach ($allRoleRecords as $record) {
                $record = (array)$record;
                $arrayIdUserMap[] = $record['externalID'];
            }
            return $arrayIdUserMap;
        }
        return null;
    }
    public function getRoleMapInExternalSFID($tableName = null)
    {
        if ($tableName == null) {
            $tableName = 'Role';
        }
        if (isset($this->masterDBConfigData['RoleMap'])) {
            $roleMap = $this->masterDBConfigData['RoleMap']['RoleID'];
            $query = DB::table($tableName)->select('ID', 'externalSFID');
            $allRoleRecords = $query->get()->toArray();
            $arrayIdUserMap = [];
            foreach ($allRoleRecords as $record) {
                $record = (array)$record;
                $arrayIdUserMap[] = $record['externalSFID'];
            }
            return $arrayIdUserMap;
        }
        return null;
    }

    /**
     * @param string $groupId
     * @param null $tableName
     * @return the Order of group that have name (from groupId) in the RoleMap in MasterDBconf.ini
     */
    public function getRoleFlagIDColumnNameFromGroupId(string $groupId, $tableName = null)
    {
        if ($tableName == null) {
            $tableName = 'Role';
        }
        if (isset($this->masterDBConfigData['RoleMap'])) {
            $roleMap = $this->masterDBConfigData['RoleMap']['RoleID'];
            // Find the Name of group has groupId
            $query = DB::table($tableName);
            $query->select('Name');
            $query->where('ID', $groupId);
            $result = $query->first();
            if ($result) {
                $groupName = $result->Name;
                foreach ($roleMap as $index => $item) {
                    if ($groupName == $item) {
                        return $index;
                    }
                }
            }
        }
        return null;
    }

    public function getAllExtractionProcessID($tableName = 'User')
    {
        $extractionProcessIDs = [];
        try {
            $filePaths = parse_ini_file($this->pathIniConfigs . 'KeySpider.ini')[Consts::EXTRACT_CONFIG];

            foreach ($filePaths as $filePath) {
                if (is_file($filePath)) {
                    $file = parse_ini_file($filePath);
                    if ($tableName == $file[Consts::EXTRACTION_TABLE] ?? '') {
                        $extractionProcessIDs[] = $file[Consts::EXTRACTION_PROCESS_ID] ?? '';
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
        return $extractionProcessIDs;
    }

    public function makeUpdateFlagsJson($nameTable = 'User')
    {
        $updateFlagsJson = $this->getAllExtractionProcessID($nameTable);
        $updateFlags = [];
        if (!empty($updateFlagsJson)) {
            foreach ($updateFlagsJson as $item) {
                $updateFlags[$item] = config('const.SET_ALL_EXTRACTIONS_IS_TRUE');
            }
        }
        return json_encode($updateFlags);
    }

    public function getRoleFlagInName($roleMaps, $roleFlag, $value)
    {
        if ($value == '0') return;
        $groupName = $roleMaps[$roleFlag]['Name'];
        return $groupName;
    }

    public function resetRoleFlagX($datas)
    {
        $allRoleFlags = $this->getRoleFlags();

        foreach ($allRoleFlags as $roleFlag) {
            $datas[$roleFlag] = '0';
        }
        return $datas;
    }

    public function getRoleFlagX($scimValue, $roleMaps)
    {
        $index = null;
        foreach ($roleMaps as $key => $value) {
            if ($value['Name'] == $scimValue) {
                $index =  $key;
                break;
            }
        }
        return $index;
    }

    public function getTableUser()
    {
        return array_get($this->masterDBConfigData, 'User.User');
    }

    public function getTableRole()
    {
        return array_get($this->masterDBConfigData, 'Role.Role');
    }

    public function traceProcessInfo($backtrace, $cmd, $message)
    {
        // ARGV : debug_backtrace 
        // $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $btFunction = $backtrace[0]['function'];
        $btClass = $backtrace[0]['class'];
        $epClass = explode('\\', $btClass);

        $functionWithclass = $epClass[count($epClass) - 1] . '.' . $btFunction;
        $tl = sprintf("[[ KSC ]] `%s` is %s. %s", $functionWithclass, $cmd, $message);
        Log::info($tl);
    }

    public function isExtractSettingsFileValid(): bool
    {
        $pathIniConfigs = config('const.PATH_INI_CONFIGS') . config('const.INI_CONFIGS');
        $inifilePaths = parse_ini_file($pathIniConfigs . 'KeySpider.ini')[Consts::EXTRACT_CONFIG];

        try {
            $epbc = Consts::EXTRACTION_PROCESS_BASIC_CONFIGURATION;
            foreach ($inifilePaths as $key => $inifilePath) {
                $inifile = parse_ini_file($inifilePath, true);
                $validate = Validator::make($inifile, [
                    $epbc => 'required',
                    $epbc . '.ExtractionProcessID' => 'required',
                    $epbc . '.OutputType' => 'required',
                    $epbc . '.ExtractionTable' => 'required',
                ]);

                if ($validate->fails()) {
                    throw new Exception('[[ KSC ]] There is an error in the configuration file.'
                        . "\n" . $inifilePath . "\n" . $validate->getMessageBag() . "\n");
                    return false;
                }
            }
            return true;
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return false;
        }
    }

    public function makeScimInfo($provisoning, $scimMethod, $table, $itemId, $itemName, $message) {
        return array(
            'provisoning' => $provisoning,
            'scimMethod' => $scimMethod,
            'table' => $table,
            'itemId' => $itemId,
            'itemName' => $itemName,
            'message' => $message,
        );
    }

    public function faildLogger($scimInfo)
    {
        // provisoning	table	id	name	scimMethod	message
        $logStr = sprintf(
            "%s\t%s\t%s\t%s\t%s\t%s",
            $scimInfo['provisoning'],
            $scimInfo['table'],
            $scimInfo['itemId'],
            $scimInfo['itemName'],
            $scimInfo['scimMethod'],
            $scimInfo['message']
        );

        Log::channel('faild')->error($logStr);
    }

    public function validationLogger($scimInfo)
    {
        // provisoning	table	id	name	scimMethod	message
        $logStr = sprintf(
            "%s\t%s\t%s\t%s\t%s\t%s",
            $scimInfo['provisoning'],
            $scimInfo['table'],
            $scimInfo['itemId'],
            $scimInfo['itemName'],
            $scimInfo['scimMethod'],
            $scimInfo['message']
        );

        Log::channel('validation')->warning($logStr);
    }

    public function detailLogger($scimInfo)
    {
        // provisoning	table	id	name	scimMethod	message
        $logStr = sprintf(
            "%s\t%s\t%s\t%s\t%s\t%s",
            $scimInfo['provisoning'],
            $scimInfo['table'],
            $scimInfo['itemId'],
            $scimInfo['itemName'],
            $scimInfo['scimMethod'],
            $scimInfo['message']
        );
        Log::channel('detail')->info($logStr);
    }

    public function summaryLogger($scimInfo)
    {
        $casesProcessed = (int)$scimInfo['createCount']
            + (int)$scimInfo['updateCount']
            + (int)$scimInfo['deleteCount'];

        // provisoning	table	cases_handle	create	update	delete	cases_processed
        $logStr = sprintf(
            "%s\t%s\t%d\t%d\t%d\t%d\t%d",
            $scimInfo['provisoning'],
            $scimInfo['table'],
            $scimInfo['casesHandle'],
            $scimInfo['createCount'],
            $scimInfo['updateCount'],
            $scimInfo['deleteCount'],
            $casesProcessed
        );

        if ($casesProcessed != $scimInfo['casesHandle']) {
            Log::channel('summary')->notice($logStr);
        } else {
            Log::channel('summary')->info($logStr);
        }
    }

    public function validateRequestData($nameTable, $request)
    {
        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Validation Keys"];
        $url = $scimOptions["url"] . "/api/Validations";

        $guzzle = new \GuzzleHttp\Client();
        $result = $guzzle->get($url, [
            "query" => [
                "nameTable" => $nameTable,
                "body" => $request,
            ],
        ])->getBody()->getContents();
        return $result;
    }


    /**
     * create summary report record.
     * 
     * @param $operation     : 'IN' or 'OUT'
     * @param $target        : service name
     * @param $table         : table name
     * @param $total         : total data count
     * @param $create        : create data count
     * @param $update        : update data count
     * @param $delete        : delete data count
     * @param $error         : error data count
     * @param $startDatetime : process start datetime
     */
    public function summaryReport($operation, $target, $table,
        $total, $create, $update, $delete, $error, $startDatetime
    ) {
        $tableName = "SummaryReport";
        $id = (new Creator())->makeIdBasedOnMicrotime($tableName);
        $report = array(
            "ID"        => $id,
            "Operation" => $operation,
            "Target"    => $target,
            "Table"     => $table,
            "Total"     => $total,
            "Create"    => $create,
            "Update"    => $update,
            "Delete"    => $delete,
            "Error"     => $error,
            "StartDatetime" => $startDatetime,
            "EndDatetime" => date(Consts::DATE_FORMAT_YMDHIS),
        );
        DB::beginTransaction();
        DB::table($tableName)->insert($report);
        DB::commit();
    }
}
