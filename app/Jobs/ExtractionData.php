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
                dump($setting);
                $condition = $setting[self::EXTRACTION_CONDITION];

                $DBExtractor = new DBExtractor();
                $table = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
                $results = $DBExtractor->extractDataByCondition($condition, $this->switchTable($table));
            }
        } catch (Exception $e) {
            Log::channel('import')->error($e);
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
