<?php

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Ldaplibs;

use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

use App\User;
use App\Organization;
use App\UserToGroup;

class RegExpsManager
{
    const OPERATIONS = array(
        'EQ'=>'=',  // EQual
        'GE'=>'>=', // Greater Equal
        'GT'=>'>',  // Greater Than
        'LE'=>'<=', // Lesser Equal
        'LT'=>'<',  // Lesser Than
        'NE'=>'!=', // Not Equal
        'RG'=>'~'   // ReGular expressions
    );

    public function __construct()
    {

    }

    public function hasLogicalOperation($convertData)
    {
        $success = preg_match('/^\(([A-Z]{2}),(.*)\)/', $convertData, $match);

        if ($success) {
            return $match;
        }
        return null;
    }

    public function makeExpLOCondition($key, $match, $whereData)
    {
        array_push($whereData, [$key, (string)self::OPERATIONS[$match[1]], (string)$match[2]]);
        return $whereData;
    }

    public function checkRegExpRecord($convertData){
        $needle = '[$';
        if ( strpos( $convertData, $needle ) === false ) {
            return null;
        }

        $success = preg_match('/\(\s*(?<exp1>[a-zA-Z0-9_\-\.]+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/', $convertData, $match);
        if ($success) {
            $retArray = explode('.', $match['exp1']);
            return $retArray[count($retArray) -1];
        }
        return null;
    }

    public function convertDataFollowSetting($convertData, $recordValue)
    {
        $stt = null;
        $group = null;
        $regx = null;
    
        $success = preg_match('/\(\s*(?<exp1>[a-zA-Z0-9_\-\.]+)\s*(,(?<exp2>.*(?=,)))?(,?(?<exp3>.*(?=\))))?\)/', $convertData, $match);
        if ($success) {
            $stt = $match['exp1'];
            $regx = $match['exp2'];
            $group = $match['exp3'];

            $check = preg_match("/{$regx}/", $recordValue, $str);
            if ($check) {
                return $this->processRegexp($str, $group);
            }
        }

        return $convertData;
    }

    public function processRegexp($str, $format): string
    {
        foreach ($str as $index => $value) {
            $format = str_replace('[$'.$index.']', $value, $format);
        }
        return $format;
    }

    public function getEffectiveDate($item)
    {
        $pattern = '/(TODAY\(\))\s*([\+|-])\s*([0-9]*)/';  
        $retDate = Carbon::now()->format('Y/m/d');
      
        if (strpos($item, 'TODAY()') !== false) {
            if ($item != 'TODAY()') {
                if (preg_match($pattern, $item, $matchs)) {
                    if ($matchs[2] == '+') {
                        $retDate = Carbon::now()->addDays((int)$matchs[3])->format('Y/m/d');
                    } elseif ($matchs[2] == '-') {
                        $retDate = Carbon::now()->subDays((int)$matchs[3])->format('Y/m/d');
                    }
                }
            }
        }
        return $retDate;
    }

    public function getGroupInExternalID($uid, $scims)
    {
        $table = 'UserToGroup';
        $queries = DB::table($table)
                    ->select('Group_ID')
                    ->where('User_ID', $uid)
                    ->where('DeleteFlag', '0')->get();

        $groupIds = [];
        foreach ($queries as $key => $value) {
            $groupIds[] = $value->Group_ID;
        }

        $table = 'Group';
        $queries = DB::table($table)
                    ->select('external' . $scims . 'ID')
                    ->whereIn('ID', $groupIds)
                    ->get();

        $externalIds = [];

        foreach ($queries as $key => $value) {
            $cnv = (array)$value;
            foreach ($cnv as $key => $value) {
                $externalIds[] = $value;
            }    
            // $externalIds[] = $value->externalID;
        }
        return $externalIds; 
    }

    public function getRoleInExternalID($uid, $scims)
    {
        $table = 'UserToRole';
        $queries = DB::table($table)
                    ->select('Role_ID')
                    ->where('User_ID', $uid)
                    ->where('DeleteFlag', '0')->get();

        $groupIds = [];
        foreach ($queries as $key => $value) {
            $groupIds[] = $value->Role_ID;
        }

        $table = 'Role';
        $queries = DB::table($table)
                    ->select('external' . $scims . 'ID')
                    ->whereIn('ID', $groupIds)
                    ->get();

        $externalIds = [];

        foreach ($queries as $key => $value) {
            $cnv = (array)$value;
            foreach ($cnv as $key => $value) {
                $externalIds[] = $value;
            }    
        }
        return $externalIds; 
    }

    public function getOrganizationInExternalID($uid, $scims)
    {
        $table = 'UserToOrganization';
        $queries = DB::table($table)
                    ->select('Organization_ID')
                    ->where('User_ID', $uid)
                    ->where('DeleteFlag', '0')->get();

        $organizationIds = [];
        foreach ($queries as $key => $value) {
            $organizationIds[] = $value->Organization_ID;
        }

        $table = 'Organization';
        $queries = DB::table($table)
                    ->select('external' . $scims . 'ID')
                    ->whereIn('ID', $organizationIds)
                    ->get();

        $externalIds = [];

        foreach ($queries as $key => $value) {
            $cnv = (array)$value;
            foreach ($cnv as $key => $value) {
                $externalIds[] = $value;
            }    
            // $externalIds[] = $value->externalID;
        }
        return $externalIds; 
    }

