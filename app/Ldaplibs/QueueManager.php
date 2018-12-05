<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:40 AM
 */

namespace App\Ldaplibs;


use App\Jobs\DBImporterJob;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class QueueManager
{

    public function push($job)
    {
        dispatch($job);
//        Queue::after(function (JobProcessed $event) {
////            $this->assertTrue($event->job->isReleased());
//            Log::alert('after done');
//        });

        Log::alert("---------------Job logger---------------");
        Log::info("Job type:");
        Log::info($job->get_job_name());
        Log::info("Job details:");
        Log::info(json_encode($job->get_job_details(), JSON_PRETTY_PRINT));
    }

    public function push_high_priority(){

    }

    public function pop($file)
    {

    }

    public function get_job_info(){
        return null;
    }

}