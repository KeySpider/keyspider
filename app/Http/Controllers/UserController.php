<?php

namespace App\Http\Controllers;

use App\Http\Models\User;
use App\Http\Models\UserResource;
use App\Jobs\DBImporterFromScimJob;
use App\Ldaplibs\Import\DBImporterFromScimData;
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

    public function store(Request $request)
    {
        $dataPost = $request->all();

        Log::info('-----------------creating user...-----------------');
        // save user resources model
        $queue = new ImportQueueManager();
        $queue->push(new DBImporterFromScimJob($dataPost));
        return $this->response('{"schemas":["urn:ietf:params:scim:schemas:core:2.0:User"]}');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function welcome()
    {
        $importSetting = new ImportSettingsManager();
        $scimIni = $importSetting->getSCIMImportSettings();
        return view('welcome');
    }

}

