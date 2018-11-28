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
use function PHPSTORM_META\elementType;

function prettyPrint($json)
{
    $result = '';
    $level = 0;
    $in_quotes = false;
    $in_escape = false;
    $ends_line_level = NULL;
    $json_length = strlen($json);

    for ($i = 0; $i < $json_length; $i++) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if ($ends_line_level !== NULL) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if ($in_escape) {
            $in_escape = false;
        } else if ($char === '"') {
            $in_quotes = !$in_quotes;
        } else if (!$in_quotes) {
            switch ($char) {
                case '}':
                case ']':
                    $level--;
                    $ends_line_level = NULL;
                    $new_line_level = $level;
                    break;

                case '{':
                case '[':
                    $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ":
                case "\t":
                case "\n":
                case "\r":
                    $char = "";
                    $ends_line_level = $new_line_level;
                    $new_line_level = NULL;
                    break;
            }
        } else if ($char === '\\') {
            $in_escape = true;
        }
        if ($new_line_level !== NULL) {
            $result .= "\n" . str_repeat("\t", $new_line_level);
        }
        $result .= $char . $post;
    }

    return $result;
}

function pretty_json($json)
{

    $result = '';
    $pos = 0;
    $strLen = strlen($json);
    $indentStr = '  ';
    $newLine = "\n";
    $prevChar = '';
    $outOfQuotes = true;

    for ($i = 0; $i <= $strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;

            // If this character is the end of an element,
            // output a new line and indent the next line.
        } else if (($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos--;
            for ($j = 0; $j < $pos; $j++) {
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
                $pos++;
            }

            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }

        $prevChar = $char;
    }

    return $result;
}

function contains($needle, $haystack)
{
    return strpos($haystack, $needle) !== false;
}

class SettingsManager
{
    private $ini_settings_folders;
    const CONVERSION = "CSV Import Process Format Conversion";
    const INI_CONFIGS = "ini_configs";
    private $ini_import_settings_files = array();
    private $master_db_ini_file = null;

    const BASIC_CONFIGURATION = "CSV Import Process Bacic Configuration";

    public function __construct($ini_settings_files = null)
    {
        $this->ini_settings_folders = storage_path("" . self::INI_CONFIGS . "/");
        echo '<h3> parsing all ini files in folder: ' . $this->ini_settings_folders."</h3>";
        $all_files = scandir($this->ini_settings_folders);
        foreach ($all_files as $file) {
            if (contains('.ini', $file)) {
//                print '<p>' . $file . '<p>';
                if (contains('Master', $file)) {
                    $this->master_db_ini_file = $file;
                } else {
                    $this->ini_import_settings_files[] = $file;
                }
            }
        }
//        var_dump($this->master_db_ini_file);
//        var_dump($this->ini_import_settings_files);

//        var_dump($ini_files_list);
    }

    public function get_list_of_data_extract()
    {
        return [];
    }

    public function get_rule_of_import()
    {
        $filename = $this->master_db_ini_file;

        $master = $this->get_inifile_content($filename);

        $all_table_settings_content = array();

//        $ini_import_settings_file = 'UserInfoCSVImport.ini';

        foreach ($this->ini_import_settings_files as $ini_import_settings_file){
            $table_contents = $this->get_inifile_content($ini_import_settings_file);
//            set filename in json file
            $table_contents['IniFileName'] = $ini_import_settings_file;
//            Set destination table in database
            $tableNameInput = $table_contents[self::BASIC_CONFIGURATION]["ImportTable"];
            $tableNameOutput = $master["$tableNameInput"]["$tableNameInput"];
            $table_contents[self::BASIC_CONFIGURATION]["TableNameInDB"] = $tableNameOutput;

//            var_dump($table_contents);
//            print_r($tableNameInput);

            $master_users = $master[$tableNameInput];
//            echo '<p><h2>DB Convert standard</h2></p>';
//            echo 'Master users: <p>';
//            var_dump($master);

//            Column conversion
            $column_name_conversion = $table_contents[self::CONVERSION];
            foreach ($column_name_conversion as $key => $value)
                if (isset($master_users[$key])) {
                    $column_name_conversion[$master_users[$key]] = $value;
                    unset($column_name_conversion[$key]);
                }
            $table_contents[self::CONVERSION] = $column_name_conversion;

            $all_table_settings_content[] = $table_contents;
        }
        return $all_table_settings_content;
    }

    /**
     * @param $filename
     */
    public function get_inifile_content($filename): array
    {
        $ini_path = $this->ini_settings_folders . $filename;
        $ini_array = parse_ini_file($ini_path, true);
        return $ini_array;
    }
}
