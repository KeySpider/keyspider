<?php

namespace App\Http\Controllers;

use App\Commons\Consts;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ValidationController extends Controller
{
    public function index(Request $request)
    {
        $nameTable = $request['nameTable'];
        $requestData = $request['body'];       

        $messages = $this->execValidations($nameTable, $requestData);

        $status = 200;
        if (empty($messages)) {
            $status = 200;
            $messages = "validation ok";
        }
        return response()->json([
            'message' => $messages
        ], $status, [], JSON_UNESCAPED_UNICODE);        

    }

    private function execValidations($nameTable, $request)
    {
        $error_messages = [];

        // If there is no setting in DB, do nothing
        $validRules = $this->getValidationRules($nameTable);
        if (is_null($validRules)) {
            return $error_messages;
        }

        foreach ($validRules as $key => $validSetting) {
            $validSetting = (array)$validSetting;
            $validType = $validSetting["type"];

            $checkColumn = sprintf("%s.%s(%s)", $validSetting["function"], $validSetting["item"], $validType);

            // `validations_type` is specified incorrectly
            if (!array_key_exists($validType, Consts::VALIDATION_TYPES)) {
                $error_messages[$checkColumn] = [ "412", "Invalid validations_type" ];
                continue;
            }

            // If there is no validation column or no array's key, do nothing
            if (!array_key_exists($validSetting["item"], $request)) {
                Log::debug('continue -> !array_key_exists($validSetting["item"], $request)');
                continue;
            } elseif (is_null($request[$validSetting["item"]])) {
                if ($validSetting["type"] == "required") {
                    $error_messages[$checkColumn] = [ "412", "Validation error. type = " . $validSetting["type"] ];
                    continue;
                }
            }

            // It will be individual logic
            if (in_array($validType, Consts::INDIVIDUAL_LOGIC)) {
                $retMessage = [];
                switch ($validType) {
                    case "required_if":
                        $retMessage = $this->validateRequiredIf($validSetting, $request);
                        break;
                    case "date":
                        $retMessage = $this->validateDateFormat($validSetting, $request);
                        break;
                    case "digits":
                        $retMessage = $this->validateItemDigits($validSetting, $request);
                        break;
                    case "flagsArray":
                        $retMessage = $this->validateFlagsArray($validSetting, $request);
                        break;
                    case "regex":
                        $retMessage = $this->validateRegex($validSetting, $request);
                        break;
                }
                if (!empty($retMessage)) {
                    $error_messages[$checkColumn] = $retMessage;
                }
                continue;
            }

            $ruleSettings = Consts::VALIDATION_TYPES[$validType];

            // Check setting params
            if (in_array($validType, Consts::APPEND_RULES_TYPE)) {
                if (empty($validSetting["value1"])) {
                    $error_messages[$checkColumn] = [ "412", "`value1` is empty"];
                    continue;
                }
                $ruleSettings = sprintf("%s:%s", $ruleSettings, $validSetting["value1"]);
            }

            // It will be individual logic(unique:table,columns)
            if ($validType == "unique") {
                $ruleSettings = sprintf("unique:%s,%s", $nameTable, $validSetting["item"]);
            }

            $rule = [$validSetting["item"] => [$ruleSettings]];
            $validator = \Validator::make( $request, $rule );
    
            if ($validator->fails()) {
                $error_messages[$checkColumn] = [ "412", "Validation error. type = " . $validSetting["type"] ];
                continue;
            }        
        }    
        return $error_messages;
    }

    private function getValidationRules($nameTable)
    {
        $query = DB::table("validations")->where("function", $nameTable)->where("delete_flag", false);
        $ruleOfRecords = $query->get()->toArray();
        if ($ruleOfRecords) {
            return $ruleOfRecords;
        }
        return null;
    }

    private function validateRequiredIf($validSetting, $request)
    {
        $error_messages = [];
        if (is_null($validSetting["value1"]) || is_null($validSetting["value2"])) {
            $error_messages = [ "412", "`value1` or `value2` is empty." ];
            return $error_messages;
        }
        $checkFiledValue = $request[$validSetting["value1"]];
        if ($checkFiledValue != $validSetting["value2"]) {
            $error_messages = [ "412", "Does not meet the conditions." ];
            return $error_messages;
        }

        if (array_key_exists($validSetting["item"], $request)) {
            if (empty($request[$validSetting["item"]])) {
                $error_messages = [ "412", "Validation error. type = " . $validSetting["type"] ];
            }
        } else {
            $error_messages = [ "412", "Validation error. type = " . $validSetting["type"] ];
        }
        return $error_messages;
    }

    private function validateDateFormat($validSetting, $request)
    {
        /*
        - 2018/01/01 (Y/m/d)
        - 2018/1/01 (Y/n/d)
        - 2018/01/1 (Y/m/j)
        - 2018/1/1 (Y/n/j)
        */
        $dateStr = $request[$validSetting['item']];
        $dateFormat = $validSetting['value1'];

        $error_messages = [];
        if (!in_array($dateFormat, Consts::DATE_FORMAT)) {
            $error_messages = [ "412", "bad date format" ];
            return $error_messages;
        }

        if (is_null($dateFormat)) {
            $error_messages = [ "412", "date format is empty" ];
            return $error_messages;
        }

        if (! is_string($dateFormat) && ! is_numeric($dateFormat)) {
            $error_messages = [ "412", "bad date format" ];
            return $error_messages;
        }

        $date = DateTime::createFromFormat('!'.$dateFormat, $dateStr);
        $isValidated = $date && $date->format($dateFormat) == $dateStr;
        if (!$isValidated) {
            $error_messages = [ "412", "bad date format" ];
            return $error_messages;
        }
        return $error_messages;
    }

    private function validateItemDigits($validSetting, $request)
    {
        $error_messages = [];

        $column = strlen($request[$validSetting["item"]]);
        $setVal = (int)$validSetting["value1"];

        if ($column !== $setVal) {
            $error_messages = [ "412", "Validation error. type = digits" ];
        }
        return $error_messages;
    }

    private function validateFlagsArray($validSetting, $request)
    {
        $error_messages = [];

        $updateFlags = json_decode($request[$validSetting["item"]], true);

        foreach ($updateFlags as $flagName => $value) {
            $intValue = (int)$value;
            if ($intValue == 0 || $intValue == 1) {
            } else {
                $error_messages = [ "412", "Validation error. type = flagsArray" ];
                break;
            }
        }
        return $error_messages;
    }

    private function validateRegex($validSetting, $request)
    {
        $error_messages = [];

        $pattern = $validSetting["value1"];
        $recData = $request[$validSetting["item"]];

        if (empty($pattern)) {
            $error_messages = [ "412", "matching pattern is empty"];
        } else {
            if (!preg_match($pattern, $recData, $matchs)) {
                $error_messages = [ "412", "matching pattern is wrong"];
            }
        }
        return $error_messages;
    }
}
