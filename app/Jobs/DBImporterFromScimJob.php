<?php
/**
 * Project = Key Spider
 * Year = 2019
 * Organization = Key Spider Japan LLC
 */

namespace App\Jobs;

use App\Ldaplibs\Import\DBImporter;
use App\Ldaplibs\Import\DBImporterFromScimData;
use App\Ldaplibs\QueueManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DBImporterFromScimJob extends DBImporterFromScimData implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param $setting
     * @param $fileName
     */
    public $tries = 5;
    public $timeout = 120;
    protected $fileName;
    private $queueSettings;

    public function __construct($dataPost)
    {
        parent::__construct($dataPost);
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
        parent::importToDBFromDataPost();
    }

    /**
     * Get job name
     * @return string
     */
    public function getJobName()
    {
        return "Import to database";
    }

    /**
     * Detail job
     * @return array
     */
    public function getJobDetails()
    {
        $details = [];
        $details['post data'] = $this->dataPost;
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
