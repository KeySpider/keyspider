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

namespace App\Console\Commands;

use App\Jobs\DeliveryJob;
use App\Ldaplibs\SettingsManager;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeliveryCSV extends Command
{
    public const CONFIGURATION = 'CSV Import Process Basic Configuration';
    public const DELIVERRY_CSV_CONFIG = 'CSV Output Process Configration';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:delivery';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reader setting import file and process it';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $sections = (new SettingsManager())->getAllConfigsFromKeyspiderIni();
        
        if (array_key_exists(self::DELIVERRY_CSV_CONFIG, $sections)) {
            $scheduleDeliveryExecution = (new DeliverySettingsManager())->getScheduleDeliveryExecution();
            if ($scheduleDeliveryExecution) {
                Log::info(json_encode($scheduleDeliveryExecution, JSON_PRETTY_PRINT));
                foreach ($scheduleDeliveryExecution as $timeExecutionString => $settingOfTimeExecution) {
                    $this->deliveryDataForTimeExecution($settingOfTimeExecution);
                }
            } else {
                Log::error('Can not run delivery schedule, getting error from config ini files');
            }
        } else {
            Log::Info('nothing to do.');
        }
    }

    public function deliveryDataForTimeExecution($settings): void
    {
        $queue = new DeliveryQueueManager();
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $delivery = new DeliveryJob($setting);
            $queue->push($delivery);
        }
    }
}
