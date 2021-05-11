<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use Google_Service_Directory;
use Google_Service_Directory_RoleAssignment;

class RoleAssignment
{
    private $service;
    private $customerId;

    public function __construct($client, $customerId)
    {
        $this->service = new Google_Service_Directory($client);
        $this->customerId = $customerId;
    }

    public function getRoleAssignment()
    {
        return $this->roleAssignment;
    }

    public function get($id, $params = array())
    {
        return $this->service->roleAssignments->get($this->customerId, $id, $params);
    }

    public function list($params = array())
    {
        return $this->service->roleAssignments->listRoleAssignments($this->customerId, $params);
    }

    public function insert($userId, $roleId)
    {
        $roleAssignment = new Google_Service_Directory_RoleAssignment();
        $roleAssignment->setAssignedTo($userId);
        $roleAssignment->setRoleId($roleId);
        $roleAssignment->setScopeType('CUSTOMER');
        return $this->service->roleAssignments->insert($this->customerId, $roleAssignment);
    }

    public function delete($id)
    {
        return $this->service->roleAssignments->delete($this->customerId, $id);
    }
}
