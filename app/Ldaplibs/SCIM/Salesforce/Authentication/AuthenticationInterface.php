<?php

namespace App\Ldaplibs\SCIM\Salesforce\Authentication;

interface AuthenticationInterface
{
    public function getAccessToken();
    public function getInstanceUrl();
}
