<?php


namespace App\Ldaplibs\SCIM\Zoom;

use App\Ldaplibs\RegExpsManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;
use MacsiDigital\Zoom\Facades\Zoom;

class SCIMToZoom
{
    protected $setting;

    /**
     * SCIMToZoom constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
    }

    function urlsafe_base64_encode($str){
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
    }

    public function makeToken()
    {
        $zoom_api_key = env('ZOOM_CLIENT_KEY');
        $zoom_api_secret = env('ZOOM_CLIENT_SECRET');
         
        $expiration = time() + (60 * 60 * 24); // Token expiration date(SEC)
         
        $header = $this->urlsafe_base64_encode('{"alg":"HS256","typ":"JWT"}');
        $payload = $this->urlsafe_base64_encode('{"iss":"' . $zoom_api_key . '","exp":' . $expiration . '}');
        $signature = $this->urlsafe_base64_encode(hash_hmac('sha256', "$header.$payload", $zoom_api_secret , TRUE));
        $token = "$header.$payload.$signature";

        Log::debug($token);
        return $token;
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

    private function createUser($tmpl)
    {
        Log::info('Zoom Create User -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);
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
            'language' => 'jp-JP',
            'status' => 'active'
        ]);
        return $user->id;
    }

    private function createRole($tmpl)
    {
        Log::info('Zoom Create Role -> ' . $tmpl['name']);

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
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Create Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Create Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function createGroup($tmpl)
    {
        Log::info('Zoom Create Group -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $json = '{
                "name": "(Group.displayName)"
        }';
        $json = str_replace("(Group.displayName)", $tmpl['name'], $json);
        
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Create Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Create Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            };
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
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
        Log::info('Zoom Update User -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
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
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/users/'.$tmpl['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Update User faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                Log::info('Update User ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $tmpl['externalZOOMID'];
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function updateRole($tmpl)
    {
        Log::info('Zoom Create Role -> ' . $tmpl['name']);

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
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/'. $tmpl['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Replace Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Replace Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
            
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function updateGroup($tmpl)
    {
        Log::info('Zoom Update Role -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $json = '{
                "name": "(Group.disylayName)"
        }';
        $json = str_replace("(Group.disylayName)", $tmpl['name'], $json);
        
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/'. $tmpl['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Replace Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Replace Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
            
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
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
        return $externalID;
    }

    private function deleteUser($resourceType, $item)
    {
        $user = Zoom::user()->find($item['email']);
        Log::info('Zoom Delete -> ' . $item['last_name'] . ' ' . $item['first_name']);
        $user->delete();
    }

    private function deleteRole($resourceType, $tmpl)
    {
        Log::info('Zoom delete Role -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';
        
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/'. $tmpl['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Delete Role faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Delete Role ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    private function deleteGroup($resourceType, $tmpl)
    {
        Log::info('Zoom delete Group -> ' . $tmpl['name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';
        
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/'. $tmpl['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
            $info = curl_getinfo($tuCurl);
            $responce = json_decode($tuData, true);

            if ((int)$info['http_code'] >= 300) {
                $zoom_status = $responce['code'];
                Log::error('Delete Group faild ststus = ' . $zoom_status . ' ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return null;
            } else {
                $return_id = $responce['id'];
                Log::info('Delete Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
                curl_close($tuCurl);
                return $return_id;
            }
            Log::info('Delete Group ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']);
        } else {
            Log::error('Curl error: ' . curl_error($tuCurl));
        }
        curl_close($tuCurl);
        return null;
    }

    public function replaceResource($resourceType, $item)
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
        // Import data group info
        $memberOf = $this->getListOfGroupsUserBelongedTo($item, 'ZOOM');

        // Now store data group info
        $groupIDListOnZOOM = $this->getGroupMemberOfsZOOM($item, $table);

        foreach ($memberOf as $groupID) {
            if(!in_array($groupID, $groupIDListOnZOOM)){
                $this->addMemberToGroup($item, $groupID);
            }
        }

        foreach ($groupIDListOnZOOM as $groupID) {
            if(!in_array($groupID, $memberOf)){
                $this->removeMemberOfGroup($item, $groupID);
            }
        }
    }

    public function addRoleMemebers($table, $item, $ext_id)
    {
        // Import data Role info
        $memberOf = $this->getListOfRoleUserBelongedTo($item, 'ZOOM');

        // Now store data group info
        $RoleIDOnZOOM = $this->getRoleMemberOfsZOOM($item, $table);

        foreach ($memberOf as $roleID) {
            if(!in_array($roleID, $RoleIDOnZOOM)){
                $this->assignMemberToRole($item, $roleID);
            }
        }

        /* No need to use */
        foreach ($RoleIDOnZOOM as $roleID) {
            if(!in_array($roleID, $memberOf)){
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

    public function getGroupMemberOfsZOOM($userAttibutes, $table)
    {
        $user = Zoom::user()->find($userAttibutes['email']);
        $arrayUser = json_decode($user, true);

        $groupIDList = [];
        foreach ($arrayUser['group_ids'] as $key => $value) {
            $groupIDList[] = $value;
        }
        return $groupIDList;
    }

    public function addMemberToGroup($item, $groupID)
    {
        Log::info('Zoom add member to Group -> ' . $item['first_name'] . ' ' .$item['last_name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $json = '{
            "members": [
                {
                  "id": "(User.externalZOOMID)",
                  "email": "(User.mail)"
                }
              ]
        }';
        $json = str_replace("(User.externalZOOMID)", $item['externalZOOMID'], $json);
        $json = str_replace("(User.mail)", $item['email'], $json);
        
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/groups/'.$groupID.'/members');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
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
        Log::info('Zoom remove member of Group -> ' . $item['first_name'] . ' ' .$item['last_name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 
            'https://api.zoom.us/v2/groups/'. $groupID . '/members/' . $item['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
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

    public function getListOfRoleUserBelongedTo($userAttibutes, $scims = ''): array
    {
        $memberOf = [];
        $memberOf = (new RegExpsManager())->getRoleInExternalID($userAttibutes['ID'], $scims);
        return $memberOf;
    }

    public function getRoleMemberOfsZOOM($userAttibutes, $table)
    {
        $user = Zoom::user()->find($userAttibutes['email']);
        $arrayUser = json_decode($user, true);

        $groupIDList = [];
        $groupIDList[] = $arrayUser['role_id'];
        return $groupIDList;
    }

    public function assignMemberToRole($item, $roleID)
    {
        Log::info('Zoom add member to Role -> ' . $item['first_name'] . ' ' .$item['last_name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $json = '{
            "members": [
                {
                "id": "(User.externalZOOMID)"
                }
            ]
        }';
        $json = str_replace("(User.externalZOOMID)", $item['externalZOOMID'], $json);
        
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 'https://api.zoom.us/v2/roles/'.$roleID.'/members');
        curl_setopt($tuCurl, CURLOPT_POST, 1);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $json);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
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
        Log::info('Zoom remove member of Role -> ' . $item['first_name'] . ' ' .$item['last_name']);

        $auth = sprintf("Bearer %s", $this->makeToken());
        $accept = 'application/json';

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, 
            'https://api.zoom.us/v2/roles/'. $roleID . '/members/' . $item['externalZOOMID']);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, 
            array("Authorization: $auth", "Content-type: $accept", "accept: $accept"));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

        $tuData = curl_exec($tuCurl);
        if(!curl_errno($tuCurl)){
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