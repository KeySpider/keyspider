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

namespace App\Ldaplibs;

use App\Jobs\JobInterface;
use Illuminate\Support\Facades\Log;

class QueueManager
{
    public const INI_CONFIGS = 'ini_configs';

    public static function getQueueSettings()
    {
        $queueSettings = parse_ini_file(storage_path('' . self::INI_CONFIGS . '/QueueSettings.ini'), true);
        return $queueSettings['Queue Settings'];
    }

    /**
     * @param JobInterface $job
     * <p> Job to be pushed </p>
     * <p> Log job information when pushing
     */
    public function push(JobInterface $job): void
    {
        dispatch($job);
        Log::info('---------------Job logger---------------');
        Log::info('Job type: ' . $job->getJobName());
        Log::info('Job details:');
//        Log::info(json_encode($job->getJobDetails(), JSON_PRETTY_PRINT));
    }

    /**
     * Push high priority
     */
    public function pushHighPriority(): void
    {
        // do something
    }

    /**
     * @param $file
     */
    public function pop($file): void
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
