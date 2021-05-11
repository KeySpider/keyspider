<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use Google_Service_Directory;
use Google_Service_Directory_Group;

class Group
{

    private $service;
    private $group;

    public function __construct($client)
    {
        $this->service = new Google_Service_Directory($client);
        $this->group = new Google_Service_Directory_Group();
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function getPrimaryKey($group)
    {
        return $group['id'];
    }

    public function setResource($group)
    {
        return $this->group = $group;
    }

    public function insert()
    {
        return $this->service->groups->insert($this->group);
    }

    public function update($id)
    {
        return $this->service->groups->update($id, $this->group);
    }

    public function delete($id)
    {
        return $this->service->groups->delete($id);
    }

    public function get($id)
    {
        return $this->service->groups->get($id);
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
            case $key == 'adminCreated':
                $this->setAdminCreated($value);
                break;
            case $key == 'aliases':
                $this->setAliases($value);
                break;
            case $key == 'description':
                $this->setDescription($value);
                break;
            case $key == 'directMembersCount':
                $this->setDirectMembersCount($value);
                break;
            case $key == 'email':
                $this->setEmail($value);
                break;
            case $key == 'etag':
                $this->setEtag($value);
                break;
            case $key == 'kind':
                $this->setKind($value);
                break;
            case $key == 'name':
                $this->setName($value);
                break;
            case $key == 'nonEditableAliases':
                $this->setNonEditableAliases($value);
                break;
        }
    }

    private function setAdminCreated($value)
    {
        $this->group->setAdminCreated($value);
    }

    private function setAliases($value)
    {
        $this->group->setAliases($value);
    }

    private function setDescription($value)
    {
        $this->group->setDescription($value);
    }

    private function setDirectMembersCount($value)
    {
        $this->group->setDirectMembersCount($value);
    }

    private function setEmail($value)
    {
        $this->group->setEmail($value);
    }

    private function setEtag($value)
    {
        $this->group->setEtag($value);
    }

    private function setKind($value)
    {
        $this->group->setKind($value);
    }

    private function setName($value)
    {
        $this->group->setName($value);
    }

    private function setNonEditableAliases($value)
    {
        $this->group->setNonEditableAliases($value);
    }
}
