<?php

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Jobs;

use App\Ldaplibs\Extract\DBExtractor;
use App\Ldaplibs\QueueManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DBToSFExtractorJob extends DBExtractor implements ShouldQueue, JobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $queueSettings;

    /**
     * Create a new job instance.
     *
     * @param $setting
     */
    public $tries = 5;
    public $timeout = 120;

    public function __construct($setting)
    {
        parent::__construct($setting);
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
        parent::processExtractToSF();
    }

    /**
     * Get job name.
     * @return string
     */
    public function getJobName()
    {
        return "Extract from database";
    }

    /**
     * Detail job
     * @return array
     */
    public function getJobDetails()
    {
        $setting = $this->setting;
        $details = [];
        $details['Conversion'] = $setting[self::OUTPUT_PROCESS_CONVERSION]['output_conversion'];
        $details['Extract table'] = $setting[self::EXTRACTION_CONFIGURATION]['ExtractionTable'];
        $details['Extract condition'] = $setting[self::EXTRACTION_CONDITION];
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
