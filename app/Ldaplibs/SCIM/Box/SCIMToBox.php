<?php

namespace App\Ldaplibs\SCIM\Box;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\Curl;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

use RuntimeException;

class SCIMToBox
{
    protected $setting;

    private $externalIdName;

    /**
     * SCIMToBox constructor.
     */
    public function __construct()
    {
    }

    public function initialize($setting, $externalIdName)
    {
        $this->setting = $setting;
        $this->settingManagement = new SettingsManager();
        $this->externalIdName = $externalIdName;
    }

    public function getServiceName() {
        return "BOX";
    }

    private function getAccessToken()
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "token", "", "", "", ""
        );

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];

        $url = "https://api.box.com/oauth2/token";
        $params = [
            "client_id" => $scimOptions["clientId"],
            "client_secret" => $scimOptions["clientSecret"],
            "grant_type" => "client_credentials",
            "box_subject_type" => "enterprise",
            "box_subject_id" => $scimOptions["enterpriseId"]
        ];

        $curl = new Curl();
        $curl->init($url, "POST", null, $params);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            Log::debug("Failed to get access token");
            $scimInfo["message"] = "Failed to get access token";
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::info("Token " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $returnValue = $response["access_token"];
        }
        $curl->close();
        return $returnValue;
    }

    public function createResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "create", ucfirst(strtolower($resourceType)), $item["ID"], $item["name"], ""
        );

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];
        $url = $scimOptions["url"] . strtolower($resourceType) . "s/";
        $auth = "Bearer " . $this->getAccessToken();
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
            Log::error($response["message"]);
            $scimInfo["message"] = $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::info("Create " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
            $returnValue = $response["id"];
        }
        $curl->close();
        return $returnValue;
    }

    public function updateResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "update", ucfirst(strtolower($resourceType)), $item["ID"], $item["name"], ""
        );

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];
        $url = $scimOptions["url"] . strtolower($resourceType) . "s/" . $item[$this->externalIdName];
        $auth = "Bearer " . $this->getAccessToken();
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
            Log::error($response["message"]);
            $scimInfo["message"] = $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            Log::info("Replace " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
            $returnValue = $item[$this->externalIdName];
        }
        $curl->close();
        return $returnValue;
    }

    public function deleteResource($resourceType, $item)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "delete", ucfirst(strtolower($resourceType)), $item["ID"], $item["name"], ""
        );

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];
        $url = $scimOptions["url"] . strtolower($resourceType) . "s/" . $item[$this->externalIdName];
        $auth = "Bearer " . $this->getAccessToken();

        $curlHeader = array("Authorization: $auth", "accept: */*");
        $curl = new Curl();
        $curl->init($url, "DELETE", $curlHeader, null);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            Log::error($response["message"]);
            $scimInfo["message"] = $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            Log::info("Delete " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
            $returnValue = true;
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

    public function userGroup($resourceType, $item, $externalId): void
    {
        if ($resourceType != "User" || empty($externalId)) {
            return;
        }

        // Import data role info
        $memberOf = $this->getListOfGroupsUserBelongedTo($item["ID"], "BOX");

        // Now stored role info
        $groupIDListOnBOX = $this->getMemberOfBOX($externalId);

        foreach ($memberOf as $groupID) {
            if (!empty($groupID) && !array_key_exists($groupID, $groupIDListOnBOX)) {
                $this->addMemberToGroup($externalId, $groupID);
            }
        }

        foreach ($groupIDListOnBOX as $key => $groupID) {
            if (!in_array($key, $memberOf)) {
                $this->removeMemberOfGroup($externalId, $groupID);
            }
        }
    }

    public function userRole($resourceType, $item, $externalId) {
        return;
    }

    private function replaceResource($resourceType, $item)
    {
        $getEncryptedFields = $this->settingManagement->getEncryptedFields();

        $tmp = Config::get("scim-box.createUser");
        if ($resourceType === "Group") {
            $tmp = Config::get("scim-box.createGroup");
        }

        foreach ($item as $key => $value) {
            if ($key === "state") {
                $address = sprintf(
                    "%s, %s, %s",
                    $item["state"],
                    $item["city"],
                    $item["streetAddress"]
                );
                $tmp = str_replace("(User.joinAddress)", $address, $tmp);
                continue;
            }

            if ($key === "locked") {
                $isActive = "active";
                if ($value == "1") $isActive = "inactive";
                $tmp = str_replace("(User.DeleteFlag)", $isActive, $tmp);
                continue;
            }

            $twColumn = $resourceType . ".$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $this->settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(" . $resourceType . ".$key)", $value, $tmp);
        }

        // if not yet replace als code, replace to null
        $tmp = str_replace('    "status": "(User.DeleteFlag)",', "", $tmp);

        return $tmp;
    }

    private function getListOfGroupsUserBelongedTo($id, $scims = ""): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($id, $scims);
        return $memberOf;
    }

    private function getMemberOfBOX($uPN)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "getMemberOfBox", "BOX", $uPN, "", ""
        );

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];
        $url = $scimOptions["url"] . "users/" . $uPN . "/memberships/";
        $auth = "Bearer " . $this->getAccessToken();
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];

        $curlHeader = array("Authorization: $auth", "Content-type: $contentType", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "GET", $curlHeader,  null);

        $groupIDList = [];
        $result = $curl->execute("total_count");
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            Log::error($response["message"]);
            $scimInfo["message"] = $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $response = json_decode($curl->getResponse(), true);
            for ($i = 0; $i < $response["total_count"]; $i++) {
                $groupIDList[$response["entries"][$i]["group"]["id"]] = $response["entries"][$i]["id"];
            }
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $groupIDList;
    }

    private function addMemberToGroup($uPCN, $groupId): void
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "addMemberToGroup", "", $uPCN, $groupId, ""
        );

        $tmpl = Config::get("scim-box.addGroup");
        $tmpl = str_replace("(upn)", $uPCN, $tmpl);
        $tmpl = str_replace("(gpn)", $groupId, $tmpl);

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];
        $url = $scimOptions["url"] . "group_memberships";
        $auth = "Bearer " . $this->getAccessToken();
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];

        $curlHeader = array("Authorization: $auth", "Content-type: $contentType", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $tmpl);

        $result = $curl->execute();
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            Log::error($response["message"]);
            $scimInfo["message"] = $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            Log::info("Create " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
    }

    private function removeMemberOfGroup($uPCN, $groupId)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "BOX", "removeMemberOfGroup", "BOX", $uPCN, $groupId, ""
        );

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["BOX Keys"];
        $url = $scimOptions["url"] . "group_memberships/" . $groupId . "/";
        $auth = "Bearer " . $this->getAccessToken();

        $curlHeader = array("Authorization: $auth", "accept: */*");
        $curl = new Curl();
        $curl->init($url, "DELETE", $curlHeader, null);

        $result = $curl->execute();
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            Log::error($response["message"]);
            $scimInfo["message"] = $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            Log::info("Delete " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
    }

}
