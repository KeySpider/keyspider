<?php
/**
 * Created by PhpStorm.
 * User: Ngulb
 * Date: 11/22/18
 * Time: 11:39 PM
 */

namespace App\Ldaplibs\Import;

use App\Ldaplibs\SettingsManager;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;

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
            $data = $this->csvReader->getDataFromOneFile($this->fileName, $params);
            $columns = implode(",", $columns);

            foreach ($data as $key2 => $item2) {
                $tmp = [];
                foreach ($item2 as $key3 => $item3) {
                    array_push($tmp, "\$\${$item3}\$\$");
                }

                $tmp = implode(",", $tmp);

                $isInsertDb = DB::statement("
                    INSERT INTO {$name_table}({$columns}) values ({$tmp});
                ");

                if ($isInsertDb) {
                    $now = Carbon::now()->format('Ymdhis') . rand(1000, 9999);
                    $fileName = "hogehoge_{$now}.csv";
                    moveFile($this->fileName, $processedFilePath . '/' . $fileName);
                }
            }
        } catch (Exception $e) {
            Log::channel('import')->error($e);
            Log::error(dd($e));
        }
    }
}
