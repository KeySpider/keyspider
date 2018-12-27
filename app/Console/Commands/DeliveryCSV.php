<?php
/**
 * Project = Key Spider
 * Year = 2019
 * Organization = Key Spider Japan LLC
 */

namespace App\Console\Commands;

use App\Jobs\DeliveryJob;
use App\Ldaplibs\Delivery\DeliveryQueueManager;
use App\Ldaplibs\Delivery\DeliverySettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeliveryCSV extends Command
{
    public const CONFIGURATION = 'CSV Import Process Basic Configuration';
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
        $scheduleDeliveryExecution = (new DeliverySettingsManager())->getScheduleDeliveryExecution();
        if ($scheduleDeliveryExecution) {
            Log::info(json_encode($scheduleDeliveryExecution, JSON_PRETTY_PRINT));
            foreach ($scheduleDeliveryExecution as $timeExecutionString => $settingOfTimeExecution) {
                $this->deliveryDataForTimeExecution($settingOfTimeExecution);
            }
        } else {
            Log::error("Can not run delivery schedule, getting error from config ini files");
        }
    }

    public function deliveryDataForTimeExecution($settings)
    {
        $queue = new DeliveryQueueManager();
        foreach ($settings as $dataSchedule) {
            $setting = $dataSchedule['setting'];
            $delivery = new DeliveryJob($setting);
            $queue->push($delivery);
        }
    }
}
