<?php

namespace App\Ldaplibs\SCIM\TrustLogin;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use MacsiDigital\Zoom\Facades\Zoom;

class SCIMToTrustLogin
{
    const SCIM_CONFIG = 'SCIM Authentication Configuration';

    protected $setting;
    protected $regExpManagement;

    /**
     * SCIMToTrustLogin constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->regExpManagement = new RegExpsManager();
        $this->settingManagement = new SettingsManager();
    }

    private function requiredItemCheck($scimInfo, $item)
    {
        $rules = [
            'mail' => ['required', 'email:strict'],
            'givenName' => 'required',
            'surname' => 'required',
            // 'department' => 'required',
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

    public function createResource($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'TrustLogin',
            'scimMethod' => 'create',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['Alias'],
            'itemName' => sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        $isContinueProcessing = $this->requiredItemCheck($scimInfo, $item);
        if (!$isContinueProcessing) {
            return null;
        }

        $tmpl = $this->replaceResource($resourceType, $item);

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true) ['TrustLogin Keys'];
        $url = $scimOptions['url'];
        $auth = $scimOptions['authorization'];
        $accept = $scimOptions['accept'];
        $contentType = $scimOptions['ContentType'];
        $return_id = '';

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

                if ((int)$info['http_code'] >= 300) {
                    $scimInfo['message'] = $responce['detail'];
                    $this->settingManagement->faildLogger($scimInfo);
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

                    $scimInfo['message'] = 'Create faild ststus = ' . $curl_status;
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

    public function updateResource($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'TrustLogin',
            'scimMethod' => 'update',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['Alias'],
            'itemName' => sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        $isContinueProcessing = $this->requiredItemCheck($scimInfo, $item);
        if (!$isContinueProcessing) {
            return null;
        }

        $tmpl = $this->replaceResource($resourceType, $item);
        $externalID = $item['externalTLID'];

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true) ['TrustLogin Keys'];
        $url = $scimOptions['url'];
        $auth = $scimOptions['authorization'];
        $accept = $scimOptions['accept'];
        $contentType = $scimOptions['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . '/' . $externalID);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
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
            if(!curl_errno($tuCurl)){
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info['http_code'] >= 300) {
                    $scimInfo['message'] = $responce['detail'];
                    $this->settingManagement->faildLogger($scimInfo);
                    return null;
                }

                if ( array_key_exists('id', $responce)) {
                    $return_id = $responce['id'];
                    Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    curl_close($tuCurl);

                    $this->settingManagement->detailLogger($scimInfo);

                    return $return_id;
                }

                if ( array_key_exists('status', $responce)) {
                    $curl_status = $responce['status'];
                    Log::error($responce);
                    Log::error('Replace faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                    curl_close($tuCurl);
                    return null;
                }
                Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
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

    public function deleteResource($resourceType, $item)
    {
        $scimInfo = array(
            'provisoning' => 'TrustLogin',
            'scimMethod' => 'delete',
            'table' => ucfirst(strtolower($resourceType)),
            'itemId' => $item['Alias'],
            'itemName' => sprintf("%s %s", $item['surname'], $item['givenName']),
            'message' => '',
        );

        $externalID = $item['externalTLID'];

        $scimOptions = parse_ini_file(storage_path('ini_configs/GeneralSettings.ini'), true) ['TrustLogin Keys'];
        $url = $scimOptions['url'];
        $auth = $scimOptions['authorization'];

        try {

            $tuCurl = curl_init();
            curl_setopt($tuCurl, CURLOPT_URL, $url . '/'. $externalID);
            curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt(
                $tuCurl,
                CURLOPT_HTTPHEADER,
                array("Authorization: $auth", "accept: */*")
            );
            curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

            $tuData = curl_exec($tuCurl);
            if(!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                $responce = json_decode($tuData, true);

                if ((int)$info['http_code'] >= 300) {
                    $scimInfo['message'] = $responce['detail'];
                    $this->settingManagement->faildLogger($scimInfo);
                    return null;
                }
                
                Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                $this->settingManagement->detailLogger($scimInfo);

                return $externalID;
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

    public function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmp = Config::get('scim-trustlogin.createUser');

        foreach ($item as $key => $value) {
            if ($key === 'locked') {
                $isActive = 'true';
                if ( $value == '1') $isActive = 'false';
                $als = sprintf("    \"active\":%s,", $isActive);
                $tmp = str_replace("accountLockStatus", $als, $tmp);
                continue;
            }

            // if (strpos($value, 'ELOQ;') !== false) {
            //     if (empty($item['OrganizationID1'])) {
            //         $tmp = str_replace("(User.Organization.displayName)", '', $tmp);
            //         continue;
            //     }
            //     // Get Eloquent string
            //     preg_match('/ELOQ;(.*)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
            //     $line = $this->regExpManagement->eloquentItem($item['ID'], $matches[1][0]);

            //     $tmp = str_replace("(User.Organization.displayName)", $line, $tmp);
            //     continue;
            // }

            $twColumn = "User.$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(User.$key)", $value, $tmp);
        }
        // if not yet replace als code, replace to null
        $tmp = str_replace("accountLockStatus\n", '', $tmp);

        return $tmp;
    }
}