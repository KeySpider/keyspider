<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use App\Ldaplibs\RegExpsManager;
use Google_Service_Directory;
use Google_Service_Directory_Role;
use Google_Service_Directory_RoleRolePrivileges;

class Role
{

    private $service;
    private $role;
    private $customerId;
    private $reg;

    public function __construct($client, $customerId)
    {
        $this->service = new Google_Service_Directory($client);
        $this->role = new Google_Service_Directory_Role();
        $this->customerId = $customerId;
        $this->reg = new RegExpsManager();
    }

    public function setPrivilegeAttrs($item) {
        $privilegeIds = $this->reg->getPrivilegesInRole($item['ID'], '0');
        if (empty($privilegeIds)) {
            return $item;
        }
        $rolePrivileges = array();
        foreach ($privilegeIds as $privilegeId) {
            $privileges = $this->reg->getAttrsFromID('Privilege', $privilegeId);
            $privilege = $privileges[0];

            $rolePrivilege = new Google_Service_Directory_RoleRolePrivileges();
            $rolePrivilege->setPrivilegeName($privilege['name']);
            $rolePrivilege->setServiceId($privilege['Key']);
            array_push($rolePrivileges, $rolePrivilege);
        }
        $item['rolePrivileges'] = $rolePrivileges;
        return $item;
    }

    public function getRole() {
        return $this->role;
    }

    public function getPrimaryKey($role) {
        return $role['roleId'];
    }

    public function setResource($role) {
        return $this->role = $role;
    }

    public function insert() {
        return $this->service->roles->insert($this->customerId, $this->role);
    }

    public function update($id) {
        return $this->service->roles->update($this->customerId, $id, $this->role);
    }

    public function delete($id) {
        return $this->service->roles->delete($this->customerId, $id);
    }

    public function get($id) {
        return $this->service->roles->get($this->customerId, $id);
    }

    public function setAttributes($values) {
        $values = $this->setPrivilegeAttrs($values);
        foreach ($values as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function setAttribute($key, $value)
    {
        switch (true) {
            case $key == 'roleName':
                $this->setRoleName($value);
                break;
            case $key == 'rolePrivileges':
                $this->setRolePrivileges($value);
                break;
            case $key == 'roleDescription':
                $this->setRoleDescription($value);
                break;
        }
    }

    private function setRoleName($value) {
        $this->role->setRoleName($value);
    }

    private function setRolePrivileges($value) {
        $this->role->setRolePrivileges($value);
    }

    private function setRoleDescription($value) {
        $this->role->setRoleDescription($value);
    }

}
