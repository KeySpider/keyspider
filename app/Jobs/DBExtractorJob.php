<?php

namespace App\Jobs;

use App\Ldaplibs\Delivery\DBExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DBExtractorJob extends DBExtractor implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($setting)
    {
        parent::__construct($setting);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        parent::process();
    }

    public function getJobName(){
        return "Extract from database";
    }

    public function getJobDetails(){
        $setting = $this->setting;
        $details = array();
        $details['Conversion'] = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
        $details['Extract table'] = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
        $details['Extract condition'] = $setting[self::EXTRACTION_CONDITION];
//        $details['Format conversion'] = $setting[self::Extraction_Process_Format_Conversion];
        return $details;

    }
}
