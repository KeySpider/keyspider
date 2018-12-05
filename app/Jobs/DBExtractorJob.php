<?php

namespace App\Jobs;

use App\Ldaplibs\Delivery\DBExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DBExtractorJob extends DBExtractor implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($setting, $file_name)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    }

    public function get_job_name(){
        return "Extract from database";
    }

    public function get_job_details(){
        return [];
    }
}
