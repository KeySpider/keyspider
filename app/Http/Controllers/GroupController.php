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
use App\Http\Models\CCC;
use App\Jobs\DBImporterFromScimJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use App\Ldaplibs\Import\SCIMReader;
use App\Ldaplibs\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Optimus\Bruno\EloquentBuilderTrait;
use Optimus\Bruno\LaravelController;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Parser;

class GroupController extends LaravelController
{
    use EloquentBuilderTrait;

    protected $roleModel;
    protected $masterDB;
    protected $path;

    public function __construct(CCC $roleModel)
    {
        $this->roleModel = $roleModel;
        $this->masterDB = 'CCC';
        $this->path = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');
    }

    /**
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = $request->input('filter', null);

        $settingManagement = new SettingsManager();
        $columnDeleted = $settingManagement->getNameColumnDeleted('CCC');

        $where = [
            "{$columnDeleted}" => '0',
        ];

        if ($request->has('filter')) {
            if ($query) {
                $parser = new Parser(Mode::FILTER());
                $node = $parser->parse($query);
                $filterValue = $node->compareValue;
            } else {
                $filterValue = null;
            }

            $where['003'] = $filterValue;
        }

        $dataConvert = [];

        if (is_exits_columns($this->masterDB, $where)) {
            $dataQuery = $this->roleModel->where($where)->get();

            if (!empty($dataQuery->toArray())) {
                $importSetting = new ImportSettingsManager();

                foreach ($dataQuery as $data) {
                    $dataFormat = $importSetting->formatDBToSCIMStandard($data->toArray(), $this->path);
                    unset($dataFormat[0]);
                    unset($dataFormat[""]);

                    array_push($dataConvert, $dataFormat);
                }
            }
        }

        $jsonData = [];
        if (!empty($dataConvert)) {
            foreach ($dataConvert as $data) {
                $dataTmp = [
                    "id" => $data['externalId'],
                    "externalId" => $data['externalId'],
                    "displayName" => $data['displayName'],
                    "meta" => [
                        "resourceType" => "Group",
                    ],
                    "members" => [],
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

        Log::info('-----------------creating role...-----------------');
        Log::info(json_encode($dataPost, JSON_PRETTY_PRINT));
        Log::info('--------------------------------------------------');

        $setting = $importSetting->getSCIMImportSettings($this->path);

        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost, $setting));

        return $this->response($dataPost, 201);
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
        // do something
        Log::info('-----------------PATCH GROUP...-----------------');
        Log::debug($id);
        Log::debug(json_encode($request->all(), JSON_PRETTY_PRINT));
        Log::info('--------------------------------------------------');

        $settingManagement = new SettingsManager();
        $columnDeleted = $settingManagement->getNameColumnDeleted($this->masterDB);
        $keyTable = $settingManagement->getTableKey($this->masterDB);

        $where = [
            "{$keyTable}" => $id,
            "{$columnDeleted}" => '0'
        ];

        if (is_exits_columns($this->masterDB, $where)) {
            $group = $this->roleModel->where($where)->first();
        } else {
            $group = null;
        }

        if (!$group) {
            throw (new SCIMException('Group Not Found'))->setCode(404);
        }

        $input = $request->input();

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

        foreach ($input['Operations'] as $operation) {

            $opTask = strtolower(array_get($operation,'op', null));
            $scimReader = new SCIMReader();
            $options = [
                "path" => $this->path,
                'operation' => $operation,
            ];

            if ($opTask === 'replace') {
                $result = $scimReader->updateReplaceSCIM($id, $options);
                if($result) return $this->response([$input['schemas'], 'detail'=>'Update Group success'], $code = 200);
            }
            elseif($opTask==='add'){
                Log::info('Add member');
                $response = $input;
                $response['id'] = $id;
                return $this->response($response, $code = 200);
            }
            elseif($opTask==='remove'){
                Log::info('Remove member');
                $response = $input;
                $response['id'] = $id;
                return $this->response($response, $code = 200);
            }
        }

//        throw (new SCIMException('Update success'))->setCode(200);
    }

    public function detail($id)
    {
        // do something
        Log::info('-----------------DETAIL GROUP...-----------------');
        Log::debug($id);
        Log::info('--------------------------------------------------');

        $settingManagement = new SettingsManager();
        $columnDeleted = $settingManagement->getNameColumnDeleted($this->masterDB);
        $keyTable = $settingManagement->getTableKey($this->masterDB);

        $where = [
            "{$keyTable}" => $id,
            "{$columnDeleted}" => '0'
        ];

        if (is_exits_columns($this->masterDB, $where)) {
            $dataQuery = $this->roleModel->where($where)->first();
        } else {
            $dataQuery = null;
        }

        $dataFormat = [];
        if ($dataQuery) {
            $importSetting = new ImportSettingsManager();
            $dataFormat = $importSetting->formatDBToSCIMStandard($dataQuery->toArray(), $this->path);
            unset($dataFormat[0]);
            unset($dataFormat[""]);
        }

        $jsonData = [];
        if (!empty($dataFormat)) {
            $jsonData = [
                "schemas" => ["urn:ietf:params:scim:schemas:core:2.0:Group"],
                "id" => $dataFormat['externalId'],
                "externalId" => $dataFormat['externalId'],
                "displayName" => $dataFormat['displayName'],
                "meta" => [
                    "resourceType" => "Group",
                ],
                "members" => [],
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
}
