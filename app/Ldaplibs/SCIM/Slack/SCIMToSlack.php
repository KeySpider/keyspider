<?php

namespace App\Ldaplibs\SCIM\Slack;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\SCIM\Curl;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SCIMToSlack
{
    protected $setting;
    protected $settingManagement;

    private $externalIdName;

    /**
     * SCIMToSlack constructor.
     */
    public function __construct()
    {
    }

    public function initialize($setting, $externalIdName)
    {
        $this->setting = $setting;
        $this->externalIdName = $externalIdName;
        $this->settingManagement = new SettingsManager();
    }

    public function getServiceName() {
        return "Slack";
    }

    public function createResource($resourceType, $item)
    {
        $camelTableName = ucfirst(strtolower($resourceType));
        $scimInfo = $this->settingManagement->makeScimInfo(
            "Slack", "create", $camelTableName, $item['ID'], "", ""
        );

        if ($camelTableName == 'User') {
            $scimInfo['itemName'] = $item['userName'];
            $isContinueProcessing = $this->requiredItemCheck($scimInfo, $item);
            if (!$isContinueProcessing) {
                return null;
            }
        } else {
            $scimInfo['itemName'] = $item['displayName'];
        }

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Slack Keys"];
        $url = $scimOptions["url"] . $resourceType . "s/";
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
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Create faild ststus = " . $responce["Errors"]["description"]);
            Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
            $scimInfo["message"] = $response["Errors"]["description"];
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
        $camelTableName = ucfirst(strtolower($resourceType));
        $scimInfo = $this->settingManagement->makeScimInfo(
            "Slack", "update", $camelTableName, $item['ID'], "", ""
        );

        if ($camelTableName == 'User') {
            $scimInfo['itemName'] = $item['userName'];
            $isContinueProcessing = $this->requiredItemCheck($scimInfo, $item);
            if (!$isContinueProcessing) {
                return null;
            }
        } else {
            $scimInfo['itemName'] = $item['displayName'];
        }

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Slack Keys"];
        $url = $scimOptions["url"] . $resourceType . "s/" . $item[$this->externalIdName];
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
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Replace faild ststus = " . $responce["Errors"]["description"]);
            Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
            $scimInfo["message"] = $response["Errors"]["description"];
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
            Log::info("Replace " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
            $returnValue = $item[$this->externalIdName];
        }
        $curl->close();
        return $returnValue;
    }

    public function deleteResource($resourceType, $item)
    {
        $camelTableName = ucfirst(strtolower($resourceType));
        $scimInfo = $this->settingManagement->makeScimInfo(
            "Slack", "delete", $camelTableName, $item['ID'], "", ""
        );

        if ($camelTableName == 'User') {
            $scimInfo['itemName'] = $item['userName'];
        } else {
            $scimInfo['itemName'] = $item['displayName'];
        }

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Slack Keys"];
        $url = $scimOptions["url"] . $resourceType . "s/" . $item[$this->externalIdName];
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
            $info = $curl->getInfo();
            $response = json_decode($curl->getResponse(), true);
            Log::error("Delete faild ststus = " . $responce["Errors"]["description"]);
            Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
            $scimInfo["message"] = $response["Errors"]["description"];
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
            Log::info("Delete " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $this->settingManagement->detailLogger($scimInfo);
            if ($camelTableName == "User") {
                $returnValue = $item[$this->externalIdName];
            } else {
                $returnValue = true;
            }
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
        if (strtolower($resourceType) == "user") {
            $memberOf = $this->getListOfGroupsUserBelongedTo($item["ID"], "Slack");
            foreach ($memberOf as $groupID) {
                $addMemberResult = $this->addMemberToGroups($externalId, $groupID, "0");
            }
            $addMemberResult = $this->removeMemberToGroup($item["ID"], $externalId);
        }
    }

    public function userRole($resourceType, $item, $externalId)
    {
        return;
    }

    private function replaceResource($resourceType, $item)
    {
        if ($resourceType == "User") {
            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $tmp = Config::get("scim-slack.createUser");
            $isActive = "true";

            $macnt = explode("@", $item["mail"]);
            if (strlen($macnt[0]) > 20) {
                $item["userName"] = substr($macnt[0], -21);
            } else {
                $item["userName"] = $macnt[0];
            }

            foreach ($item as $key => $value) {
                if ($key === "locked") {
                    if ( $value == "1") $isActive = "false";
                    $als = sprintf("       \"active\":%s,", $isActive);
                    $tmp = str_replace("accountLockStatus", $als, $tmp);
                    continue;
                }

                $twColumn = "Slack.$key";
                if (in_array($twColumn, $getEncryptedFields)) {
                    $value = $settingManagement->passwordDecrypt($value);
                }
                $tmp = str_replace("(User.$key)", $value, $tmp);
            }

            // if not yet replace als code, replace to null
            $tmp = str_replace("accountLockStatus\n", '', $tmp);

        } else {
            $tmp = Config::get("scim-slack.createGroup");
            $tmp = str_replace("(Group.DisplayName)", $item["displayName"], $tmp);
        }
        $pattern = '/"\((.*)\)"/';
        $nullable = preg_replace($pattern, '""', $tmp);

        return $nullable;
    }

    private function getListOfGroupsUserBelongedTo($id, $scims = ""): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($id, $scims);
        return $memberOf;
    }

    private function addMemberToGroups($memberId, $groupId, $delFlag)
    {
        try {
            return $this->addMemberToGroup($memberId, $groupId, $delFlag);
        } catch (\Exception $exception) {
            return [];
        }
    }

    private function addMemberToGroup($memberId, $groupId, $delFlag)
    {
        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Slack Keys"];
        $url = $scimOptions["url"] . "Groups/" . $groupId;
        $auth = $scimOptions["authorization"];
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];

        $tmpl = Config::get("scim-slack.patchGroup");
        if ($delFlag == "1") {
            $tmpl = Config::get("scim-slack.removeGroup");
        }
        $tmpl = str_replace("(memberOfSlack)", $memberId, $tmpl);

        $curlHeader = array("Authorization: $auth", "Content-type: $contentType", "accept: $accept");
        $curl = new Curl();
        $curl->init($url, "PATCH", $curlHeader, $tmpl);

        $result = $curl->execute();
        $returnValue = null;
        if ($result == "curl_error") {
            Log::error("Curl error: " . $curl->getError());
        }
        else if ($result == "http_code_error") {
            if (array_key_exists("Errors", $responce)) {
                $info = $curl->getInfo();
                $response = json_decode($curl->getResponse(), true);
                Log::error("add member faild ststus = " . $response["Errors"]["description"]);
                Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
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
            Log::info("add member " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            $returnValue = $response["id"];
        }
        $curl->close();
        return $returnValue;
    }

    private function removeMemberToGroup($id, $externalId)
    {
        $table = "UserToGroup";
        $queries = DB::table($table)
                    ->select("Group_ID")
                    ->where("User_ID", $id)
                    ->where("DeleteFlag", "1")->get();

        foreach ($queries as $key => $value) {

            $table = "Group";
            $slackQueries = DB::table($table)
                        ->select($this->externalIdName)
                        ->where("ID", $value->Group_ID)
                        ->get();

            $externalIdName = $this->externalIdName;
            foreach ($slackQueries as $key => $value) {
                $addMemberResult = $this->addMemberToGroup($externalId, $value->$externalIdName, "1");
            }
        }
    }

    private function requiredItemCheck($scimInfo, $item)
    {
        $rules = [
            "userName" => "required",
            "mail" => ["required", "email:strict"],
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

