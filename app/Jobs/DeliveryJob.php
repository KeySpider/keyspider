<?php

namespace App\Jobs;

use App\Ldaplibs\Extract\DBExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeliveryJob extends DBExtractor implements ShouldQueue, JobInterface
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
        return "Delivery";
    }

    public function getJobDetails(){
        $setting = $this->setting;
        $details = array();
        return $details;

    }
}
