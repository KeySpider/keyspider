<?php

namespace App\Ldaplibs\SCIM\Zoom;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\Curl;
use Illuminate\Support\Facades\Log;
use MacsiDigital\Zoom\Facades\Zoom;

class SCIMToZoom
{
    protected $setting;

    private $externalIdName;

    /**
     * SCIMToZoom constructor.
     */
    public function __construct()
    {
        $this->settingManagement = new SettingsManager();
    }

    public function initialize($setting, $externalIdName)
    {
        $this->setting = $setting;
        $this->externalIdName = $externalIdName;
        $this->settingManagement = new SettingsManager();
    }

    public function getServiceName() {
        return "ZOOM";
    }

    public function createResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);
        $externalID = null;
        if ($resourceType == "User") {
            $externalID = $this->createUser($tmpl);
        } elseif ($resourceType == "Role") {
            $externalID = $this->createRole($tmpl);
        } elseif ($resourceType == "Group") {
            $externalID = $this->createGroup($tmpl);
        }
        return $externalID;
    }

    public function updateResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);

        $externalID = null;
        if ($resourceType == "User") {
            $externalID = $this->updateUser($tmpl);
        } elseif ($resourceType == "Role") {
            $externalID = $this->updateRole($tmpl);
        } elseif ($resourceType == "Group") {
            $externalID = $this->updateGroup($tmpl);
        }
        return $externalID;
    }

    public function deleteResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);

        $externalID = null;
        if ($resourceType == "User") {
            $externalID = $this->deleteUser($resourceType, $item);
        } elseif ($resourceType == "Role") {
            $externalID = $this->deleteRole($resourceType, $item);
        } elseif ($resourceType == "Group") {
            $externalID = $this->deleteGroup($resourceType, $item);
        }
        return $externalID;
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
        if ($resourceType != "User" || empty($externalId)) {
            return;
        }

        try {
            // Import data group info
            $memberOf = $this->getListOfGroupsUserBelongedTo($item, "ZOOM");

            // Now store data group info
            $groupIDListOnZOOM = $this->getGroupMemberOfsZOOM($item, $resourceType);

            foreach ($memberOf as $groupID) {
                if (!in_array($groupID, $groupIDListOnZOOM)) {
                    $this->addMemberToGroup($item, $groupID);
                }
            }

            foreach ($groupIDListOnZOOM as $groupID) {
                if (!in_array($groupID, $memberOf)) {
                    $this->removeMemberOfGroup($item, $groupID);
                }
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }
    }

    public function userRole($resourceType, $item, $externalId)
    {
        if ($resourceType != "User" || empty($externalId)) {
            return;
        }

        // Import data Role info
        $memberOf = $this->getListOfRoleUserBelongedTo($item, "ZOOM");

        // Now store data group info
        $RoleIDOnZOOM = $this->getRoleMemberOfsZOOM($item, $resourceType);

        foreach ($memberOf as $roleID) {
            if (!in_array($roleID, $RoleIDOnZOOM)) {
                $this->assignMemberToRole($item, $roleID);
            }
        }

        /* No need to use */
        foreach ($RoleIDOnZOOM as $roleID) {
            if (!in_array($roleID, $memberOf)) {
                if (!is_numeric($roleID)) {
                    $this->unassignMemberOfRole($item, $roleID);
                }
            }
        }
    }

    private function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        foreach ($item as $key => $value) {
            $twColumn = "ZOOM.$key";

            if (in_array($twColumn, $getEncryptedFields)) {
                $item[$key] = $settingManagement->passwordDecrypt($value);
            }
        }
        return $item;
    }

    private function makeToken()
    {
        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true)["Zoom Keys"];
        $clientId = $scimOptions["clientId"];
        $clientSecret = $scimOptions["clientSecret"];

        $expiration = time() + (60 * 60 * 24); // Token expiration date(SEC)

        $header = $this->urlsafe_base64_encode('{"alg":"HS256","typ":"JWT"}');
        $payload = $this->urlsafe_base64_encode('{"iss":"' . $clientId . '","exp":' . $expiration . '}');
        $signature = $this->urlsafe_base64_encode(hash_hmac("sha256", "$header.$payload", $clientSecret, TRUE));
        $token = "$header.$payload.$signature";

        Log::debug($token);
        return $token;
    }

    private function urlsafe_base64_encode($str)
    {
        return str_replace(array("+", "/", "="), array("-", "_", ""), base64_encode($str));
    }

    private function createUser($tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "create", "User", $tmpl["ID"], sprintf("%s %s", $tmpl["last_name"], $tmpl["first_name"]), ""
        );

        Log::info("Zoom Create User -> " . $tmpl["last_name"] . " " . $tmpl["first_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
            "action": "create",
            "user_info": {
              "first_name": "(User.first_name)",
              "last_name":  "(User.last_name)",
              "email":  "(User.email)",
              "phone_country": "JP",
              "phone_number": "(User.phone_number)",
              "job_title": "(User.job_title)",
              "type": 1,
              "timezone": "Asia/Tokyo",
              "verified": 0,
              "language": "jp-JP",
              "status": "active"
            }
        }';
        $json = str_replace("(User.first_name)", $tmpl["first_name"], $json);
        $json = str_replace("(User.last_name)", $tmpl["last_name"], $json);
        $json = str_replace("(User.email)", $tmpl["email"], $json);
        $json = str_replace("(User.phone_number)", $tmpl["phone_number"], $json);
        $json = str_replace("(User.job_title)", $tmpl["job_title"], $json);

        $url = "https://api.zoom.us/v2/users/";
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $json);

        $result = $curl->execute("id");
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            $scimInfo["message"] = "Create User faild : " . $response["message"];
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
                    "Create User faild status = " . $curl_status . $info["total_time"]
                    . " seconds to send a request to " . $info["url"]
                );
                $scimInfo["message"] = "Create User faild status = " . $curl_status;
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
            Log::info("Create User " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);

            $this->updateStatus($tmpl, $returnValue);
        }
        $curl->close();
        return $returnValue;

    }

    private function updateStatus($tmpl, $externalId) {
        if (array_key_exists("locked", $tmpl) ) {
            $auth = sprintf("Bearer %s", $this->makeToken());
            $accept = "application/json";

            $userStatus = [];
            if ($tmpl["locked"] == "1") {
                $userStatus = '{"action": "deactivate"}';
            } else {
                $userStatus = '{"action": "activate"}';
            }

            $url = "https://api.zoom.us/v2/users/" . $tmpl[$this->externalIdName] . "/status";
            $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
            $curl = new Curl();
            $curl->init($url, "PUT", $curlHeader, $userStatus);

            $curl->execute();
            $curl->close();
        }
    }

    private function createRole($tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "create", "Role", $tmpl["ID"], $tmpl["name"], ""
        );

        Log::info("Zoom Create Role -> " . $tmpl["name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
                "name": "(Role.name)",
                "privileges": [
                    "User:Read",
                    "User:Edit",
                    "Group:Read",
                    "Group:Edit"
                  ]    
        }';
        $json = str_replace("(Role.name)", $tmpl["name"], $json);

        $url = "https://api.zoom.us/v2/roles/";
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $json);

        $result = $curl->execute("id");
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            $scimInfo["message"] = "Create Role faild : " . $responce["message"];
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
                    "Create Role faild status = " . $curl_status . $info["total_time"]
                    . " seconds to send a request to " . $info["url"]
                );
                $scimInfo["message"] = "Create Role faild status = " . $curl_status;
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
            Log::info("Create Role " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function createGroup($tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "create", "Group", $tmpl["ID"], $tmpl["name"], ""
        );

        Log::info("Zoom Create Group -> " . $tmpl["name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
                "name": "(Group.displayName)"
        }';
        $json = str_replace("(Group.displayName)", $tmpl["name"], $json);

        $url = "https://api.zoom.us/v2/groups/";
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $json);

        $result = $curl->execute("id");
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            $scimInfo["message"] = "Create Group faild : " . $responce["message"];
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
                    "Create Group faild status = " . $curl_status . $info["total_time"]
                    . " seconds to send a request to " . $info["url"]
                );
                $scimInfo["message"] = "Create Group faild status = " . $curl_status;
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
            Log::info("Create Group " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function updateUser($tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "update", "User", $tmpl["ID"], sprintf("%s %s", $tmpl["last_name"], $tmpl["first_name"]), ""
        );

        Log::info("Zoom Update User -> " . $tmpl["last_name"] . " " . $tmpl["first_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
            "first_name": "(User.first_name)",
            "last_name":  "(User.last_name)",
            "phone_number": "(User.phone_number)",
            "job_title" : "(User.job_title)"
        }';
        $json = str_replace("(User.first_name)", $tmpl["first_name"], $json);
        $json = str_replace("(User.last_name)", $tmpl["last_name"], $json);
        $json = str_replace("(User.phone_number)", $tmpl["phone_number"], $json);
        $json = str_replace("(User.job_title)", $tmpl["job_title"], $json);

        $url = "https://api.zoom.us/v2/users/" . $tmpl[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "PATCH", $curlHeader, $json);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $response = json_decode($curl->getResponse(), true);
            Log::error(
                "Update User faild status = " . $responce["code"] . " " . $info["total_time"]
                . " seconds to send a request to " . $info["url"]);
            $scimInfo["message"] = "Update User faild : " . $response["message"];
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
            $returnValue = $tmpl[$this->externalIdName];
            Log::info("Update User " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);

            $this->updateStatus($tmpl, $returnValue);
        }
        $curl->close();
        return $returnValue;
    }

    private function updateRole($tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "update", "Role", $tmpl["ID"], $tmpl["name"], ""
        );

        Log::info("Zoom Update Role -> " . $tmpl["name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
                "name": "(Role.name)",
                "privileges": [
                    "User:Read",
                    "User:Edit",
                    "Group:Read",
                    "Group:Edit"
                  ]    
        }';
        $json = str_replace("(Role.name)", $tmpl["name"], $json);

        $url = "https://api.zoom.us/v2/roles/" . $tmpl[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "PATCH", $curlHeader, $json);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error(
                "Replace Role faild status = " . $response["code"] . " " . $info["total_time"]
                . " seconds to send a request to " . $info["url"]
            );
            $scimInfo["message"] = "Replace Role faild : " . $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $returnValue = $tmpl[$this->externalIdName];
            $info = $curl->getInfo();
            Log::info("Replace Role " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function updateGroup($tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "update", "Group", $tmpl["ID"], $tmpl["name"], ""
        );

        Log::info("Zoom Update Role -> " . $tmpl["name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
                "name": "(Group.disylayName)"
        }';
        $json = str_replace("(Group.disylayName)", $tmpl["name"], $json);

        $url = "https://api.zoom.us/v2/groups/" . $tmpl[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "PATCH", $curlHeader, $json);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
            $scimInfo["message"] = "Curl error: " . $curl->getError();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "http_code_error") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error(
                "Replace Group faild status = " . $response["code"] . " " . $info["total_time"]
                . " seconds to send a request to " . $info["url"]
            );
            $scimInfo["message"] = "Replace Group faild : " . $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $returnValue = $tmpl[$this->externalIdName];
            $info = $curl->getInfo();
            Log::info("Replace Group " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function deleteUser($resourceType, $tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "delete", "User", $tmpl["ID"], sprintf("%s %s", $tmpl["last_name"], $tmpl["first_name"]), ""
        );

        Log::info("Zoom Delete -> " . $tmpl["last_name"] . " " . $tmpl["first_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/users/" . $tmpl[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
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
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error(
                "Delete User faild status = " . $response["code"] . " " . $info["total_time"]
                . " seconds to send a request to " . $info["url"]
            );
            $scimInfo["message"] = "Delete User faild : " . $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            // $returnValue = $tmpl[$this->externalIdName];
            $returnValue = true;
            $info = $curl->getInfo();
            Log::info("Delete User " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function deleteRole($resourceType, $tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "delete", "Role", $tmpl["ID"], $tmpl["name"], ""
        );

        Log::info("Zoom delete Role -> " . $tmpl["name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/roles/" . $tmpl[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
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
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error(
                "Delete Role faild status = " . $response["code"] . " " . $info["total_time"]
                . " seconds to send a request to " . $info["url"]
            );
            $scimInfo["message"] = "Delete Role faild : " . $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            // $returnValue = $tmpl[$this->externalIdName];
            $returnValue = true;
            $info = $curl->getInfo();
            Log::info("Delete Role " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function deleteGroup($resourceType, $tmpl)
    {
        $scimInfo = $this->settingManagement->makeScimInfo(
            "ZOOM", "delete", "Group", $tmpl["ID"], $tmpl["name"], ""
        );

        Log::info("Zoom delete Group -> " . $tmpl["name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/groups/" . $tmpl[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
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
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error(
                "Delete Group faild status = " . $response["code"] . " " . $info["total_time"]
                . " seconds to send a request to " . $info["url"]
            );
            $scimInfo["message"] = "Delete Group faild : " . $response["message"];
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            // $returnValue = $tmpl[$this->externalIdName];
            $returnValue = true;
            $info = $curl->getInfo();
            Log::info("Delete Group " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
        }
        $curl->close();
        return $returnValue;
    }

    private function getListOfGroupsUserBelongedTo($userAttibutes, $scims = ""): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($userAttibutes["ID"], $scims);
        return $memberOf;
    }

    private function getGroupMemberOfsZOOM($userAttibutes, $table)
    {
        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/users/" . $userAttibutes[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "GET", $curlHeader);

        $result = $curl->execute();
        $returnValue = [];
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            if (array_key_exists("group_ids", $response)) {
                foreach ($response["group_ids"] as $key => $value) {
                    $returnValue[] = $value;
                }
            }
        }
        $curl->close();
        return $returnValue;
    }

    private function addMemberToGroup($item, $groupID)
    {
        Log::info("Zoom add member to Group -> " . $item["first_name"] . " " . $item["last_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $json = '{
            "members": [
                {
                  "id": "(User.externalId)",
                  "email": "(User.mail)"
                }
              ]
        }';
        $json = str_replace("(User.externalId)", $item[$this->externalIdName], $json);
        $json = str_replace("(User.mail)", $item["email"], $json);

        $url = "https://api.zoom.us/v2/groups/" . $groupID . "/members";
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $json);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "http_code_error") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Zoom add member to Group faild status = "
                . $response["code"] . " " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
        }
        else if ($result == "success") {
            $response = json_decode($curl->getResponse(), true);
            $returnValue = $response["ids"];
            $info = $curl->getInfo();
            Log::info("Zoom add member to Group " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        $curl->close();
        return $returnValue;
    }

    private function removeMemberOfGroup($item, $groupID)
    {
        Log::info("Zoom remove member of Group -> " . $item["first_name"] . " " . $item["last_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/groups/" . $groupID . "/members/" . $item[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "DELETE", $curlHeader);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "http_code_error") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Zoom remove member of Group faild status = "
                . $response["code"] . " " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
        }
        else if ($result == "success") {
            $response = json_decode($curl->getResponse(), true);
            $returnValue = $response["ids"];
            $info = $curl->getInfo();
            Log::info("Zoom remove member of Group " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        $curl->close();
        return $returnValue;
    }

    private function getListOfRoleUserBelongedTo($userAttibutes, $scims = ""): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getRoleInExternalID($userAttibutes["ID"], $scims);
        return $memberOf;
    }

    private function getRoleMemberOfsZOOM($userAttibutes, $table)
    {
        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/users/" . $userAttibutes[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "GET", $curlHeader);

        $result = $curl->execute();
        $returnValue = [];
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            if (!empty($response["role_name"])) {
                $returnValue[] = $response["role_id"];
            }
        }
        $curl->close();
        return $returnValue;
    }

    private function assignMemberToRole($item, $roleID)
    {
        Log::info("Zoom add member to Role -> " . $item["first_name"] . " " . $item["last_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $externalIdName = $this->externalIdName;
        $json = '{
            "members": [
                {
                "id": "(User.externalId)"
                }
            ]
        }';
        $json = str_replace("(User.externalId)", $item[$this->externalIdName], $json);

        $url = "https://api.zoom.us/v2/roles/" . $roleID . "/members";
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "POST", $curlHeader, $json);

        $result = $curl->execute();
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "http_code_error") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Zoom add member to Role faild status = "
                . $response["code"] . " " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
            $scimInfo["message"] = $curl->getException()->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            Log::info("Zoom add member to Role " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        $curl->close();
    }

    /* No need to use */
    private function unassignMemberOfRole($item, $roleID)
    {
        Log::info("Zoom remove member of Role -> " . $item["first_name"] . " " . $item["last_name"]);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = "application/json";

        $url = "https://api.zoom.us/v2/roles/" . $roleID . "/members/" . $item[$this->externalIdName];
        $curlHeader = array("Authorization: $auth", "Content-type: $accept", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "DELETE", $curlHeader);

        $result = $curl->execute();
        $returnValue = [];
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "http_code_error") {
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Zoom remove member of Role faild status = "
                . $response["code"] . " " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        else if ($result == "exception") {
            Log::debug($curl->getException()->getMessage());
        }
        else if ($result == "success") {
            $info = $curl->getInfo();
            Log::info("Zoom remove member of Role " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
        }
        $curl->close();
        return $returnValue;
    }
}
