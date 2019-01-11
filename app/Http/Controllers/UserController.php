<?php

namespace App\Http\Controllers;

use App\Http\Models\User;
use App\Http\Models\UserResource;
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

        Log::info('---------------------------------------------------');
        Log::info('-----------------creating user...-----------------');
        Log::info(json_encode($dataPost, JSON_PRETTY_PRINT));
        Log::info('---------------------------------------------------');
        sleep(1);

        // save user resources model
        UserResource::create([
            "data" => json_encode($dataPost, JSON_PRETTY_PRINT),
        ]);

        $dataToSaveToDB = [
            'firstName' => $dataPost['name']['givenName'],
            'familyName' => $dataPost['name']['familyName'],
            'fullName' => $dataPost['name']['formatted'],
            'externalId' => $dataPost['externalId'],
            'email' => $dataPost['userName'],
            'displayName' => $dataPost['displayName'],
            'role_id' => $dataPost['title'],
            'organization_id' => $dataPost[self::SCHEMAS_EXTENSION_USER]['department'],
        ];

        // save users model
        User::create($dataToSaveToDB);

        return $this->response('{"schemas":["urn:ietf:params:scim:schemas:core:2.0:User"]}');
    }

    public function welcome()
    {
        $importSetting = new ImportSettingsManager();
        $scimIni = $importSetting->getSCIMImportSettings();

        return view('welcome');
    }
}
