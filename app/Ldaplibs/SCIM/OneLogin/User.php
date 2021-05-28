<?php

namespace App\Ldaplibs\SCIM\OneLogin;

class User
{
    private $client;
    private $user = array();
    private $useLockflag = false;

    const FORMAT_CONVERSION = 'Extraction Process Format Conversion';
    const INI_PATH = 'ini_configs/extract/UserToOLExtraction.ini';

    public function __construct($client)
    {
        $this->client = $client;

        $fcSection = parse_ini_file(storage_path(self::INI_PATH), true) [self::FORMAT_CONVERSION];
        if ( array_key_exists('status', $fcSection) ) {
            $this->useLockflag = true;
        }
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPrimaryKey($user)
    {
        return $user->id;
    }

    public function setResource($user)
    {
        return $this->user = $user;
    }

    public function create()
    {
        return $this->client->createUser($this->user);
    }

    public function update($id)
    {
        return $this->client->updateUser($id, $this->user);
    }

    public function delete($id)
    {
        return $this->client->deleteUser($id);
    }

    public function get($id)
    {
        return $this->client->getUser($id);
    }

    public function password($id, $password)
    {
        return $this->client->setPasswordUsingClearText($id, $password, $password);
    }

    public function getUserRoles($id)
    {
        return $this->client->getUserRoles($id);
    }

    public function assignRoleToUser($id, $roleIds)
    {
        return $this->client->assignRoleToUser($id, $roleIds);
    }

    public function removeRoleFromUser($id, $roleIds)
    {
        return $this->client->removeRoleFromUser($id, $roleIds);
    }

    public function getError()
    {
        return $this->client->getError();
    }

    public function getErrorDescription()
    {
        return $this->client->getErrorDescription();
    }

    public function setAttributes($values)
    {
        foreach ($values as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function setAttribute($key, $value)
    {
        switch (true) {
            case $key == 'firstname':
                $this->setValue($key, $value);
                break;
            case $key == 'lastname':
                $this->setValue($key, $value);
                break;
            case $key == 'email':
                $this->setValue($key, $value);
                break;
            case $key == 'username':
                $this->setValue($key, $value);
                break;
                //            case $key == 'password':
                //                $this->setValue($key, $value);
                //                break;
                //            case $key == 'password_confirmation':
                //                $this->setValue($key, $value);
                //                break;
                //            case $key == 'password_algorithm':
                //                $this->setValue($key, $value);
                //                break;
                //            case $key == 'salt':
                //                $this->setValue($key, $value);
                //                break;
            case $key == 'title':
                $this->setValue($key, $value);
                break;
            case $key == 'department':
                $this->setValue($key, $value);
                break;
            case $key == 'company':
                $this->setValue($key, $value);
                break;
            case $key == 'comment':
                $this->setValue($key, $value);
                break;
            case $key == 'group_id':
                $this->setValue($key, $value);
                break;
            case $key == 'role_ids':
                $this->setValue($key, $value);
                break;
            case $key == 'phone':
                $this->setValue($key, $value);
                break;
            case $key == 'state':
                $this->setValue($key, $value);
                break;
            case $key == 'status':
                if ($this->useLockflag) {
                    $convBool = true;
                    if ((int)$value == 1) {
                        $convBool = false;
                    }                    
                    $this->setValue($key, $convBool);
                }
                break;
            case $key == 'directory_id':
                $this->setValue($key, $value);
                break;
            case $key == 'trusted_idp_id':
                $this->setValue($key, $value);
                break;
            case $key == 'manager_ad_id':
                $this->setValue($key, $value);
                break;
            case $key == 'samaccountname':
                $this->setValue($key, $value);
                break;
            case $key == 'member_of':
                $this->setValue($key, $value);
                break;
            case $key == 'userprincipalname':
                $this->setValue($key, $value);
                break;
            case $key == 'distinguished_name':
                $this->setValue($key, $value);
                break;
            case $key == 'external_id':
                $this->setValue($key, $value);
                break;
        }
    }

    private function setValue($key, $value)
    {
        $this->user[$key] = $value;
    }
}
