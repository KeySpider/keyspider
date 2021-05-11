<?php

namespace App\Ldaplibs\SCIM\OneLogin;

class Role
{

    private $client;
    private $role = array();

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getPrimaryKey($role)
    {
        return $role->id;
    }

    public function setResource($role)
    {
        return $this->role = $role;
    }

    public function create()
    {
        return $this->client->createRole($this->role);
    }

    public function update($id)
    {
        return $this->client->updateRole($id, $this->role);
    }

    public function delete($id)
    {
        return $this->client->deleteRole($id);
    }

    public function get($id)
    {
        return $this->client->getRole($id);
    }

    public function getRoleUsers($id)
    {
        return $this->client->getRoleUsers($id);
    }

    public function assignUserToRole($id, $userIds)
    {
        return $this->client->assignUserToRole($id, $userIds);
    }

    public function removeUserFromRole($id, $userIds)
    {
        return $this->client->removeUserFromRole($id, $userIds);
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
            case $key == 'name':
                $this->setValue($key, $value);
                break;
        }
    }

    private function setValue($key, $value)
    {
        $this->role[$key] = $value;
    }
}
