<?php
/**
 * Created by PhpStorm.
 * Date: 11/23/18
 * Time: 12:40 AM
 */

namespace App\Ldaplibs;

use App\Jobs\JobInterface;
use Illuminate\Support\Facades\Log;

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
        Log::info("Job type: " . $job->getJobName());
        Log::info("Job details:");
        Log::info(json_encode($job->getJobDetails(), JSON_PRETTY_PRINT));
    }

    /**
     * Push high priority
     */
    public function pushHighPriority()
    {
        // do something
    }

    /**
     * @param $file
     */
    public function pop($file)
    {
        // do something
    }

    /**
     * @return null
     */
    public function getJobInfo()
    {
        return null;
    }
}
