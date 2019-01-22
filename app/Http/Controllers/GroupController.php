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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Optimus\Bruno\EloquentBuilderTrait;
use Optimus\Bruno\LaravelController;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Parser;

class GroupController extends LaravelController
{
    use EloquentBuilderTrait;

    /**
     * @param Request $request
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $fileIni = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');

        $parser = new Parser(Mode::FILTER());
        $node = $parser->parse($request->input('filter'));

        $dataQuery = CCC::where('003', $node->compareValue)->first();

        $dataFormat = [];
        if ($dataQuery) {
            $importSetting = new ImportSettingsManager();
            $dataFormat = $importSetting->formatDBToSCIMStandard($dataQuery->toArray(), $fileIni);
            unset($dataFormat[0]);
            unset($dataFormat[""]);
        }

        $jsonData = [];
        if (!empty($dataFormat)) {
            $data = [
                "id" => $dataFormat['externalId'],
                "externalId" => $dataFormat['externalId'],
                "displayName" => $dataFormat['displayName'],
                "meta" => [
                    "resourceType" => "Group",
                ],
                "members" => [],
            ];
            array_push($jsonData, $data);
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

        $filePath = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');
        $setting = $importSetting->getSCIMImportSettings($filePath);

        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost, $setting));

        return $this->response($dataPost, 201);
    }

    /**
     * Show detail data
     *
     * @param $id
     */
    public function show($id)
    {
        // do something
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

        $group = CCC::where('001', $id)->first();

        if (!$group) {
            throw (new SCIMException('User Not Found'))->setCode(400);
        }

        $filePath = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');
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
            if (strtolower($operation['op']) === 'replace') {
                $scimReader = new SCIMReader();
                $options = [
                    "path" => $filePath,
                    'operation' => $operation,
                ];
                $scimReader->updateReplaceSCIM($id, $options);
            }
        }

        throw (new SCIMException('Update success'))->setCode(200);
    }

    public function detail($id)
    {
        // do something
        Log::info('-----------------DETAIL GROUP...-----------------');
        Log::debug($id);
        Log::info('--------------------------------------------------');

        $dataQuery = CCC::where('001', $id)->first();

        $fileIni = storage_path('ini_configs/import/RoleInfoSCIMInput.ini');

        $dataFormat = [];
        if ($dataQuery) {
            $importSetting = new ImportSettingsManager();
            $dataFormat = $importSetting->formatDBToSCIMStandard($dataQuery->toArray(), $fileIni);
            unset($dataFormat[0]);
            unset($dataFormat[""]);
        }

        $jsonData = [];
        if (!empty($dataFormat)) {
            $jsonData = [
                "id" => $dataFormat['externalId'],
                "externalId" => $dataFormat['externalId'],
                "displayName" => $dataFormat['displayName'],
                "meta" => [
                    "resourceType" => "Group",
                ],
                "members" => [],
                "schemas" => [
                    "urn:ietf:params:scim:api:messages:2.0:Group"
                ],
            ];
        }

        return $this->response($jsonData, $code = 200);
    }

    /**
     * Destroy user
     *
     * @param $id
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        Log::info('-----------------DELETE GROUPS...-----------------');
        Log::debug($id);
        Log::info('--------------------------------------------------');

        $response = [
            'totalResults' => count([]),
            "itemsPerPage" => 10,
            "startIndex" => 1,
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:ListResponse"
            ],
            'Resources' => [],
        ];

        return $this->response($response);
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function setFilterForRequest(Request &$request): bool
    {
        $filter = explode(' ', $request->input('filter'));
        try {
            $request["filter_groups"] = [
                0 =>
                    [
                        'filters' =>
                            [
                                0 =>
                                    [
                                        'key' => $filter[0],
                                        'value' => $filter[2],
                                        'operator' => $filter[1],
                                    ],
                            ],
                    ],
            ];
            return true;
        } catch (\Exception $exception) {
            return false;
        }
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
