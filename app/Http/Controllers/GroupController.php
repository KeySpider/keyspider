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

namespace App\Http\Controllers;

use App\Exceptions\SCIMException;
use App\Http\Models\AAA;
use App\Http\Models\CCC;
use App\Jobs\DBImporterFromScimJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\Import\SCIMReader;
use App\Ldaplibs\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Optimus\Bruno\EloquentBuilderTrait;
use Optimus\Bruno\LaravelController;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Parser;

/**
 * @property SettingsManager settingManagement
 */
class GroupController extends LaravelController
{
    use EloquentBuilderTrait;

    protected $masterDB;
    protected $path;

    public function __construct()
    {

        $this->path = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');
        $this->settingManagement = new SettingsManager();
        $this->importSetting = new ImportSettingsManager();
        $SCIMImportSettingFiles = $this->importSetting->keySpider['SCIM Input Process Configration']['import_config']??[];
        foreach ($SCIMImportSettingFiles as $file){
            $fileContent = parse_ini_file($file);
            if($fileContent['ImportTable']=='Role'){
                $this->path = $file;
                break;
            }
        }

        $this->masterDB = $this->importSetting->getTableRole();

    }

    /**
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $scimQuery = $request->input('filter', null);

        $columnDeleted = $this->settingManagement->getDeleteFlagColumnName($this->masterDB);

        $sqlQuery = DB::table($this->importSetting->getTableRole());
//        $sqlQuery->where($columnDeleted, '!=', '1');

        if ($request->has('filter')) {
            if ($scimQuery) {
                $parser = new Parser(Mode::FILTER());
                $node = $parser->parse($scimQuery);
                $filterValue = $node->compareValue;
            } else {
                $filterValue = null;
            }
            $sqlQuery->where('ID', $filterValue);
//            $where['003'] = $filterValue;
        }

        $dataConvert = [];


        $dataQuery = $sqlQuery->get();
        $sqlString = $sqlQuery->toSql();

        if (!empty($dataQuery->toArray())) {
            $importSetting = new ImportSettingsManager();

            foreach ($dataQuery as $data) {
                $dataFormat = $importSetting->formatDBToSCIMStandard((array)$data, $this->path);
                unset($dataFormat[0]);
                unset($dataFormat[""]);

                array_push($dataConvert, $dataFormat);
            }
        }


        $jsonData = [];

        if (!empty($dataConvert)) {
            foreach ($dataConvert as $data) {
                $members = $this->getAllMembersBelongedToGroupId($data['externalId']);
                $dataTmp = [
                    "id" => $data['externalId'],
                    "externalId" => $data['externalId'],
                    "displayName" => $data['displayName'],
                    "meta" => [
                        "resourceType" => "Group",
                    ],
                    "members" => $members,
                ];

                array_push($jsonData, $dataTmp);
            }
        }

        return $this->response($this->toSCIMArray($jsonData), $code = 200);
    }

    /**
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function store(Request $request)
    {
        $dataPost = $request->all();
        $importSetting = new ImportSettingsManager();
        $setting = $importSetting->getSCIMImportSettings($this->path);

        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost, $setting));

        $dataResponse = $dataPost;
        $dataResponse['id'] = $dataPost['externalId'];
        $dataResponse['meta']['location'] = $request->fullUrl() . '/' . $dataResponse['id'];
        return $this->response($dataResponse, 201);
    }

    public function destroy($id, Request $request)
    {
        // do something
        // Log::info('-----------------DELETE USER...-----------------');
        // Log::debug($id);
        // Log::info('--------------------------------------------------');

        $this->checkToResponseErrorGroupNotFound($id);

        $this->logicalDeleteGroup($id);

        $jsonResponse = [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:Success"
            ],
            "detail" => "Delete Group success",
            "id" => $id,
            "meta" => [
                "resourceType" => "Group"
            ],
            "status" => 200
        ];

        return $this->response($jsonResponse);
    }

    private function checkToResponseErrorGroupNotFound($id)
    {
        $keyTable = $this->importSetting->getTableKey();

        $sqlQuery = DB::table($this->importSetting->getTableRole());
        $where = [
            "{$keyTable}" => $id,
        ];

        if (is_exits_columns($this->masterDB, $where)) {
            $user = $sqlQuery->where($where)->first();
        } else {
            $user = null;
        }

        if (!$user) {
            throw (new SCIMException('Group Not Found'))->setCode(404);
        }
    }

    /**
     * logicalDeleteUser
     *
     * @param $id
     * @return Bool
     * @throws SCIMException
     */
    private function logicalDeleteGroup($id) {
        $deleteFlagColumnName = $this->importSetting->getDeleteFlagColumnName($this->masterDB);
        $updateFlagsColumnName = $this->importSetting->getUpdateFlagsColumnName($this->masterDB);

        $setValues = [];
        $setValues[$deleteFlagColumnName] = config('const.SET_ALL_EXTRACTIONS_IS_TRUE');
        $setValues[$updateFlagsColumnName] = $this->importSetting->makeUpdateFlagsJson();

        $keyTable = $this->importSetting->getTableKey();
        $query = DB::table($this->importSetting->getTableRole());
        $data = $query->where($keyTable, $id)->first();

        if ($data) {
            DB::table($this->importSetting->getTableRole())
                ->where($keyTable, $id)->update($setValues);
        } else {
            throw (new SCIMException('Group Not Found'))->setCode(404);
            return false;
        }
        return true;
    }

