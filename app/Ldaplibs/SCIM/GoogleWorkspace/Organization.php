<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use Google_Service_Directory;
use Google_Service_Directory_OrgUnit;

class Organization
{
    private $service;
    private $organization;
    private $customerId;

    public function __construct($client, $customerId)
    {
        $this->service = new Google_Service_Directory($client);
        $this->organization = new Google_Service_Directory_OrgUnit();
        $this->customerId = $customerId;
    }

    public function getOrganization()
    {
        return $this->organization;
    }

    public function getPrimaryKey($organization)
    {
        return $organization['orgUnitId'];
    }

    public function setResource($organization)
    {
        return $this->organization = $organization;
    }

    public function insert()
    {
        return $this->service->orgunits->insert($this->customerId, $this->organization);
    }

    public function update($id)
    {
        return $this->service->orgunits->update($this->customerId, $id, $this->organization);
    }

    public function delete($id)
    {
        return $this->service->orgunits->delete($this->customerId, $id);
    }

    public function get($id)
    {
        return $this->service->orgunits->get($this->customerId, $id);
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
            case $key == 'blockInheritance':
                $this->setBlockInheritance($value);
                break;
            case $key == 'description':
                $this->setDescription($value);
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
            case $key == 'orgUnitId':
                $this->setOrgUnitId($value);
                break;
            case $key == 'orgUnitPath':
                $this->setOrgUnitPath($value);
                break;
            case $key == 'parentOrgUnitId':
                $this->setParentOrgUnitId($value);
                break;
            case $key == 'parentOrgUnitPath':
                $this->setParentOrgUnitPath($value);
                break;
        }
    }

    private function setBlockInheritance($value)
    {
        $this->organization->setBlockInheritance($value);
    }

    private function setDescription($value)
    {
        $this->organization->setDescription($value);
    }

    private function setEtag($value)
    {
        $this->organization->setEtag($value);
    }

    private function setKind($value)
    {
        $this->organization->setKind($value);
    }

    private function setName($value)
    {
        $this->organization->setName($value);
    }

    private function setOrgUnitId($value)
    {
        $this->organization->setOrgUnitId($value);
    }

    private function setOrgUnitPath($value)
    {
        $this->organization->setOrgUnitPath($value);
    }

    private function setParentOrgUnitId($value)
    {
        if ($value == 'default') {
            $this->organization->setParentOrgUnitPath('/');
        } else {
            $this->organization->setParentOrgUnitId($value);
        }
    }

    private function setParentOrgUnitPath($value)
    {
        $this->organization->setParentOrgUnitPath($value);
    }
}
