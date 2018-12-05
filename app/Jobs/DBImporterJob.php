<?php

namespace App\Jobs;

use App\Ldaplibs\Import\DBImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DBImporterJob extends DBImporter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
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
}
