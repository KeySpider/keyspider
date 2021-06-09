<?php

namespace App\Ldaplibs\SCIM\TrustLogin;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\Curl;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SCIMToTrustLogin
{
    protected $setting;
    protected $regExpManagement;

    private $externalIdName;

    /**
     * SCIMToTrustLogin constructor.
     */
    public function __construct()
    {
    }

    public function initialize($setting, $externalIdName)
    {
        $this->setting = $setting;
        $this->externalIdName = $externalIdName;
        $this->regExpManagement = new RegExpsManager();
        $this->settingManagement = new SettingsManager();
    }

    public function getServiceName() {
        return "TrustLogin";
    }

    public function createResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "TrustLogin", "create", ucfirst(strtolower($resourceType)),
            $item["Alias"], sprintf("%s %s", $item["surname"], $item["givenName"]), ""
        );

        $isContinueProcessing = $this->requiredItemCheck($scimInfo, $item);
        if (!$isContinueProcessing) {
            return null;
        }

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["TrustLogin Keys"];
        $url = $scimOptions["url"];
        $auth = $scimOptions["authorization"];
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];

        $curlHeader = array("Authorization: $auth", "Content-type: $contentType", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $tmpl);

        $result = $curl->execute("id");
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            $scimInfo["message"] = $response["detail"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "failed") {
            $response = json_decode($curl->getResponse(), true);
            if (array_key_exists("status", $response)) {
                $curl_status = $response["status"];
                $info = $curl->getInfo();
                Log::error($info);
                Log::error($response);
                Log::error(
                    "Create faild status = " . $curl_status . $info["total_time"]
                    . " seconds to send a request to " . $info["url"]
                );
                $scimInfo["message"] = "Create faild status = " . $curl_status;
                $this->settingManagement->faildLogger($scimInfo);
            } else {
                Log::info("Create " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            }
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            $returnValue = $response["id"];
            Log::info("Create " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    public function updateResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "TrustLogin", "update", ucfirst(strtolower($resourceType)),
            $item["Alias"], sprintf("%s %s", $item["surname"], $item["givenName"]), ""
        );

        $isContinueProcessing = $this->requiredItemCheck($scimInfo, $item);
        if (!$isContinueProcessing) {
            return null;
        }

        $tmpl = $this->replaceResource($resourceType, $item);
var_dump($tmpl);
        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["TrustLogin Keys"];
        $url = $scimOptions["url"] . "/" . $item[$this->externalIdName];
        $auth = $scimOptions["authorization"];
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];

        $curlHeader = array("Authorization: $auth", "Content-type: $contentType", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "PUT", $curlHeader, $tmpl);

        $result = $curl->execute("id");
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            $scimInfo["message"] = $response["detail"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "failed") {
            $response = json_decode($curl->getResponse(), true);
            if (array_key_exists("status", $response)) {
                $curl_status = $response["status"];
                $info = $curl->getInfo();
                Log::error($info);
                Log::error($response);
                Log::error(
                    "Replace faild status = " . $curl_status . $info["total_time"]
                    . " seconds to send a request to " . $info["url"]
                );
                $scimInfo["message"] = "Replace faild status = " . $curl_status;
                $this->settingManagement->faildLogger($scimInfo);
            } else {
                Log::info("Replace " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            }
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $returnValue = $item[$this->externalIdName];
            Log::info("Replace " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    public function deleteResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "TrustLogin", "delete", ucfirst(strtolower($resourceType)),
            $item["Alias"], sprintf("%s %s", $item["surname"], $item["givenName"]), ""
        );

        $externalID = $item[$this->externalIdName];

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["TrustLogin Keys"];
        $url = $scimOptions["url"] . "/". $item[$this->externalIdName];
        $auth = $scimOptions["authorization"];

        $curlHeader = array("Authorization: $auth", "accept: */*");
        $curl = new Curl();
        $curl->init($url, "DELETE", $curlHeader);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            $scimInfo["message"] = $response["detail"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $returnValue = true;
            Log::info("Delete " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    public function passwordResource($resourceType, $item, $externalId)
    {
        return;
    }

    public function statusResource($resourceType, $item, $externalId)
    {
        return;
    }

    public function userGroup($resourceType, $item, $externalId)
    {
        return;
    }

    public function userRole($resourceType, $item, $externalId)
    {
        return;
    }

    private function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmp = Config::get("scim-trustlogin.createUser");

        foreach ($item as $key => $value) {
            if ($key === "locked") {
                $isActive = 'true';
                if ( $value == "1") $isActive = 'false';
                $als = sprintf("    \"active\":%s,", $isActive);
                $tmp = str_replace("accountLockStatus", $als, $tmp);
                continue;
            }

            $twColumn = "User.$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(User.$key)", $value, $tmp);
        }
        // if not yet replace als code, replace to null
        $tmp = str_replace("accountLockStatus\n", "", $tmp);

        return $tmp;
    }

    private function requiredItemCheck($scimInfo, $item)
    {
        $rules = [
            "mail" => ["required", "email:strict"],
            "givenName" => "required",
            "surname" => "required",
        ];

        $validate = Validator::make($item, $rules);
        if ($validate->fails()) {
            $reqStr = "Validation error :";
            foreach ($validate->getMessageBag()->keys() as $index => $value) {
                if ($index != 0) {
                    $reqStr = $reqStr . ",";
                }
                $reqStr = $reqStr . " " . $value;
            }
            $scimInfo["message"] = $reqStr;

            $this->settingManagement->validationLogger($scimInfo);
            return false;
        }
        return true;
    }
}