<?php

namespace App\Ldaplibs\SCIM\Salesforce;

use App\Ldaplibs\SettingsManager;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CRUD
{
    protected $instance_url;
    protected $access_token;
    protected $settingManagement;

    public function __construct()
    {
        if (!isset($_SESSION) and !isset($_SESSION['salesforce'])) {
            throw new \Exception('Access Denied', 403);
        }
        $this->instance_url = $_SESSION['salesforce']['instance_url'];
        $this->access_token = $_SESSION['salesforce']['access_token'];
        $this->settingManagement = new SettingsManager();
    }

    public function query($query)
    {
        $url = "$this->instance_url/services/data/v39.0/query";

        $client = new Client();
        $request = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token"
            ],
            'query' => [
                'q' => $query
            ]
        ]);

        return json_decode($request->getBody(), true);
    }

    public function create($object, array $data)
    {
        $camelTableName = ucfirst(strtolower($object));
        $scimInfo = array(
            'provisoning' => 'SalesForce',
            'scimMethod' => 'create',
            'table' => $camelTableName,
            'message' => '',
        );

        if ($camelTableName == 'User') {
            $scimInfo['itemId'] = $data['Alias'];
            $scimInfo['itemName'] = sprintf("%s %s", $data['LastName'], $data['FirstName']);
            $isContinueProcessing = $this->requiredItemCheck($scimInfo, $data);
            if (!$isContinueProcessing) {
                return null;
            }
        } else {
            $scimInfo['itemId'] = $data['Name'];
            $scimInfo['itemName'] = $data['DeveloperName'];
        }

        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/";

        $client = new Client();

        try {

            $request = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => "OAuth $this->access_token",
                    'Content-type' => 'application/json'
                ],
                'json' => $data
            ]);

            $status = $request->getStatusCode();

            if ($status != 201) {
                $scimInfo['message'] = $request->getReasonPhrase();
                $this->settingManagement->faildLogger($scimInfo);

                die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
            }

            $this->settingManagement->detailLogger($scimInfo);

            $response = json_decode($request->getBody(), true);
            $id = $response["id"];

            return $id;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    private function requiredItemCheck($scimInfo, $item)
    {
        $rules = [
            'Alias' => 'required',
            'UserName' => ['required', 'email:strict'],
            'Email' => 'required',
            'FirstName' => 'required',
            'LastName' => 'required',
        ];

        $validate = Validator::make($item, $rules);
        if ($validate->fails()) {
            $reqStr = 'Validation error :';
            foreach ($validate->getMessageBag()->keys() as $index => $value) {
                if ($index != 0) {
                    $reqStr = $reqStr . ',';                    
                }
                $reqStr = $reqStr . ' ' . $value;
            }
            $scimInfo['message'] = $reqStr;

            $this->settingManagement->validationLogger($scimInfo);
            return false;
        }
        return true;
    }

    public function update($object, $id, array $data)
    {
        $camelTableName = ucfirst(strtolower($object));
        $scimInfo = array(
            'provisoning' => 'SalesForce',
            'scimMethod' => 'update',
            'table' => $camelTableName,
            'message' => '',
        );

        if ($camelTableName == 'User') {
            $scimInfo['itemId'] = $data['Alias'];
            $scimInfo['itemName'] = sprintf("%s %s", $data['LastName'], $data['FirstName']);
            $isContinueProcessing = $this->requiredItemCheck($scimInfo, $data);
            if (!$isContinueProcessing) {
                return null;
            }
        } else {
            $scimInfo['itemId'] = $data['Name'];
            $scimInfo['itemName'] = $data['DeveloperName'];
        }

        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/$id";

        $client = new Client();

        try {
            $request = $client->request('PATCH', $url, [
                'headers' => [
                    'Authorization' => "OAuth $this->access_token",
                    'Content-type' => 'application/json'
                ],
                'json' => $data
            ]);

            $status = $request->getStatusCode();

            if ($status != 204) {
                $scimInfo['message'] = $request->getReasonPhrase();
                $this->settingManagement->faildLogger($scimInfo);
    
                die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
            }

            $this->settingManagement->detailLogger($scimInfo);

            return $status;
        
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    public function password($object, $id, array $data)
    {
        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/$id/password";

        $client = new Client();

        try {
            $request = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => "OAuth $this->access_token",
                    'Content-type' => 'application/json'
                ],
                'json' => $data
            ]);

            $status = $request->getStatusCode();
            return $status;
        } catch (\Exception $e) {
            if ($e->getCode() == 500 && strpos($e->getMessage(), 'invalid repeated password') !== false) {
                // password not changed
            } else {
                Log::error($e);
            }
        }
        return null;
    }

    public function delete($object, $id)
    {
        $scimInfo = array(
            'provisoning' => 'SalesForce',
            'scimMethod' => 'delete',
            'table' => ucfirst(strtolower($object)),
            'itemId' => $id,
            'itemName' => '',
            'message' => '',
        );

        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/$id";

        $client = new Client();
        try {

            $request = $client->request('DELETE', $url, [
                'headers' => [
                    'Authorization' => "OAuth $this->access_token",
                ]
            ]);

            $status = $request->getStatusCode();

            if ($status != 204) {
                $scimInfo['message'] = $request->getReasonPhrase();
                $this->settingManagement->faildLogger($scimInfo);

                die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
            }
            $this->settingManagement->detailLogger($scimInfo);

            return true;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return false;
        }
    }

    public function addMemberToGroup($memberId, $groupId)
    {
        $url = "$this->instance_url/services/data/v39.0/sobjects/GroupMember/";
        $data = json_decode(Config::get('schemas.addMemberToGroup'));
        $data->GroupId = $groupId;
        $data->UserOrGroupId = $memberId;

        $client = new Client();

        try {
            $request = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => "OAuth $this->access_token",
                    'Content-type' => 'application/json'
                ],
                'json' => $data
            ]);

            $status = $request->getStatusCode();
            return $status;
        } catch (\Exception $e) {
            if ($e->getCode() == 500 && strpos($e->getMessage(), 'invalid repeated password') !== false) {
                // password not changed
            } else {
                Log::error($e);
            }
        }
        return null;
    }

    public function getResourceDetail($object, $id = null)
    {
        if ($id == null) {
            $url = "$this->instance_url/services/data/v39.0/sobjects/$object";
        } else {
            $url = "$this->instance_url/services/data/v39.0/sobjects/$object/$id";
        }

        $client = new Client();

        try {
            $request = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => "OAuth $this->access_token",
                    'Content-type' => 'application/json'
                ],
                'http_errors' => false
            ]);
            $status = $request->getStatusCode();
            if ($status == '404') {
                return null;
            }
            return json_decode($request->getBody(), true);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), '404 Not Found') !== false) {
                // resource was deleted
            } else {
                Log::error($exception);
            }
        }
        return null;
    }
}
