<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:40 AM
 */

namespace App\Ldaplibs;

use App\Jobs\DBImporterJob;
use App\Jobs\JobInterface;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class QueueManager
{

    /**
     * @param JobInterface $job
     * <p> Job to be pushed </p>
     * <p> Log job information when pushing
     */
    public function push(JobInterface $job)
    {
        dispatch($job);
        Log::alert("---------------Job logger---------------");
        Log::info("Job type: ". $job->getJobName());
        Log::info("Job details:");
        Log::info(json_encode($job->getJobDetails(), JSON_PRETTY_PRINT));
    }

    public function push_high_priority(){

    }

    public function pop($file)
    {

    }

    public function getJobInfo(){
        return null;
    }

}
