<?php
/**
 * Created by PhpStorm.
 * User: Ngulb
 * Date: 11/22/18
 * Time: 11:39 PM
 */

namespace App\Ldaplibs\Import;

use App\Ldaplibs\SettingsManager;
use DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DBImporter
{
    /**
     * @var array $setting
     * @var string $fileName
     * @var object $csvReader
     */
    protected $setting;
    protected $fileName;
    protected $csvReader;

    /**
     * define const
     */
    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Basic Configuration";

    /**
     * DBImporter constructor.
     *
     * @param array $setting
     * @param $fileName
     */
    public function __construct($setting, $fileName)
    {
        $this->setting = $setting;
        $this->fileName = $fileName;
        $this->csvReader = new CSVReader(new SettingsManager());
    }

    /**
     * Process import data csv into database
     *
     * @return void
     */
    public function import()
    {
        try {
            $processedFilePath = $this->setting[self::CONFIGURATION]['ProcessedFilePath'];
            mkDirectory($processedFilePath);

            $name_table = $this->csvReader->getNameTableFromSetting($this->setting);
            $columns = $this->csvReader->getAllColumnFromSetting($this->setting);

            $this->csvReader->createTable($name_table, $columns);

            $params = [
                'CONVERSATION' => $this->setting[self::CONVERSION],
            ];
            $this->csvReader->getDataFromOneFile($this->fileName, $params, $columns, $name_table, $processedFilePath);
        } catch (Exception $e) {
            Log::channel('import')->error($e);
        }
    }
}
