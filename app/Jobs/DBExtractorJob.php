<?php

namespace App\Jobs;

use App\Ldaplibs\Extract\DBExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DBExtractorJob extends DBExtractor implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $queueSettings;

    /**
     * Create a new job instance.
     *
     * @param $setting
     */
    public $tries = 5;
    public $timeout = 120;

    public function __construct($setting, $fileName)
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
        sleep((int)$this->queueSettings['sleep']);
        parent::process();
    }

    /**
     * Get job name.
     * @return string
     */
    public function getJobName()
    {
        return "Extract from database";
    }

    /**
     * Detail job
     * @return array
     */
    public function getJobDetails()
    {
        $setting = $this->setting;
        $details = [];
        $details['Conversion'] = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
        $details['Extract table'] = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
        $details['Extract condition'] = $setting[self::EXTRACTION_CONDITION];
        return $details;
    }


    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds((int)$this->queueSettings['retry_after']);
    }
}
