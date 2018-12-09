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

    /**
     * Create a new job instance.
     *
     * @param $setting
     * @param $file_name
     */
    public function __construct($setting, $file_name)
    {
        parent::__construct($setting, $file_name);
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

    public function getJobName(){
        return "Import to database";
    }

    public function getJobDetails(){
        $details = array();
        $basic_setting = $this->setting['CSV Import Process Bacic Configuration'];
        $details['File to import'] = $this->file_name;
        $details['File path'] = $basic_setting['FilePath'];
        $details['Processed File Path'] = $basic_setting['ProcessedFilePath'];
        $details['Table Name In DB'] = $basic_setting['TableNameInDB'];
        $details['Settings File Name'] = $this->setting['IniFileName'];
        return $details;
    }
}
