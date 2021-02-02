<?php


namespace App\Ldaplibs\SCIM\Box;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MacsiDigital\Zoom\Facades\Zoom;

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

            if ($info['http_code'] >= 300) {
                Log::error('Curl error: ' . $info['http_code']);
                return null;
            }

            $responce = json_decode($tuData, true);

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
        $externalID = $item['externalBOXID'];

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
        $externalID = $item['externalBOXID'];

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
        if(!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
    }

    public function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmp = Config::get('scim-box.createUser');
        if ($resourceType === 'Group') {
            $tmp = Config::get('scim-box.createGroup');
        }

        $isActive = 'active';
        foreach ($item as $key => $value) {
            if ($key === 'state') {
                $address = sprintf("%s, %s, %s", 
                    $item['state'], $item['city'], $item['streetAddress']);
                $tmp = str_replace("(User.joinAddress)", $address, $tmp);
                continue;

            }

            if ($key === 'locked') {
                if ( $value == '1') $isActive = 'inactive';
                $tmp = str_replace("(User.DeleteFlag)", $isActive, $tmp);
                continue;
            }

            $twColumn = $resourceType . ".$key";
            if (in_array($twColumn, $getEncryptedFields)) {
                $value = $settingManagement->passwordDecrypt($value);
            }
            $tmp = str_replace("(" . $resourceType . ".$key)", $value, $tmp);
        }
        return $tmp;
    }

    public function addMemberToGroups($userAttibutes, $uPN): void
    {
        // Import data role info
        $memberOf = $this->getListOfGroupsUserBelongedTo($userAttibutes, 'BOX');

        // Now stored role info
        $groupIDListOnAD = $this->getMemberOfsBOX($uPN);

        foreach ($memberOf as $groupID) {
            if(!array_key_exists($groupID, $groupIDListOnAD)){
                $this->addMemberToGroup($uPN, $groupID);
            }
        }

        foreach ($groupIDListOnAD as $key => $groupID) {
            if(!in_array($key, $memberOf)){
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

    public function getMemberOfsBOX($uPN)
    {
        $setting = $this->setting;

        $url = $setting[self::SCIM_CONFIG]['url'];
        $auth = $setting[self::SCIM_CONFIG]['authorization'];
        $contentType = $setting[self::SCIM_CONFIG]['ContentType'];
        $accept = $setting[self::SCIM_CONFIG]['accept'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . $uPN . '/memberships/');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $contentType", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $groupIDList = [];

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ( array_key_exists('total_count', $responce)) {
                $groupIDList = [];
                for($i = 0; $i < $responce['total_count']; $i++){
                    $groupIDList[$responce['entries'][$i]['group']['id']] = $responce['entries'][$i]['id'];
                }
                curl_close($tuCurl);
                return $groupIDList;
            };
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return $groupIDList;
    }

    private function addMemberToGroup($uPCN, $groupId): void
    {
        $setting = $this->setting;

        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        $tmpl = Config::get('scim-box.addGroup');
        $tmpl = str_replace('(upn)', $uPCN, $tmpl);
        $tmpl = str_replace('(gpn)', $groupId, $tmpl);
            
        $url = 'https://api.box.com/2.0/group_memberships';
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

            if ($info['http_code'] >= 300) {
                Log::error('Curl error: ' . $info['http_code']);
            }

            $responce = json_decode($tuData, true);

            if ( array_key_exists('status', $responce)) {
                $curl_status = $responce['status'];
                Log::error('Create faild ststus = ' . $curl_status . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
            }
            Log::info('Create ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
    }

    public function removeMemberOfGroup($uPCN, $groupId){
        $setting = $this->setting;

        $url = 'https://api.box.com/2.0/group_memberships/' . $groupId .'/';
        $auth = $setting[self::SCIM_CONFIG]['authorization'];

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "accept: */*"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            Log::info('Delete ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
    }
}