    public function updateUserUpdateFlags($user_id)
    {
        $nameTable = 'User';
        $settingManagement = new SettingsManager();
        $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($nameTable);
        // set UpdateFlags
        $updateFlagsArray = $settingManagement->getAllExtractionProcessID($nameTable);

        foreach ($updateFlagsArray as $processId) {
            $updateFlags[$processId] = '1';
        }

        $setValues[$colUpdateFlag] = json_encode($updateFlags);
        DB::table($nameTable)->where("ID", $user_id)->update($setValues);
    }

    public function getUsersInGroup($id)
    {
        $table = 'UserToGroup';
        $queries = DB::table($table)
                   ->select('User_ID')->where('Group_ID', $id)->get();

        $userIds = [];
        foreach ($queries as $key => $value) {
            $userIds[] = $value->User_ID;
        }
        return $userIds;
    }

    public function getUsersInGroups($id)
    {
        $table = 'UserToGroup';
        $queries = DB::table($table)
                   ->select('User_ID')->whereIn('Group_ID', $id)->get();

        $userIds = [];
        foreach ($queries as $key => $value) {
            $userIds[] = $value->User_ID;
        }
        return $userIds;
    }

    public function getGroupsInUser($id)
    {
        $table = 'UserToGroup';
        $queries = DB::table($table)
                   ->select('Group_ID')->where('User_ID', $id)->get();

        $groupIds = [];
        foreach ($queries as $key => $value) {
            $groupIds[] = $value->Group_ID;
        }
        return $groupIds;
    }

    public function getPrivilegesInRole($id, $deleteFlag = null)
    {
        $table = 'RoleToPrivilege';
        $queries = DB::table($table)->select('Privilege_ID')->where('Role_ID', $id);
        if ($deleteFlag != null) {
            $queries = $queries->where('DeleteFlag', $deleteFlag);
        }
        $list = $queries->get();

        $privilegeIds = [];
        foreach ($list as $key => $value) {
            $privilegeIds[] = $value->Privilege_ID;
        }
        return $privilegeIds;
    }

    public function getDeleteFlagFromUserToGroup($userId, $groupId)
    {
        $table = 'UserToGroup';
        $queries = DB::table($table)
                   ->select('DeleteFlag')
                   ->where('User_ID', $userId)->where('Group_ID', $groupId)
                   ->first();

        return $queries->DeleteFlag;
    }

    public function getAttrs($table, $column, $ids)
    {
        $queries = DB::table($table)->where($column, $ids)->get();

        $attrs = array();
        foreach ($queries as $key => $value) {
            array_push($attrs, (array) $value);
        }
        return $attrs;
    }

    public function getAttrFromID($table, $ids, $selectColumn)
    {
        $queries = DB::table($table)
                   ->select($selectColumn)->where('ID', $ids)->get();

        $attrs = [];
        foreach ($queries as $key => $value) {
            $attrs[] = ((array) $value)[$selectColumn];
        }
        return $attrs;
    }

    public function getAttrsFromID($table, $ids)
    {
        $queries = DB::table($table)->where('ID', $ids)->get();

        $attrs = array();
        foreach ($queries as $key => $value) {
            array_push($attrs, (array) $value);
        }
        return $attrs;
    }

    public function getUpperValue($item, $key, $value, $default = null) {
        if (strpos($value, 'ELOQ;') !== false) {
            return $item[$key];
        }
        preg_match('/\((.*)\)/', $value, $matches, PREG_OFFSET_CAPTURE, 0);
        if (strpos($matches[1][0], '->') === false) {
            return $item[$key];
        }
        $list = explode('->', $matches[1][0]);
        $i = $item;
        foreach ($list as $k => $v) {
            $d = explode('.', $v);
            if ($k == 0) {
                $whereValue = $i[$d[1]];
            } else if ($k % 2 == 1) {
                $whereColumn = $d[1];
                $table = $d[0];
            } else {
                $selectColumn = $d[1];
                $query = DB::table($table);
                $query = $query->select($selectColumn)
                           ->where($whereColumn, $whereValue);
                $extractedSql = $query->toSql();
                $i = (array) $query->first();
                if (empty($i) || array_key_exists($d[1], $i) === false) {
                    return $default;
                }
                $whereValue = $i[$d[1]];
            }
        }
        if (empty($i)) {
            return $default;
        } else {
            return $i[$selectColumn];
        }
    }

    public function eloquentItem($id, $evalStr)
    {
        $box = '';

        $splts = explode('->', $evalStr);
        $cmps = '';
        foreach ($splts as $key => $value) {
            if ($key == 0) {
                $cmps = $value . "::find('***')";
            } else {
                $cmps = $cmps . '->' . $value;
            }
        }
        $evalStr = $cmps;

        $evalStr = str_replace('***', $id, $evalStr);
        $commands = '$box = \App\\' . $evalStr . ';';

        try {
            eval($commands);
        } catch (ParseError $e) {
            // echo 'Caught exception: '.$e->getMessage()."\n";
        }
        return $box;
    }
}
