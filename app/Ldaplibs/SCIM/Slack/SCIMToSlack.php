<?php


namespace App\Ldaplibs\SCIM\Slack;

use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MacsiDigital\Zoom\Facades\Zoom;

class SCIMToSlack
{
    const SCIM_CONFIG = 'SCIM Authentication Configuration';

    protected $setting;

    /**
     * SCIMToSlack constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
    }

    public function createResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);

        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $tmpl);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Create faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
    
            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Create faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    public function updateResource($resourceType, $item)
    {
        $tmpl = $this->replaceResource($resourceType, $item);
        $externalID = $item['externalSlackID'];

        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $return_id = '';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        // curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $tmpl);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if (array_key_exists("Errors", $responce)) {
                $curl_status = $responce['Errors']['description'];
                Log::error('Replace faild ststus = ' . $curl_status);
                Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }

            if ( array_key_exists('id', $responce)) {
                $return_id = $responce['id'];
                Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Replace faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            }
            Log::info('Replace ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    public function deleteResource($resourceType, $item)
    {
        $externalID = $item['externalSlackID'];

        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $externalID);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "accept: */*"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        $responce = json_decode($tuData, true);
        $info = curl_getinfo($tuCurl);

        if (empty($responce)) {
            if(!curl_errno($tuCurl)) {
                $info = curl_getinfo($tuCurl);
                Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
            } else {
                Log::error('Curl error: ' . curl_error($tuCurl));
            }
            curl_close($tuCurl);
            return $responce['id'];
        }
        
        if (array_key_exists("Errors", $responce)) {
            $curl_status = $responce['Errors']['description'];
            Log::error('Delete faild ststus = ' . $curl_status);
            Log::error($info['total_time'] . ' seconds to send a request to ' . $info['url']);
            curl_close($tuCurl);
            return null;
        }
    }

    public function replaceResource($resourceType, $item)
    {
        if ($resourceType == 'User') {
            $settingManagement = new SettingsManager();
            $getEncryptedFields = $settingManagement->getEncryptedFields();

            $tmp = Config::get('scim-slack.createUser');
            $isActive = 'true';

            $macnt = explode('@', $item['mail']);
            if (strlen($macnt[0]) > 20) {
                $item['userName'] = substr($macnt[0], -21);
            } else {
                $item['userName'] = $macnt[0];
            }

            foreach ($item as $key => $value) {
                if ($key === 'locked') {
                    if ( $value == '1') $isActive = 'false';
                    $tmp = str_replace("(User.DeleteFlag)", $isActive, $tmp);
                    continue;
                }

                $twColumn = "Slack.$key";
                if (in_array($twColumn, $getEncryptedFields)) {
                    $value = $settingManagement->passwordDecrypt($value);
                }
                $tmp = str_replace("(User.$key)", $value, $tmp);
            }

            return $tmp;
        } else {
            $tmp = Config::get('scim-slack.createGroup');
            $tmp = str_replace("(Organization.DisplayName)", $item['displayName'], $tmp);
            $tmp = str_replace("(Organization.externalID)", $item['externalSlackID'], $tmp);
            return $tmp;
        }
    }
}