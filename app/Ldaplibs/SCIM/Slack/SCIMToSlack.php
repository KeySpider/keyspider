<?php

namespace App\Ldaplibs\SCIM\Slack;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
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
        $scimInfo = array(
            'provisoning' => 'Slack',
            'scimMethod' => 'create',
            'table' => $camelTableName,
            'itemId' => $item['ID'],
            'message' => '',
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
        $return_id = "";

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $tmpl);

        try {
            $tuData = curl_exec($tuCurl);
            if(!curl_errno($tuCurl)){
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info["http_code"] >= 300) {
                    $curl_status = $responce["Errors"]["description"];
                    Log::error("Create faild ststus = " . $curl_status);
                    Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
                    curl_close($tuCurl);

                    $scimInfo["message"] = $curl_status;
                    $this->settingManagement->faildLogger($scimInfo);
                    return null;
                }

                if (array_key_exists("id", $responce)) {
                    $return_id = $responce["id"];
                    Log::info("Create " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
                    curl_close($tuCurl);

                    $this->settingManagement->detailLogger($scimInfo);

                    return $return_id;
                }

            } else {
                Log::error("Curl error: " . curl_error($tuCurl));

                $scimInfo["message"] = curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
            curl_close($tuCurl);
            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    public function updateResource($resourceType, $item)
    {
        $camelTableName = ucfirst(strtolower($resourceType));
        $scimInfo = array(
            'provisoning' => 'Slack',
            'scimMethod' => 'update',
            'table' => $camelTableName,
            'itemId' => $item['ID'],
            'message' => '',
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
        $externalId = $item[$this->externalIdName];

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Slack Keys"];
        $url = $scimOptions["url"] . $resourceType . "s/";
        $auth = $scimOptions["authorization"];
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];
        $return_id = "";

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalId);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $tmpl);

        try {
            $tuData = curl_exec($tuCurl);
            if(!curl_errno($tuCurl)){
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info["http_code"] >= 300) {
                    $curl_status = $responce["Errors"]["description"];
                    Log::error("Replace faild ststus = " . $curl_status);
                    Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
                    curl_close($tuCurl);

                    $scimInfo["message"] = $curl_status;
                    $this->settingManagement->faildLogger($scimInfo);
                    return null;
                }

                if (array_key_exists("id", $responce)) {
                    $return_id = $responce["id"];
                    Log::info("Replace " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
                    curl_close($tuCurl);

                    $this->settingManagement->detailLogger($scimInfo);

                    return $return_id;
                }
            } else {
                Log::error("Curl error: " . curl_error($tuCurl));

                $scimInfo["message"] = curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
            curl_close($tuCurl);
            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

    public function deleteResource($resourceType, $item)
    {
        $camelTableName = ucfirst(strtolower($resourceType));
        $scimInfo = array(
            'provisoning' => 'Slack',
            'scimMethod' => 'delete',
            'table' => $camelTableName,
            'itemId' => $item['ID'],
            'message' => '',
        );

        if ($camelTableName == 'User') {
            $scimInfo['itemName'] = $item['userName'];
        } else {
            $scimInfo['itemName'] = $item['displayName'];
        }

        $externalId = $item[$this->externalIdName];

        $scimOptions = parse_ini_file(storage_path("ini_configs/GeneralSettings.ini"), true) ["Slack Keys"];
        $url = $scimOptions["url"] . $resourceType . "s/";
        $auth = $scimOptions["authorization"];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalId);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "accept: */*")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        try {
            $tuData = curl_exec($tuCurl);
            if (!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info["http_code"] >= 300) {
                    $curl_status = $responce["Errors"]["description"];
                    Log::error("Delete faild ststus = " . $curl_status);
                    Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
                    curl_close($tuCurl);

                    $scimInfo["message"] = $curl_status;
                    $this->settingManagement->faildLogger($scimInfo);
                    return null;
                }

                Log::info("Delete " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
                curl_close($tuCurl);

                $this->settingManagement->detailLogger($scimInfo);

                if ($camelTableName == "User") {
                    return $externalId;
                } else {
                    return true;
                }
            }
            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo["message"] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }

    }

    public function passwordResource($resourceType, $item, $externalId)
    {
        return;
    }

    public function userGroup($resourceType, $item, $externalId)
    {
        if (strtolower($resourceType) == "user") {
            $memberOf = $this->getListOfGroupsUserBelongedTo($item["ID"], "Slack");
            foreach ($memberOf as $groupID) {
                $addMemberResult = $this->addMemberToGroups($externalId, $groupID, "0");
                echo "\nAdd member to group result:\n";
                var_dump($addMemberResult);
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
            $tmp = str_replace("(Organization.DisplayName)", $item["displayName"], $tmp);
        }
        $pattern = '/"\((.*)\)"/';
        $nullable = preg_replace($pattern, '""', $tmp);

        return $nullable;
    }

    private function getListOfGroupsUserBelongedTo($id, $scims = ""): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getOrganizationInExternalID($id, $scims);
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
        $url = $scimOptions["url"] . "Groups/";
        $auth = $scimOptions["authorization"];
        $accept = $scimOptions["accept"];
        $contentType = $scimOptions["ContentType"];
        $return_id = "";

        $tmpl = Config::get("scim-slack.patchGroup");
        if ($delFlag == "1") {
            $tmpl = Config::get("scim-slack.removeGroup");
        }
        $tmpl = str_replace("(memberOfSlack)", $memberId, $tmpl);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $groupId);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $tmpl);

        $tuData = curl_exec($tuCurl);
        $responce = json_decode($tuData, true);
        $info = curl_getinfo($tuCurl);

        if (empty($responce)) {
            if(!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                Log::info("add member " . $info["total_time"] . " seconds to send a request to " . $info["url"]);
            } else {
                Log::error("Curl error: " . curl_error($tuCurl));
            }
            curl_close($tuCurl);
            return $responce["id"];
        }
        
        if (array_key_exists("Errors", $responce)) {
            $curl_status = $responce["Errors"]["description"];
            Log::error("add member faild ststus = " . $curl_status);
            Log::error($info["total_time"] . " seconds to send a request to " . $info["url"]);
            curl_close($tuCurl);
            return null;
        }
    }

    private function removeMemberToGroup($id, $externalId)
    {
        $table = "UserToOrganization";
        $queries = DB::table($table)
                    ->select("Organization_ID")
                    ->where("User_ID", $id)
                    ->where("DeleteFlag", "1")->get();

        foreach ($queries as $key => $value) {

            $table = "Organization";
            $slackQueries = DB::table($table)
                        ->select($this->externalIdName)
                        ->where("ID", $value->Organization_ID)
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

