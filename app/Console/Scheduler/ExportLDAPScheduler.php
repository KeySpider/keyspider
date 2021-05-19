<?php

namespace App\Console\Scheduler;

use App\Commons\Consts;
use App\Jobs\DBToLdapExtractorJob;

class ExportLDAPScheduler extends ExportScheduler
{
    protected function getJob($setting)
    {
        return new DBToLdapExtractorJob($setting);
    }

    protected function getServiceConfig()
    {
        return Consts::LDAP_EXTRACT_PROCESS_CONFIGURATION;
    }

    protected function getServiceName()
    {
        return 'LDAP';
    }
}
