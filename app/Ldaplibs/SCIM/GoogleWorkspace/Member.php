<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use Google_Service_Directory;
use Google_Service_Directory_Member;

class Member
{
    private $service;
    private $member;

    public function __construct($client)
    {
        $this->service = new Google_Service_Directory($client);
    }

    public function getMember()
    {
        return $this->member;
    }

    public function get($id)
    {
        return $this->service->members->listMembers($id);
    }

    public function hasMember($groupKey, $userKey)
    {
        return $this->service->members->hasMember($groupKey, $userKey);
    }

    public function insert($groupKey, $userKey)
    {
        $member = new Google_Service_Directory_Member();
        $member->setId($userKey);
        return $this->service->members->insert($groupKey, $member);
    }

    public function delete($groupKey, $userKey)
    {
        return $this->service->members->delete($groupKey, $userKey);
    }
}
