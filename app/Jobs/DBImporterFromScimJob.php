<?php
/**
 * Project = Key Spider
 * Year = 2019
 * Organization = Key Spider Japan LLC
 */

namespace App\Jobs;

use App\Ldaplibs\Import\DBImporter;
use App\Ldaplibs\QueueManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DBImporterJob extends DBImporter implements ShouldQueue, JobInterface
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

    public function __construct($setting, $fileName)
    {
        parent::__construct($setting, $fileName);
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
        parent::import();
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
        $basicSetting = $this->setting['CSV Import Process Basic Configuration'];
        $details['File to import'] = $this->fileName;
        $details['File path'] = $basicSetting['FilePath'];
        $details['Processed File Path'] = $basicSetting['ProcessedFilePath'];
        $details['Table Name In DB'] = $basicSetting['TableNameInDB'];
        $details['Settings File Name'] = $this->setting['IniFileName'];
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
