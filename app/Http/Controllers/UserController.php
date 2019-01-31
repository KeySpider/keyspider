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

class UserController extends LaravelController
{
    use EloquentBuilderTrait;

    public const SCHEMAS_EXTENSION_USER = "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User";

    protected $userModel;
    protected $masterDB;
    protected $path;

    public function __construct(AAA $userModel)
    {
        $this->userModel = $userModel;
        $this->masterDB = 'AAA';
        $this->path = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function welcome()
    {
        return view('welcome');
    }

    /**
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $result = null;
        $query = $request->input('filter', null);

        $settingManagement = new SettingsManager();
        $columnDeleted = $settingManagement->getNameColumnDeleted($this->masterDB);
        $keyTable = $settingManagement->getTableKey($this->masterDB);

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

            $pattern = '/([A-Za-z0-9\._+]+)@(.*)/';
            $isPattern = preg_match($pattern, $filterValue, $result);

            $valueQuery = null;
            if ($isPattern) {
                $valueQuery = $result[1];
            }

            $where[$keyTable] = $valueQuery;
        }

        $dataQuery = $this->userModel->where($where)->get();
        $dataConvert = [];

        if (!empty($dataQuery->toArray())) {
            $importSetting = new ImportSettingsManager();

            foreach ($dataQuery as $data) {
                $dataFormat = $importSetting->formatDBToSCIMStandard($data->toArray(), $this->path);
                $dataFormat['id'] = $dataFormat['userName'];
                $dataFormat['externalId'] = $dataFormat['userName'];
                $dataFormat['userName'] = $result ? "{$dataFormat['userName']}@{$result[2]}" : $dataFormat['userName'];
                unset($dataFormat[0]);

                array_push($dataConvert, $dataFormat);
            }
        }

        $jsonData = [];
        if (!empty($dataConvert)) {
            foreach ($dataConvert as $data) {
                $dataTmp = [
                    'id' => $data['id'],
                    "externalId" => $data['externalId'],
                    "userName" => str_replace("\"", "", $data['userName']),
                    "active" => true,
                    "displayName" => $data['displayName'],
                    "meta" => [
                        "resourceType" => "User",
                    ],
                    "name" => [
                        "formatted" => "",
                        "familyName" => "",
                        "givenName" => "",
                    ],
                    "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User" => [
                        "department" => $data['department']
                    ],
                ];

                array_push($jsonData, $dataTmp);
            }
        }

        return $this->response($this->toSCIMArray($jsonData), $code = 200);
    }

    /**
     * @param $id
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     * @throws SCIMException
     */
    public function detail($id)
    {
        Log::info('-----------------DETAIL USER...-----------------');
        Log::debug($id);
        Log::info('--------------------------------------------------');

        $settingManagement = new SettingsManager();
        $columnDeleted = $settingManagement->getNameColumnDeleted($this->masterDB);
        $keyTable = $settingManagement->getTableKey($this->masterDB);

        $dataQuery = $this->userModel->where([
            "{$keyTable}" => $id,
            "{$columnDeleted}" => '0',
        ])->first();

        if (!$dataQuery) {
            throw (new SCIMException('User Not Found'))->setCode(404);
        }

        $dataFormat = [];
        if ($dataQuery) {
            $importSetting = new ImportSettingsManager();
            $dataFormat = $importSetting->formatDBToSCIMStandard($dataQuery->toArray(), $this->path);
            $dataFormat['id'] = $dataFormat['userName'];
            unset($dataFormat[0]);
        }

        $jsonData = [];
        if (!empty($dataFormat)) {
            $jsonData = [
                "schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
                'id' => $dataFormat['userName'],
                "externalId" => $dataFormat['userName'],
                "userName" => "{$id}@keyspiderjp.onmicrosoft.com",
                "active" => $dataQuery->{"{$columnDeleted}"} === '0' ? true : false,
                "displayName" => $dataFormat['displayName'],
                "meta" => [
                    "resourceType" => "User",
                ],
                "name" => [
                    "formatted" => "",
                    "familyName" => "",
                    "givenName" => "",
                ],
                "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User" => [
                    "department" => $dataFormat['department']
                ],
            ];
        }

        return $this->response($jsonData, $code = 200);
    }

    /**
     * Create data
     *
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function store(Request $request)
    {
        $dataPost = $request->all();
        $importSetting = new ImportSettingsManager();

        Log::info('-----------------creating user...-----------------');
        Log::info(json_encode($dataPost, JSON_PRETTY_PRINT));
        Log::info('--------------------------------------------------');

        $setting = $importSetting->getSCIMImportSettings($this->path);

        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost, $setting));

        return $this->response($dataPost, $code = 201);
    }

    /**
     * Update
     *
     * @param $id
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     * @throws SCIMException
     */
    public function update($id, Request $request)
    {
        // do something
        Log::info('-----------------PATCH USER...-----------------');
        Log::debug($id);
        Log::debug(json_encode($request->all(), JSON_PRETTY_PRINT));
        Log::info('--------------------------------------------------');

        $input = $request->input();

        $settingManagement = new SettingsManager();
        $columnDeleted = $settingManagement->getNameColumnDeleted($this->masterDB);
        $keyTable = $settingManagement->getTableKey($this->masterDB);

        $user = $this->userModel->where([
            "{$keyTable}" => $id,
            "{$columnDeleted}" => '0'
        ])->first();

        if (!$user) {
            throw (new SCIMException('User Not Found'))->setCode(404);
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

        $processReplace = [];
        foreach ($input['Operations'] as $operation) {
            // process Operations Replace
            if (strtolower($operation['op']) === 'replace' && $operation['path'] !== 'userName') {
                array_push($processReplace, $operation);
            }
        }

        foreach ($processReplace as $key => $op) {
            $scimReader = new SCIMReader();
            $options = [
                "path" => $this->path,
                'operation' => $op,
            ];
            $scimReader->updateReplaceSCIM($id, $options);
        }

        $jsonResponse = [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:Success"
            ],
            "detail" => "Update User success",
            "status"=> 200
        ];

        return $this->response($jsonResponse);
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