    /**
     * Update
     *
     * @param $id
     * @param Request $request
     * @throws SCIMException
     */
    public function update($id, Request $request)
    {
        $columnDeleted = $this->settingManagement->getDeleteFlagColumnName($this->masterDB);
        $input = $request->input();

        $input = $this->checkToResponseErrorCase($id, $input);

        foreach ($input['Operations'] as $operation) {

            $opTask = strtolower(array_get($operation, 'op', null));
            $scimReader = new SCIMReader();
            $options = [
                "path" => $this->path,
                'operation' => $operation,
            ];

            if ($opTask === 'replace') {
//                $result = $scimReader->updateReplaceSCIM($id, $options);
                $setting = $this->importSetting->getSCIMImportSettings($this->path);
                $result = $scimReader->updateRsource($id, $input, $setting);
                if ($result) return $this->response(
                    [$input['schemas'],
                        "meta" => [
                            "resourceType" => "Group"
                        ],
                        'detail' => 'Update Group success'
                    ],
                    $code = 200);
            } elseif ($opTask === 'add') {
                Log::info('Add member');
                $result = $scimReader->updateMembersOfGroup($id, $input);
                $response = $input;
                $response['id'] = $id;
                $response['add members successfully'] = (bool)$result;
                return $this->response($response, $code = 200);
            } elseif ($opTask === 'remove') {
                Log::info('Remove member');
                $result = $scimReader->updateMembersOfGroup($id, $input);
                $response = $input;
                $response['id'] = $id;
                $response['remove members successfully'] = (bool)$result;
                return $this->response($response, $code = 200);
            }
        }

//        throw (new SCIMException('Update success'))->setCode(200);
    }

    public function detail($id, Request $request)
    {
        // do something
        // Log::info('-----------------DETAIL GROUP...-----------------');
        // Log::debug($id);
        // Log::info('--------------------------------------------------');

        $columnDeleted = $this->settingManagement->getDeleteFlagColumnName($this->masterDB);
        $keyTable = $this->settingManagement->getTableKey();

        $where = [
            "{$keyTable}" => $id,
//            "{$columnDeleted}" => '0'
        ];
        $sqlQuery = DB::table($this->importSetting->getTableRole());

        if (is_exits_columns($this->masterDB, $where)) {
            $dataQuery = $sqlQuery->where($where)->first();
        }

        $dataFormat = [];
        if ($dataQuery) {
            $importSetting = new ImportSettingsManager();
            $dataFormat = $importSetting->formatDBToSCIMStandard((array)$dataQuery, $this->path);
            unset($dataFormat[0]);
            unset($dataFormat[""]);
        } else {
            return $this->response(["Group not found: $id"], $code = 404);
        }

        $jsonData = [];
        if (!empty($dataFormat)) {
            $members = $this->getAllMembersBelongedToGroupId($id);
            $jsonData = [
                "schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"],
                "id" => $dataFormat['externalId'],
                "externalId" => $dataFormat['externalId'],
                "displayName" => $dataFormat['displayName'],
                "meta" => [
                    "resourceType" => "Group",
                    "location" => $request->fullUrl()
                ],
                "members" => $members,
            ];
        }

        return $this->response($jsonData, $code = 200);
    }

    /**
     * @param $dataArray
     * @return array
     */
    private function toSCIMArray($dataArray)
    {
        $arr = [
            'totalResults' => count($dataArray),
            "itemsPerPage" => 10,
            "startIndex" => 1,
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:ListResponse"
            ],
            'Resources' => $dataArray,
        ];
        return $arr;
    }

    /**
     * @param $id
     * @return array
     * Step:
     * Get Group Name from Group Id.
     * Get Column name of RoleFlag in User table from group Name.
     * Query all User has RoleFlag Column name equal '1'
     */
    private function getAllMembersBelongedToGroupId($id): array
    {
        $members = [];
        $groupTable = $this->settingManagement->getTableUser();
        $roleColumnIndex = $this->settingManagement->getRoleFlagIDColumnNameFromGroupId($id);
        $tableKey = $this->importSetting->getTableKey();
        if ($roleColumnIndex!==null) {
            $roleFlagColumnName = 'RoleFlag-' . (string)$roleColumnIndex;
            //Find all User has RoleFlag Column name equal '1'
            $query = DB::table($groupTable);
            $query->where($roleFlagColumnName, '1');
//            $query->where($tableKey, $id);
            $membersInDB = $query->get()->toArray();
//            Set response for each member
            foreach ($membersInDB as $member) {
                $members[] = ["display" => $member->ID, "value" => $member->ID];
            }

        }

        return $members;
    }

    /**
     * @param $id
     * @param $input
     * @return mixed
     * @throws SCIMException
     */
    private function checkToResponseErrorCase($id, $input)
    {
        $keyTable = $this->settingManagement->getTableKey();
        $sqlQuery = DB::table($this->importSetting->getTableRole());
        $where = [
            "{$keyTable}" => $id,
//            "{$columnDeleted}" => '0'
        ];

        if (is_exits_columns($this->masterDB, $where)) {
            $group = $sqlQuery->where($where)->first();
        } else {
            $group = null;
        }

        if (!$group) {
            throw (new SCIMException('Group Not Found'))->setCode(404);
        }

        if ($input['schemas'] !== ["urn:ietf:params:scim:api:messages:2.0:PatchOp"]) {
            throw (new SCIMException(sprintf(
                'Invalid schema "%s". MUST be "urn:ietf:params:scim:api:messages:2.0:PatchOp"',
                json_encode($input['schemas'])
            )))->setCode(404);
        }

        if (isset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'])) {
            $input['Operations'] = $input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'];
            unset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations']);
        }
        return $input;
    }
}
