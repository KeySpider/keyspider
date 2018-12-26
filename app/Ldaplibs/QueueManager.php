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
    public const INI_CONFIGS = "ini_configs";

    public static function getQueueSettings()
    {
        $queueSettings = parse_ini_file(storage_path("" . self::INI_CONFIGS . "/QueueSettings.ini"), true);
        return $queueSettings['Queue Settings'];
    }

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
