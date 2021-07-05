<?php

namespace App\Commons;

use Illuminate\Support\Facades\DB;
use App\Ldaplibs\SettingsManager;


class Creator
{
    private const TABLE_PREFIX = array(
        "User" => "u",
        "Group" => "g",
        "Organization" => "o",
        "Role" => "r",
        "Privilege" => "p",
        "UserToGroup" => "m",
        "UserToOrganization" => "s",
        "UserToRole" => "e",
        "UserToPrivilege" => "t",
        "RoleToPrivilege" => "y",
        "SummaryReport" => "r"
    );

    public function makeIdBasedOnMicrotime($table)
    {
        $prefix = self::TABLE_PREFIX[$table] ?? "k";
        $tableKey = (new SettingsManager)->getTableKey();

        $makeId = $this->makeId($prefix);
        while (DB::table($table)->where($tableKey, $makeId)->exists())
        {
            $makeId = $this->makeId($prefix);
        }
        return $makeId;
    }

    private function makeId($prefix)
    {
        list($msec, $sec) = explode(" ", microtime());
        $hashCreateTime = $sec . floor($msec * 1000000);
        $hashCreateTime = strrev($hashCreateTime);
        $makeId = base_convert($hashCreateTime, 10, 36);     
        $makeId = sprintf("$prefix%011s", $makeId);
        return $makeId;
    }
}
