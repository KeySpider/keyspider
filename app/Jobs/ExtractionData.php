<?php

namespace App\Jobs;

use App\Ldaplibs\Delivery\DataDelivery;
use App\Ldaplibs\Delivery\DBExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Exception;

class ExtractionData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $setting;

    const EXTRACTION_CONDITION = 'Extraction Condition';
    const EXTRACTION_CONFIGURATION = "Extraction Process Bacic Configuration";
    const OUTPUT_PROCESS_CONVERSION = "Output Process Conversion";

    /**
     * Create a new job instance.
     *
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            foreach ($this->setting as $data) {
                $setting = $data['setting'];

                if (!isset($setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable']) ||
                    $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'] == ""
                ) {
                    Log::channel('export')->error("Extract table is empty");
                    break;
                }

                if (empty($setting)) {
                    Log::channel('export')->error("Setting is empty");
                    break;
                }

                $condition = $setting[self::EXTRACTION_CONDITION];

                $DBExtractor = new DBExtractor();
                $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
                $results = $DBExtractor->extractDataByCondition($condition, $this->switchTable($table));

                if (empty($results)) {
                    Log::channel('export')->error("Data is empty with {$setting}");
                    break;
                }

                $outputProcess = $setting[self::OUTPUT_PROCESS_CONVERSION];
                if (empty($outputProcess)) {
                    Log::channel('export')->error("Output Process Conversion is empty");
                    break;
                }

                $DBExtractor->extractCSVBySetting($results, $setting);
            }
        } catch (Exception $e) {
            Log::channel('export')->error($e);
        }
    }

    /**
     * @param $extractTable
     * @return string|null
     */
    protected function switchTable($extractTable)
    {
        switch ($extractTable) {
            case 'Role':
                return 'CCC';
                break;
            case 'User':
                return 'AAA';
                break;
            case 'Organization':
                return 'BBB';
                break;
            default:
                return null;
        }
    }
}
