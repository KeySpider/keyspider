<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

function prettyPrint( $json )
{
    $result = '';
    $level = 0;
    $in_quotes = false;
    $in_escape = false;
    $ends_line_level = NULL;
    $json_length = strlen( $json );

    for( $i = 0; $i < $json_length; $i++ ) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if( $ends_line_level !== NULL ) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if ( $in_escape ) {
            $in_escape = false;
        } else if( $char === '"' ) {
            $in_quotes = !$in_quotes;
        } else if( ! $in_quotes ) {
            switch( $char ) {
                case '}': case ']':
                $level--;
                $ends_line_level = NULL;
                $new_line_level = $level;
                break;

                case '{': case '[':
                $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ": case "\t": case "\n": case "\r":
                $char = "";
                $ends_line_level = $new_line_level;
                $new_line_level = NULL;
                break;
            }
        } else if ( $char === '\\' ) {
            $in_escape = true;
        }
        if( $new_line_level !== NULL ) {
            $result .= "\n".str_repeat( "\t", $new_line_level );
        }
        $result .= $char.$post;
    }

    return $result;
}

function pretty_json($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;

            // If this character is the end of an element,
            // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }

        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element,
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }

            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }

        $prevChar = $char;
    }

    return $result;
}

class SettingsManager
{
    private $json_path;
    const CONVERSION = "CSV Import Process Format Conversion";

    const INI_CONFIGS = "ini_configs";

    public function __construct($json_path=null)
    {
        $this->$json_path = $json_path;
    }

    public function get_list_of_data_extract(){
        return [];
    }

    public function get_rule_of_import(){
        $filename = 'MasterDBConf.ini';
        $master = $this->get_inifile_content($filename);
        $user = $this->get_inifile_content('UserInfoCSVImport.ini');

        $mater_users = $master['User'];
        echo '<p><h2>DB Convert standard</h2></p>';
        var_dump($mater_users);
        $user_conversion = $user[self::CONVERSION];
        foreach ($user_conversion as $key=> $value )
            if (isset($mater_users[$key])){
                $user_conversion[$mater_users[$key]] = $value;
                unset($user_conversion[$key]);
            }
        $user[self::CONVERSION]=$user_conversion;


        return json_encode($user, JSON_PRETTY_PRINT);;
    }

    /**
     * @param $filename
     */
    public function get_inifile_content($filename): array
    {
        $ini_path = storage_path("" . self::INI_CONFIGS . "/$filename");
        $ini_array = parse_ini_file($ini_path, true);
        return $ini_array;
    }
}