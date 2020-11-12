<?php

namespace App\Ldaplibs\SCIM\Salesforce;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class CRUD
{
    protected $instance_url;
    protected $access_token;

    public function __construct()
    {
        if (!isset($_SESSION) and !isset($_SESSION['salesforce'])) {
            throw new \Exception('Access Denied', 403);
        }

        $this->instance_url = $_SESSION['salesforce']['instance_url'];
        $this->access_token = $_SESSION['salesforce']['access_token'];
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
        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/";

        $client = new Client();

        $request = $client->request('POST', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token",
                'Content-type' => 'application/json'
            ],
            'json' => $data
        ]);

        $status = $request->getStatusCode();

        if ($status != 201) {
            die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
        }

        $response = json_decode($request->getBody(), true);
        $id = $response["id"];

        return $id;

    }

    public function update($object, $id, array $data)
    {
        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/$id";

        $client = new Client();

        $request = $client->request('PATCH', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token",
                'Content-type' => 'application/json'
            ],
            'json' => $data
        ]);

        $status = $request->getStatusCode();

        if ($status != 204) {
            die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
        }

        return $status;
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
            if ($e->getCode() == 500 && strpos($e->getMessage(),'invalid repeated password') !== false) {
                // password not changed
            } else {
                Log::error($e);
            }
        }
        return null;
    }

    public function delete($object, $id)
    {
        $url = "$this->instance_url/services/data/v39.0/sobjects/$object/$id";

        $client = new Client();
        $request = $client->request('DELETE', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token",
            ]
        ]);

        $status = $request->getStatusCode();

        if ($status != 204) {
            die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
        }

        return true;
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
            if (strpos($e->getMessage(),'404 Not Found') !== false) {
                // resource was deleted
            } else {
                Log::error($exception);
            }
        }
        return null;
    }

}
