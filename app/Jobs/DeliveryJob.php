<?php

namespace App\Jobs;

use App\Ldaplibs\Delivery\CSVDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SerializesModels;

class DeliveryJob implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $queueSettings;
    protected $setting;
    public $tries = 5;
    public $timeout = 120;

    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->queueSettings = QueueManager::getQueueSettings();
        $this->tries = $this->queueSettings['tries'];
        $this->timeout = $this->queueSettings['timeout'];

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        sleep((int)$this->queueSettings['sleep']);
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
