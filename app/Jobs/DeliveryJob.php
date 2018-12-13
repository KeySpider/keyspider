<?php

namespace App\Jobs;

use App\Ldaplibs\Delivery\CSVDelivery;
use App\Ldaplibs\Extract\DBExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeliveryJob implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $setting;

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
        $csv_delivery = new CSVDelivery($this->setting);
        $csv_delivery->delivery();
    }

    public function getJobName(){
        return "Delivery Job";
    }

    public function getJobDetails(){

        return $this->setting;
    }
}
