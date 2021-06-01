<?php

namespace App\Ldaplibs\SCIM\Zoom;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;
use MacsiDigital\Zoom\Facades\Zoom;

class SCIMToZoom
{
    protected $setting;

    private $externalIdName;
    private $token;

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
        $this->token = $this->makeToken();
    }

    public function getServiceName() {
        return "ZOOM";
    }

    public function createResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);
        $externalID = null;
        if ($resourceType == 'User') {
            $externalID = $this->createUser($tmpl);
        } elseif ($resourceType == 'Role') {
            $externalID = $this->createRole($tmpl);
        } elseif ($resourceType == 'Group') {
            $externalID = $this->createGroup($tmpl);
        }
        return $externalID;
    }

    public function updateResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);

        $externalID = null;
        if ($resourceType == 'User') {
            $externalID = $this->updateUser($tmpl);
        } elseif ($resourceType == 'Role') {
            $externalID = $this->updateRole($tmpl);
        } elseif ($resourceType == 'Group') {
            $externalID = $this->updateGroup($tmpl);
        }
        return $externalID;
    }

    public function deleteResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);

        $externalID = null;
        if ($resourceType == 'User') {
            $externalID = $this->deleteUser($resourceType, $item);
        } elseif ($resourceType == 'Role') {
            $externalID = $this->deleteRole($resourceType, $item);
        } elseif ($resourceType == 'Group') {
            $externalID = $this->deleteGroup($resourceType, $item);
        }
        // return $externalID;
        return true;
    }

    public function passwordResource($resourceType, $item, $externalId)
    {
        return;
    }

    public function userGroup($resourceType, $item, $externalId)
    {
        if ($resourceType == "User") {
            // Import data group info
            $memberOf = $this->getListOfGroupsUserBelongedTo($item, 'ZOOM');

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
        }
    }

    public function userRole($resourceType, $item, $externalId)
    {
        if ($resourceType == "User") {
            // Import data Role info
            $memberOf = $this->getListOfRoleUserBelongedTo($item, 'ZOOM');

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
        $signature = $this->urlsafe_base64_encode(hash_hmac('sha256', "$header.$payload", $clientSecret, TRUE));
        $token = "$header.$payload.$signature";

        Log::debug($token);
        return $token;
    }

    private function urlsafe_base64_encode($str)
    {
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
    }

    private function createUser($tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'create',
            'table' => 'User',
            'itemId' => $tmpl['ID'],
            'itemName' => sprintf("%s %s", $tmpl['last_name'], $tmpl['first_name']),
            'message' => '',
        );

        Log::info('Zoom Create User -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);

        try {
            $user = Zoom::user()->create([
                'first_name' => $tmpl['first_name'],
                'last_name' => $tmpl['last_name'],
                'email' => $tmpl['email'],
                'phone_country' => 'JP',
                'phone_number' => $tmpl['phone_number'],
                'job_title' => $tmpl['job_title'],
                'type' => 1,
                'timezone' => 'Asia/Tokyo',
                'verified' => 0,
                'language' => 'jp-JP'
            ]);

            if ( array_key_exists('locked', $tmpl) ) {
                $userStatus = 'activate';
                if ($tmpl['locked'] == '1') {
                    $userStatus = 'deactivate';
                }
                $user->updateStatus($userStatus); // Allowed values active, deactivate
            }
            $this->settingManagement->detailLogger($scimInfo);
            return $user->id;
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
        }
    }

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/users/');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);
            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Create User faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Create User ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function createRole($tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'create',
            'table' => 'Role',
            'itemId' => $tmpl['ID'],
            'itemName' => $tmpl['name'],
            'message' => '',
        );

        Log::info('Zoom Create Role -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $json = '{
                "name": "(Role.name)",
                "privileges": [
                    "User:Read",
                    "User:Edit",
                    "Group:Read",
                    "Group:Edit"
                  ]    
        }';
        $json = str_replace("(Role.name)", $tmpl['name'], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        try {

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                    $scimInfo['message'] = 'Create Role faild : ' . $responce['message'];
                    $this->settingManagement->faildLogger($scimInfo);
                return null;
                }

                if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Create Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                    $this->settingManagement->detailLogger($scimInfo);

                return $return_id;
            };

                if ( array_key_exists('status', $responce)) {
                    $curl_status = $responce['status'];
                    Log::error($info);
                    Log::error($responce);
                    Log::error('Create Role faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);

                    $scimInfo['message'] = 'Create Role faild ststus = ' . $curl_status;
                    $this->settingManagement->faildLogger($scimInfo);

                    curl_close($tuCurl);
                    return null;
                }
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = sprintf("Curl error: %s", curl_error($tuCurl));
                $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }
    }

    private function createGroup($tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'create',
            'table' => 'Group',
            'itemId' => $tmpl['ID'],
            'itemName' => $tmpl['name'],
            'message' => '',
        );

        Log::info('Zoom Create Group -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $json = '{
                "name": "(Group.displayName)"
        }';
        $json = str_replace("(Group.displayName)", $tmpl['name'], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        try {

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                    $scimInfo['message'] = 'Create Group faild ststus = ' . $responce['message'];
                    $this->settingManagement->faildLogger($scimInfo);
                curl_close($tuCurl);
                return null;
                }

                if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                    Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $this->settingManagement->detailLogger($scimInfo);
                curl_close($tuCurl);
                return $return_id;
            };

                if ( array_key_exists('status', $responce)) {
                    $curl_status = $responce['status'];
                    Log::error($info);
                    Log::error($responce);
                    Log::error('Create faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);

                    $scimInfo['message'] = 'Create Group faild : ' . $curl_status;
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                    return null;
                }
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }

    }

    public function updateResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);

        $externalID = null;
        if ($resourceType == 'User') {
            $externalID = $this->updateUser($tmpl);
        } elseif ($resourceType == 'Role') {
            $externalID = $this->updateRole($tmpl);
        } elseif ($resourceType == 'Group') {
            $externalID = $this->updateGroup($tmpl);
        }
        return $externalID;
    }

    private function updateUser($tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'update',
            'table' => 'User',
            'itemId' => $tmpl['ID'],
            'itemName' => sprintf("%s %s", $tmpl['last_name'], $tmpl['first_name']),
            'message' => '',
        );

        Log::info('Zoom Update User -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $json = '{
            "first_name": "(User.first_name)",
            "last_name":  "(User.last_name)",
            "phone_number": "(User.phone_number)",
            "job_title" : "(User.job_title)"
        }';
        $json = str_replace("(User.first_name)", $tmpl['first_name'], $json);
        $json = str_replace("(User.last_name)", $tmpl['last_name'], $json);
        $json = str_replace("(User.phone_number)", $tmpl['phone_number'], $json);
        $json = str_replace("(User.job_title)", $tmpl['job_title'], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/users/' . $tmpl[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        try {
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Update User faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);

                    $scimInfo['message'] = 'Update User faild : ' . $responce['message'];
                    $this->settingManagement->faildLogger($scimInfo);
    
                curl_close($tuCurl);
                return null;
            } else {
    
                    if ( array_key_exists('locked', $tmpl) ) {
                        $userStatus = 'activate';
                        if ($tmpl['locked'] == '1') {
                            $userStatus = 'deactivate';
                        }
                        $user = Zoom::user()->find($tmpl['externalZOOMID']);
                        $user->updateStatus($userStatus); // Allowed values active, deactivate
                    }

                    $this->settingManagement->detailLogger($scimInfo);
        
                Log::info('Update User ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $tmpl[$this->externalIdName];
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            curl_close($tuCurl);
            return null;
    }

        curl_close($tuCurl);
        return null;
    }

    private function updateRole($tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'update',
            'table' => 'Role',
            'itemId' => $tmpl['ID'],
            'itemName' => $tmpl['name'],
            'message' => '',
        );

        Log::info('Zoom Update Role -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $json = '{
                "name": "(Role.name)",
                "privileges": [
                    "User:Read",
                    "User:Edit",
                    "Group:Read",
                    "Group:Edit"
                  ]    
        }';
        $json = str_replace("(Role.name)", $tmpl['name'], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/' . $tmpl[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        try {
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Replace Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $scimInfo['message'] = 'Replace Role faild : ' . $responce['message'];
                    $this->settingManagement->faildLogger($scimInfo);
   
                curl_close($tuCurl);
                return null;
            } else {
                Log::info('Replace Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);

                    $this->settingManagement->detailLogger($scimInfo);

                curl_close($tuCurl);
                    return $tmpl['externalZOOMID'];
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
        }
            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        curl_close($tuCurl);
        return null;
    }
        curl_close($tuCurl);
        return null;
    }

    private function updateGroup($tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'update',
            'table' => 'Group',
            'itemId' => $tmpl['ID'],
            'itemName' => $tmpl['name'],
            'message' => '',
        );

        Log::info('Zoom Update Role -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $json = '{
                "name": "(Group.disylayName)"
        }';
        $json = str_replace("(Group.disylayName)", $tmpl['name'], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/' . $tmpl[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        try {
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Replace Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);

                    $scimInfo['message'] = 'Replace Group faild : ' . $responce['message'];
                    $this->settingManagement->faildLogger($scimInfo);
        
                curl_close($tuCurl);
                return null;
            } else {
                Log::info('Replace Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $this->settingManagement->detailLogger($scimInfo);
                curl_close($tuCurl);
                    return $tmpl['externalZOOMID'];
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }
    }

    private function deleteUser($resourceType, $tmpl)
    {
        Log::info('Zoom Delete -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/users/' . $tmpl[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Delete User faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Delete User ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }

    private function deleteUser($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'delete',
            'table' => 'User',
            'itemId' => $item['ID'],
            'itemName' => sprintf("%s %s", $item['last_name'], $item['first_name']),
            'message' => '',
        );

        try {
            $user = Zoom::user()->find($item['email']);
            $ext_id = $user->id;
            $user->delete();
            Log::info('Zoom Delete -> ' . $item['last_name'] . ' ' . $item['first_name']);
            $this->settingManagement->detailLogger($scimInfo);
            return $ext_id;    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }
    }

    private function deleteRole($resourceType, $tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'delete',
            'table' => 'Role',
            'itemId' => $tmpl['ID'],
            'itemName' => $tmpl['name'],
            'message' => '',
        );

        Log::info('Zoom delete Role -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/' . $tmpl[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        try {
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Delete Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);

                    $scimInfo['message'] = 'Delete Role faild : ' . $responce['message'];
                    $this->settingManagement->faildLogger($scimInfo);
        
                curl_close($tuCurl);
                return null;
            } else {
                Log::info('Delete Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $this->settingManagement->detailLogger($scimInfo);
                curl_close($tuCurl);
                    return $tmpl['externalZOOMID'];
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
            return null;
    }

    }

    private function deleteGroup($resourceType, $tmpl)
    {
        $scimInfo = array(
            'provisoning' => 'ZOOM',
            'scimMethod' => 'delete',
            'table' => 'Group',
            'itemId' => $tmpl['ID'],
            'itemName' => $tmpl['name'],
            'message' => '',
        );

        Log::info('Zoom delete Group -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/' . $tmpl[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        try {
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Delete Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $scimInfo['message'] = 'Delete Group faild : ' . $responce['message'];;
                    $this->settingManagement->faildLogger($scimInfo);
                curl_close($tuCurl);
                return null;
            } else {
                Log::info('Delete Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $this->settingManagement->detailLogger($scimInfo);
                curl_close($tuCurl);
                    return $tmpl['externalZOOMID'];
            }
            Log::info('Delete Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
        }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        curl_close($tuCurl);
        return null;
    }
        curl_close($tuCurl);
        return null;
    }

    private function getListOfGroupsUserBelongedTo($userAttibutes, $scims = ''): array
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

    public function addGroupMemebers($table, $item, $ext_id)
    {
        try {
            // Import data group info
            $memberOf = $this->getListOfGroupsUserBelongedTo($item, 'ZOOM');

            // Now store data group info
            $groupIDListOnZOOM = $this->getGroupMemberOfsZOOM($item, $table);

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

    public function addRoleMemebers($table, $item, $ext_id)
    {
        // Import data Role info
        $memberOf = $this->getListOfRoleUserBelongedTo($item, 'ZOOM');

        // Now store data group info
        $RoleIDOnZOOM = $this->getRoleMemberOfsZOOM($item, $table);

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

    public function getListOfGroupsUserBelongedTo($userAttibutes, $scims = ''): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($userAttibutes['ID'], $scims);
        return $memberOf;
    }

    private function getGroupMemberOfsZOOM($userAttibutes, $table)
    {
        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/users/' . $userAttibutes[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $groupIDList = [];
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("group_ids", $responce)) {
                foreach ($responce['group_ids'] as $key => $value) {
                    $groupIDList[] = $value;
                }
                curl_close($tuCurl);
                return $groupIDList;
            };
        } else {
            Log::error("Curl error: " . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return $groupIDList;
    }

    private function addMemberToGroup($item, $groupID)
    {
        Log::info('Zoom add member to Group -> ' . $item['first_name'] . ' ' . $item['last_name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $externalIdName = $this->externalIdName;
        $json = '{
            "members": [
                {
                  "id": "(User.externalId)",
                  "email": "(User.mail)"
                }
              ]
        }';
        $json = str_replace("(User.externalId)", $item[$this->externalIdName], $json);
        $json = str_replace("(User.mail)", $item['email'], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/' . $groupID . '/members');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Zoom add member to Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['ids'];
                Log::info('Zoom add member to Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function removeMemberOfGroup($item, $groupID)
    {
        Log::info('Zoom remove member of Group -> ' . $item['first_name'] . ' ' . $item['last_name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt(
            $tuCurl,
            CURLOPT_URL,
            'https://api.zoom.us/v2/groups/' . $groupID . '/members/' . $item[$this->externalIdName]
        );
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Zoom remove member of Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['ids'];
                Log::info('Zoom remove member of Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function getListOfRoleUserBelongedTo($userAttibutes, $scims = ''): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getRoleInExternalID($userAttibutes['ID'], $scims);
        return $memberOf;
    }

    private function getRoleMemberOfsZOOM($userAttibutes, $table)
    {
        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/users/' . $userAttibutes[$this->externalIdName]);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $groupIDList = [];
        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (!empty($responce['role_name'])) {
                $groupIDList[] = $responce['role_id'];
                curl_close($tuCurl);
                return $groupIDList;
            };
        } else {
            Log::error("Curl error: " . curl_error($tuCurl));
        }
        curl_close($tuCurl);

        return $groupIDList;
    }

    private function assignMemberToRole($item, $roleID)
    {
        Log::info('Zoom add member to Role -> ' . $item['first_name'] . ' ' . $item['last_name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $externalIdName = $this->externalIdName;
        $json = '{
            "members": [
                {
                "id": "(User.externalId)"
                }
            ]
        }';
        $json = str_replace("(User.externalId)", $item[$this->externalIdName], $json);

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/' . $roleID . '/members');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Zoom add member to Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
            } else {
                Log::info('Zoom add member to Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
            curl_close($tuCurl);
        }
    }

    /* No need to use */
    private function unassignMemberOfRole($item, $roleID)
    {
        Log::info('Zoom remove member of Role -> ' . $item['first_name'] . ' ' . $item['last_name']);

        $auth = sprintf("Bearer %s", $this->token);
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt(
            $tuCurl,
            CURLOPT_URL,
            'https://api.zoom.us/v2/roles/' . $roleID . '/members/' . $item[$this->externalIdName]
        );
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Zoom remove member of Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
            } else {
                Log::info('Zoom remove member of Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
            curl_close($tuCurl);
        }
    }
}
