<?php

namespace App\Jobs;

use App\Ldaplibs\Delivery\CSVDelivery;
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
        $csvDelivery = new CSVDelivery($this->setting);
        $csvDelivery->delivery();
    }

    /**
     * Get job name
     * @return string
     */
    public function getJobName()
    {
        return "Delivery Job";
    }

    /**
     * Detail job
     * @return mixed
     */
    public function getJobDetails()
    {
        return $this->setting;
    }
}
