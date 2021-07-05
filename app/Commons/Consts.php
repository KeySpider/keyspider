<?php

namespace App\Commons;

class Consts
{
    // import
    public const IMPORT_PROCESS_BASIC_CONFIGURATION     =  "Import Process Basic Configuration";
    public const IMPORT_PROCESS_FORMAT_CONVERSION       =  "Import Process Format Conversion";
    public const IMPORT_PROCESS_DATABASE_CONFIGURATION  =  "Import Process Database Configuration";

    // extract
    public const EXTRACTION_PROCESS_BASIC_CONFIGURATION    =  "Extraction Process Basic Configuration";
    public const EXTRACTION_PROCESS_FORMAT_CONVERSION      =  "Extraction Process Format Conversion";
    public const EXTRACTION_LDAP_CONNECTING_CONFIGURATION  =  "Extraction LDAP Connecting Configuration";
    public const EXTRACTION_PROCESS_CONDITION              =  "Extraction Process Condition";
    public const OUTPUT_PROCESS_CONVERSION                 =  "Output Process Conversion";
    public const SCIM_AUTHENTICATION_CONFIGURATION         =  "SCIM Authentication Configuration";
    public const RDB_CONNECTING_CONFIGURATION              =  "Extraction RDB Connecting Configuration";

    // import & extract common
    public const EXTRACTION_PROCESS_ID  =  "ExtractionProcessID";
    public const OUTPUT_TYPE            =  "OutputType";
    public const EXTRACTION_TABLE       =  "ExtractionTable";
    public const EXTERNAL_ID            =  "ExternalID";
    public const EXECUTION_TIME         =  "ExecutionTime";
    public const CONNECTION_TYPE        =  "ConnectionType";
    public const EXPORT_TABLE           =  "ExportTable";
    public const IMPORT_TABLE           =  "ImportTable";
    public const OUTPUT_TABLE           =  "OutputTable";
    public const PRIMARY_COLUMN         =  "PrimaryColumn";
    public const DELETE_TYPE            =  "deleteType";
    public const PREFIX                 =  "Prefix";
    public const FILE_PATH              =  "FilePath";
    public const FILE_NAME              =  "FileName";
    public const TEMP_PATH              =  "TempPath";
    public const PROCESSED_FILE_PATH    =  "ProcessedFilePath";
    public const GROUP_DELIMITER        =  "GroupDelimiter";

    // Keyspider.ini
    public const MASTER_DB_CONFIGURATION              =  "Master DB Configuration";
    public const AD_EXTRACT_PROCESS_CONFIGURATION     =  "Azure Extract Process Configuration";
    public const BOX_EXTRACT_PROCESS_CONFIGURATION    =  "BOX Extract Process Configuration";
    public const CSV_EXTRACT_PROCESS_CONFIGURATION    =  "CSV Extract Process Configuration";
    public const CSV_IMPORT_PROCESS_CONFIGURATION     =  "CSV Import Process Configuration";
    public const CSV_OUTPUT_PROCESS_CONFIGURATION     =  "CSV Output Process Configuration";
    public const GW_EXTRACT_PROCESS_CONFIGURATION     =  "GW Extract Process Configuration";
    public const LDAP_EXTRACT_PROCESS_CONFIGURATION   =  "LDAP Extract Process Configuration";
    public const OL_EXTRACT_PROCESS_CONFIGURATION     =  "OL Extract Process Configuration";
    public const RDB_IMPORT_PROCESS_CONFIGURATION     =  "RDB Import Process Configuration";
    public const RDB_EXTRACT_PROCESS_CONFIGURATION    =  "RDB Extract Process Configuration";
    public const SCIM_IMPORT_PROCESS_CONFIGURATION    =  "SCIM Import Process Configuration";
    public const SF_EXTRACT_PROCESS_CONFIGURATION     =  "SF Extract Process Configuration";
    public const SLACK_EXTRACT_PROCESS_CONFIGURATION  =  "SLACK Extract Process Configuration";
    public const TL_EXTRACT_PROCESS_CONFIGURATION     =  "TL Extract Process Configuration";
    public const ZOOM_EXTRACT_PROCESS_CONFIGURATION   =  "ZOOM Extract Process Configuration";

    public const MASTER_DB_CONFIG  =  "master_db_config";
    public const IMPORT_CONFIG     =  "import_config";
    public const EXTRACT_CONFIG    =  "extract_config";
    public const OUTPUT_CONFIG     =  "output_config";

    // Date
    public const DATE_FORMAT_YMDHIS  = "Y/m/d H:i:s";
    public const DATE_FORMAT_YMD     = "Y/m/d";

    // File
    public const INI_CONFIGS_PATH               =  "ini_configs";
    public const JSONS_PATH                     =  "jsons/";
    public const FILENAME_KEYSPIDER_INI         =  "KeySpider.ini";
    public const FILENAME_GENERAL_SETTINGS_INI  =  "GeneralSettings.ini";

    public const JOB_TITLE  =  "jobTitle";

    public const VALIDATION_TYPES = array(
        "required" => "required",  // 必須
        "required_if" => "required",  // 前提あり必須
        "unique" => "unique",  // ユニーク制約
        "numeric" => "numeric",  // フォーマット：数値
        "boolean" => "boolean",  // フォーマット：boolean
        "array" => "array",  // フォーマット：array
        "email" => "email:strict,dns,spoof",  // フォーマット：email
        "date" => null,  // フォーマット：日付
        "flag" => "boolean",  // フォーマット：フラグ（0 or 1 のチェック）
        "flagsArray" => null,  // フォーマット：arrayフラグ
        "regex" => null,  // フォーマット：正規表現
        "digits" => "digits",  // 桁数
        "max" => "max",  // 最大値
        "min" => "min"  // 最小値        
    );

    public const APPEND_RULES_TYPE = array(
        "max", "min"
    );

    public const DATE_FORMAT = array(
        "Y/m/d", "Y/n/d", "Y/m/j", "Y/n/j"
    );

    public const INDIVIDUAL_LOGIC = array(
        "required_if", "date", "digits", "flagsArray", "regex"
    );

    public const VALIDATION_OK = '{"message":"validation ok"}';

}
