<?php

namespace App\Jobs;

use App\Ldaplibs\Import\DBImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DBImporterJob extends DBImporter implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileName;

    /**
     * Create a new job instance.
     *
     * @param $setting
     * @param $fileName
     */
    public function __construct($setting, $fileName)
    {
        parent::__construct($setting, $fileName);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
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
}
