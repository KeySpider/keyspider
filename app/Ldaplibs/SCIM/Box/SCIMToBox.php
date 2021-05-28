<?php

namespace App\Ldaplibs\SCIM\Box;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

use Throwable;
use RuntimeException;

class SCIMToBox
{
    const SCIM_CONFIG = 'SCIM Authentication Configuration';

    protected $setting;

    /**
     * SCIMToBox constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->settingManagement = new SettingsManager();
    }

    private function getAccessToken()
    {
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'token',
            'table' => '',
            'itemId' => '',
            'itemName' => '',
            'message' => '',
        );

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];

        $endPoint = "https://api.box.com/oauth2/token";
        $params = [
            "client_id" => $scimOptions['clientId'],
            "client_secret" => $scimOptions['clientSecret'],
            "grant_type" => "client_credentials",
            "box_subject_type" => "enterprise",
            "box_subject_id" => $scimOptions['enterpriseId']
        ];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $endPoint);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $params);

        try {
            $tuData = curl_exec($tuCurl);
            if (!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info['http_code'] >= 300) {
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    throw new RuntimeException("Failed to get access token");
                    return null;
                }
    
                if ( array_key_exists('access_token', $responce)) {
                    Log::info('Token ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    curl_close($tuCurl);
                    return $responce['access_token'];
                }
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());

            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
    }

    public function createResource($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'create',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => $item['name'],
            'message' => '',
        );

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];
        $url = $scimOptions['url'] . strtolower($resourceType) . 's/';
        $auth = "Bearer " . $this->getAccessToken();
        $accept = $scimOptions['accept'];
        $contentType = $scimOptions['ContentType'];

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
            if (!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);
                
                if ((int)$info['http_code'] >= 300) {
                    Log::error($responce['error_description']);
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                    return null;
                }
    
                if ( array_key_exists('id', $responce)) {
                    Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $this->settingManagement->detailLogger($scimInfo);
                    curl_close($tuCurl);
                    return $responce['id'];
                }
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
    }

    public function updateResource($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'update',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => $item['name'],
            'message' => '',
        );

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];
        $url = $scimOptions['url'] . strtolower($resourceType) . 's/';
        $auth = "Bearer " . $this->getAccessToken();
        $accept = $scimOptions['accept'];
        $contentType = $scimOptions['ContentType'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $item['externalBOXID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $tmpl);

        try {
            $tuData = curl_exec($tuCurl);
            if (!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info['http_code'] >= 300) {
                    Log::error($responce['error_description']);
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                    return null;
                }
                
                if (array_key_exists('id', $responce)) {
                    Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    $this->settingManagement->detailLogger($scimInfo);
                    curl_close($tuCurl);
                    return $item['externalBOXID'];
                }
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
    }

    public function deleteResource($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'delete',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['ID'],
            'itemName' => $item['name'],
            'message' => '',
        );

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];
        $url = $scimOptions['url'] . strtolower($resourceType) . 's/';
        $auth = "Bearer " . $this->getAccessToken();

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $item['externalBOXID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
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

                if ((int)$info['http_code'] >= 300) {
                    Log::error($responce['error_description']);
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                    return null;
                }

                Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                $this->settingManagement->detailLogger($scimInfo);
                curl_close($tuCurl);
                return $item['externalBOXID'];

            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return null;
    }

    public function replaceResource($resourceType, $item)
    {
        $getEncryptedFields = $this->settingManagement->getEncryptedFields();

        $tmp = Config::get('scim-box.createUser');
        if ($resourceType === 'Group') {
            $tmp = Config::get('scim-box.createGroup');
        }

        foreach ($item as $key => $value) {
            if ($key === 'state') {
                $address = sprintf(
                    "%s, %s, %s",
                    $item['state'],
                    $item['city'],
                    $item['streetAddress']
                );
                $tmp = str_replace("(User.joinAddress)", $address, $tmp);
                continue;
            }

            if ($key === 'locked') {
                $isActive = 'active';
                if ($value == '1') $isActive = 'inactive';
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
        $tmp = str_replace('    "status": "(User.DeleteFlag)",', '', $tmp);

        return $tmp;
    }

    public function addMemberToGroups($userAttibutes, $uPN): void
    {
        // Import data role info
        $memberOf = $this->getListOfGroupsUserBelongedTo($userAttibutes, 'BOX');

        // Now stored role info
        $groupIDListOnBOX = $this->getMemberOfBOX($uPN);

        foreach ($memberOf as $groupID) {
            if (!array_key_exists($groupID, $groupIDListOnBOX)) {
                $this->addMemberToGroup($uPN, $groupID);
            }
        }

        foreach ($groupIDListOnBOX as $key => $groupID) {
            if (!in_array($key, $memberOf)) {
                $this->removeMemberOfGroup($uPN, $groupID);
            }
        }
    }

    public function getListOfGroupsUserBelongedTo($userAttibutes, $scims = ''): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getGroupInExternalID($userAttibutes['ID'], $scims);
        return $memberOf;
    }

    public function getMemberOfBOX($uPN)
    {
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'getMemberOfBox',
            'table' => "BOX",
            'itemId' => $uPN,
            'itemName' => '',
            'message' => '',
        );

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];
        $url = $scimOptions['url'] . 'users/';
        $auth = "Bearer " . $this->getAccessToken();
        $accept = $scimOptions['accept'];
        $contentType = $scimOptions['ContentType'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $uPN . '/memberships/');
        curl_setopt(
            $tuCurl,
            CURLOPT_HTTPHEADER,
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept")
        );
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $groupIDList = [];

        try {
            $tuData = curl_exec($tuCurl);
            if (!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info['http_code'] >= 300) {
                    Log::error($responce['error_description']);
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                    return $groupIDList;
                }
                
                if (array_key_exists('total_count', $responce)) {
                    $groupIDList = [];
                    for ($i = 0; $i < $responce['total_count']; $i++) {
                        $groupIDList[$responce['entries'][$i]['group']['id']] = $responce['entries'][$i]['id'];
                    }
                    $this->settingManagement->detailLogger($scimInfo);
                    curl_close($tuCurl);
                    return $groupIDList;
                };
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
    
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
        return $groupIDList;
    }

    private function addMemberToGroup($uPCN, $groupId): void
    {
        // $getEncryptedFields = $this->settingManagement->getEncryptedFields();
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'addMemberToGroup',
            'table' => '',
            'itemId' => $uPCN,
            'itemName' => $groupId,
            'message' => '',
        );

        $tmpl = Config::get('scim-box.addGroup');
        $tmpl = str_replace('(upn)', $uPCN, $tmpl);
        $tmpl = str_replace('(gpn)', $groupId, $tmpl);

        $url = 'https://api.box.com/2.0/group_memberships';

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];
        $auth = "Bearer " . $this->getAccessToken();
        $accept = $scimOptions['accept'];
        $contentType = $scimOptions['ContentType'];

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

            if (!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info['http_code'] >= 300) {
                    Log::error($responce['error_description']);
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                }
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                $this->settingManagement->detailLogger($scimInfo);
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
    }

    public function removeMemberOfGroup($uPCN, $groupId)
    {
        $scimInfo = array(
            'provisoning' => 'BOX',
            'scimMethod' => 'removeMemberOfGroup',
            'table' => 'BOX',
            'itemId' => $uPCN,
            'itemName' => $groupId,
            'message' => '',
        );

        $url = 'https://api.box.com/2.0/group_memberships/' . $groupId . '/';

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true)['BOX Keys'];
        $auth = "Bearer " . $this->getAccessToken();

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
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

                if ((int)$info['http_code'] >= 300) {
                    Log::error($responce['error_description']);
                    $scimInfo['message'] = $responce['error_description'];
                    $this->settingManagement->faildLogger($scimInfo);
                    curl_close($tuCurl);
                }
                Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                $this->settingManagement->detailLogger($scimInfo);
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
                $scimInfo['message'] = 'Curl error: ' . curl_error($tuCurl);
                $this->settingManagement->faildLogger($scimInfo);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            $scimInfo['message'] = $exception->getMessage();
            $this->settingManagement->faildLogger($scimInfo);
        }
        curl_close($tuCurl);
    }
}
