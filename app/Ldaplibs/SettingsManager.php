<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:04 AM
 */

namespace App\Ldaplibs;

use App\Ldaplibs\Import\CSVReader;

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
    private $ini_master_db_file = null;
    private $all_table_settings_content = null;

    const BASIC_CONFIGURATION = "CSV Import Process Bacic Configuration";

    public function __construct($ini_settings_files = null)
    {
        $this->ini_settings_folders = storage_path("" . self::INI_CONFIGS . "/");
//        echo '<h3> parsing all ini files in folder: ' . $this->ini_settings_folders . "</h3>";
        $all_files = scandir($this->ini_settings_folders);
        foreach ($all_files as $file_name) {
            if (contains('.ini', $file_name)) {
                if (contains('Master', $file_name)) {
                    $this->ini_master_db_file = $file_name;
                } else {
                    $this->ini_import_settings_files[] = $file_name;
                }
            }
        }
    }

    public function get_list_of_data_extract()
    {
        return [];
    }

    /**
     * @return array
     */
    public function get_rule_of_import()
    {
        if ($this->all_table_settings_content){
            return $this->all_table_settings_content;
        }

        $filename = $this->ini_master_db_file;

        $master = $this->get_inifile_content($filename);

        $this->all_table_settings_content = array();

        foreach ($this->ini_import_settings_files as $ini_import_settings_file) {
            $table_contents = $this->get_inifile_content($ini_import_settings_file);
//            set filename in json file
            $table_contents['IniFileName'] = $ini_import_settings_file;
//            Set destination table in database
            $tableNameInput = $table_contents[self::BASIC_CONFIGURATION]["ImportTable"];
            $tableNameOutput = $master["$tableNameInput"]["$tableNameInput"];
            $table_contents[self::BASIC_CONFIGURATION]["TableNameInDB"] = $tableNameOutput;

            $master_users = $master[$tableNameInput];

//            Column conversion
            $column_name_conversion = $table_contents[self::CONVERSION];
            foreach ($column_name_conversion as $key => $value)
                if (isset($master_users[$key])) {
                    $column_name_conversion[$master_users[$key]] = $value;
                    unset($column_name_conversion[$key]);
                }
            $table_contents[self::CONVERSION] = $column_name_conversion;

            $this->all_table_settings_content[] = $table_contents;
        }
        return $this->all_table_settings_content;
    }

    /**
     * @param $filename     ini file name to read
     * @return array of key/value from ini file.
     */
    public function get_inifile_content($filename): array
    {
        $ini_path = $this->ini_settings_folders . $filename;
        $ini_array = parse_ini_file($ini_path, true);
        return $ini_array;
    }

    public function get_schedule_import_execution()
    {
        $json_settings = $this->get_rule_of_import();
//        print (json_encode($json_settings, JSON_PRETTY_PRINT));
        $time_array = array();
        foreach ($json_settings as $a_setting){
            $executionTime = $a_setting[self::BASIC_CONFIGURATION]['ExecutionTime'];
            print (json_encode($executionTime, JSON_PRETTY_PRINT));
            $time_array = array_merge($time_array,$executionTime);
        }
        return $time_array;
    }

    public function get_files_from_pattern($path, $pattern)
    {
        $data = [];

        $pathDir = storage_path("{$path}");
        $validate_file = ['csv'];

        if (is_dir($pathDir)) {
            foreach (scandir($pathDir) as $key => $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($ext, $validate_file)) {
                    if (preg_match("/{$this->remove_ext($pattern)}/", $this->remove_ext($file))) {
                        array_push($data['file_csv'], "{$path}/{$file}");
                    }
                }
            }
        }

        return $data;
    }

    protected function remove_ext($file_name)
    {
        $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
        return $file;
    }
}
