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
        foreach ($this->setting as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $extractor = new DBExtractor($setting);
            $extractor->process();
        }
    }


}
