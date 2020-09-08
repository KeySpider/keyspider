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
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Optimus\Bruno\EloquentBuilderTrait;
use Optimus\Bruno\Illuminate\Http\JsonResponse;
use Optimus\Bruno\LaravelController;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Parser;

class UserController extends LaravelController
{
    use EloquentBuilderTrait;

    public const SCHEMAS_EXTENSION_USER = "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User";

    protected $masterDB;
    protected $path;

    public function __construct()
    {
        $this->importSetting = new ImportSettingsManager();

        $SCIMImportSettingFiles = $this->importSetting->keySpider['SCIM Input Process Configration']['import_config']??[];
        foreach ($SCIMImportSettingFiles as $file){
            $fileContent = parse_ini_file($file);
            if($fileContent['ImportTable']=='User'){
                $this->path = $file;
                break;
            }
        }
        $this->masterDB = $this->importSetting->getTableUser();
    }

    /**
     * @return Factory|View
     * @throws Exception
     */
    public function welcome()
    {
        return view('welcome');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $result = null;
        $columnDeleted = $this->importSetting->getDeleteFlagColumnName($this->masterDB);

        $sqlQuery = DB::table($this->importSetting->getTableUser());
//        $sqlQuery->where($columnDeleted, '!=', '1');

        $scimQuery = $request->input('filter', null);
        $keyTable = $this->importSetting->getTableKey();
        if ($request->has('filter')) {
            if ($scimQuery) {
                $parser = new Parser(Mode::FILTER());
                $node = $parser->parse($scimQuery);
                $filterValue = $node->compareValue;
            } else {
                $filterValue = null;
            }
            $sqlQuery->where($keyTable, $filterValue);
//            $where[$keyTable] = $filterValue;
        }

        $dataConvert = [];

//        $sqlQuery->where($columnDeleted, '!=', '1');
        $sqlString = $sqlQuery->toSql();
        $dataQuery = $sqlQuery->get();

        if (!empty($dataQuery->toArray())) {
            foreach ($dataQuery as $data) {
                $dataFormat = $this->importSetting->formatDBToSCIMStandard((array)$data, $this->path);
                $dataFormat['id'] = $dataFormat['userName'];
                $dataFormat['externalId'] = $dataFormat['userName'];
                $dataFormat['userName'] =
                    $result ? "{$dataFormat['userName']}@{$result[2]}" : $dataFormat['userName'];
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
                    "active" => $data['active'] === '1' ? false : true,
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
                        "department" => array_get($data, 'department', "")
                    ],
                ];

                array_push($jsonData, $dataTmp);
            }
        }

        return $this->response($this->toSCIMArray($jsonData), $code = 200);
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws SCIMException
     */
    public function detail($id)
    {
        Log::info('-----------------DETAIL USER...-----------------');
        Log::debug($id);
        Log::info('--------------------------------------------------');

        $columnDeleted = $this->importSetting->getDeleteFlagColumnName($this->masterDB);
        $keyTable = $this->importSetting->getTableKey();

        try {
            $query = DB::table($this->importSetting->getTableUser());
            $query = $query->where(function ($query) use ($keyTable, $columnDeleted, $id) {
                $query->where($keyTable, $id);
//                $query->where($columnDeleted, '!=', 1);
            });
            $toSql = $query->toSql();
            Log::info($toSql);
            $userRecord = $query->first();
        } catch (Exception $exception) {
            throw (new SCIMException($query->toSql()))->setCode(404);
        }

        if (!$userRecord) {
            throw (new SCIMException("Not Found User Id: $id"))->setCode(404);
        }

        $jsonData = $this->getResponseFromUserRecord($userRecord, $columnDeleted);

        return $this->response($jsonData, $code = 200);
    }

    /**
     * Create data
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function store(Request $request)
    {
        $dataPost = $request->all();

        Log::info('-----------------creating user...-----------------');
        Log::alert("[" . $request->method() . "]");
        Log::info(json_encode($dataPost, JSON_PRETTY_PRINT));
        Log::info('--------------------------------------------------');

        $setting = $this->importSetting->getSCIMImportSettings($this->path);

        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost, $setting));
        $dataResponse = $dataPost;
        $dataResponse['id'] = array_get($dataPost, 'externalId', null);
        $dataResponse['meta']['location'] = $request->fullUrl() . '/' . $dataPost['externalId'];
        return $this->response($dataResponse, $code = 201);
    }

    public function destroy($id, Request $request)
    {
        // do something
        Log::info('-----------------DELETE USER...-----------------');
        Log::debug($id);
        Log::info('--------------------------------------------------');

        $this->checkToResponseErrorUserNotFound($id);

        $this->logicalDeleteUser($id);

        $jsonResponse = [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:Success"
            ],
            "detail" => "Delete User success",
            "id" => $id,
            "meta" => [
                "resourceType" => "User"
            ],
            "status" => 200
        ];

        return $this->response($jsonResponse);
    }

    /**
     * logicalDeleteUser
     *
     * @param $id
     * @return Bool
     * @throws SCIMException
     */
    private function logicalDeleteUser($id) {
        $deleteFlagColumnName = $this->importSetting->getDeleteFlagColumnName($this->masterDB);
        $updateFlagsColumnName = $this->importSetting->getUpdateFlagsColumnName($this->masterDB);

        $updateFlags = $this->importSetting->getAllExtractionProcessID($this->masterDB);

        $setValues = [];
        $setValues[$deleteFlagColumnName] = config('const.SET_ALL_EXTRACTIONS_IS_TRUE');
        $setValues[$updateFlagsColumnName] = json_encode($updateFlags);

        $keyTable = $this->importSetting->getTableKey();
        $query = DB::table($this->importSetting->getTableUser());
        $data = $query->where($keyTable, $id)->first();

        // $data = DB::table($nameTable)->where($primaryKey, $dataCreate[$primaryKey])->first();

        if ($data) {
            DB::table($this->importSetting->getTableUser())
                ->where($keyTable, $id)->update($setValues);
        } else {
            throw (new SCIMException('User Not Found'))->setCode(404);
            return false;
        }
        return true;
    }

    /**
     * Update
     *
     * @param $id
     * @param Request $request
     * @return JsonResponse
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

        $columnDeleted = $this->importSetting->getDeleteFlagColumnName($this->masterDB);
        $input = $this->checkToResponseErrorCase($id, $input);
        $scimReader = new SCIMReader();
        $setting = $this->importSetting->getSCIMImportSettings($this->path);
        $scimReader->updateRsource($id, $input, $setting);

        $jsonResponse = [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:Success"
            ],
            "detail" => "Update User success",
            "id" => $id,
            "meta" => [
                "resourceType" => "User"
            ],
            "status" => 200
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

    /**
     * @param $userRecord
     * @param $columnDeleted
     * @return array
     */
    private function getResponseFromUserRecord($userRecord, $columnDeleted): array
    {
        $dataFormat = [];
        if ($userRecord) {
//            $userResouce = $userRecord->toArray();
            $userResouce = (array)$userRecord;
            $dataFormat = $this->importSetting->formatDBToSCIMStandard($userResouce, $this->path);
            $dataFormat['id'] = $dataFormat['userName'];
            unset($dataFormat[0]);
        }

        $jsonData = [];
        if (!empty($dataFormat)) {
            $jsonData = [
                "schemas" => ["urn:ietf:params:scim:schemas:core:2.0:User"],
                'id' => $dataFormat['externalId'],
                "externalId" => $dataFormat['externalId'],
                "userName" => $dataFormat['userName'],
                "active" => $userRecord->{"{$columnDeleted}"} === '1' ? false : true,
                "displayName" => $dataFormat['userName'],
                "meta" => [
                    "resourceType" => "User",
                ],                 "name" => [
                    "formatted" => $userRecord->displayName,
                    "familyName" => $userRecord->Name,
                    "givenName" => $userRecord->givenName,
                ],
                "emails"=> [[
                    "value"=> $userRecord->mail,
                    "type"=> "work",
                    "primary"=> true
                ]],
                "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User" => [
                    "department" => array_get($dataFormat, 'department', "")
                ],
            ];
        }


        return $jsonData;
    }

    private function checkToResponseErrorUserNotFound($id)
    {
        $keyTable = $this->importSetting->getTableKey();

        $sqlQuery = DB::table($this->importSetting->getTableUser());
        $where = [
            "{$keyTable}" => $id,
        ];

        if (is_exits_columns($this->masterDB, $where)) {
            $user = $sqlQuery->where($where)->first();
        } else {
            $user = null;
        }

        if (!$user) {
            throw (new SCIMException('User Not Found'))->setCode(404);
        }
    }

    /**
     * @param $id
     * @param $input
     * @return mixed
     * @throws SCIMException
     */
    private function checkToResponseErrorCase($id, $input)
    {
        $keyTable = $this->importSetting->getTableKey();

        $sqlQuery = DB::table($this->importSetting->getTableUser());
        $where = [
            "{$keyTable}" => $id,
//            "{$columnDeleted}" => '0'
        ];

        if (is_exits_columns($this->masterDB, $where)) {
            $user = $sqlQuery->where($where)->first();
        } else {
            $user = null;
        }

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
        return $input;
    }
}
