<?php

namespace App\Console\Scheduler;

use App\Jobs\DBToLdapExtractorJob;

class ExportLDAPScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToLdapExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return 'LDAP Export Process Configration';
    }

    protected function getServiceName()
    {
        return 'LDAP';
    }
}
