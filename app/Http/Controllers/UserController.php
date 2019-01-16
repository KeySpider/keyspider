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

use App\Http\Models\User;
use App\Http\Models\UserResource;
use App\Jobs\DBImporterFromScimJob;
use App\Ldaplibs\Import\ImportQueueManager;
use App\Ldaplibs\Import\ImportSettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Optimus\Bruno\EloquentBuilderTrait;
use Optimus\Bruno\LaravelController;

class UserController extends LaravelController
{
    use EloquentBuilderTrait;

    const SCHEMAS_EXTENSION_USER = "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User";

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function welcome()
    {
        return view('welcome');
    }

    public function index(Request $request)
    {
        $this->setFilterForRequest($request);

        $resourceOptions = $this->parseResourceOptions($request);

        // Start a new query for books using Eloquent query builder
        // (This would normally live somewhere else, e.g. in a Repository)
        $query = User::query();
        $this->applyResourceOptions($query, $resourceOptions);

        $users = $query->get();
        $parsedData = $this->parseData($users, $resourceOptions, 'users');

        sleep(1);
        return $this->response($this->toSCIMArray($parsedData));
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

        $filePath = storage_path('ini_configs/import/UserInfoSCIMInput.ini');
        $setting = $importSetting->getSCIMImportSettings($filePath);

        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost, $setting));

        // save users resource
        UserResource::create([
            "data" => json_encode($request->all()),
        ]);
        return $this->response('{"schemas":["urn:ietf:params:scim:schemas:core:2.0:User"]}');
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
     */
    public function update($id)
    {
        // do something
    }

    /**
     * Destroy user
     *
     * @param $id
     * @return \Optimus\Bruno\Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        Log::info('-----------------DELETE USER...-----------------');
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

    private function toSCIMArray($dataArray)
    {
        $arr = [
            'totalResults' => count($dataArray),
            "itemsPerPage" => 10,
            "startIndex" => 1,
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:ListResponse"
            ],
            'Resources' => [],
        ];
        return $arr;
    }
}
