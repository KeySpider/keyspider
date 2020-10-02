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

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class RegExpsManager
{

    public function __construct()
    {

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
            preg_match($pattern, $item, $matchs);
            if ($matchs[2] == '+') {
              $retDate = Carbon::now()->addDays((int)$matchs[3])->format('Y/m/d');
            } elseif ($matchs[2] == '-') {
              $retDate = Carbon::now()->subDays((int)$matchs[3])->format('Y/m/d');
            }
          }
        }
        return $retDate;
    }
}
