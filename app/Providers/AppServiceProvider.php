<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Queue::before(function (JobProcessing $event) {
            Log::info('-------------------------------before processing task-------------------------------');
//            Log::debug(json_encode($event->job->payload(), JSON_PRETTY_PRINT));
        });

        Queue::after(function (JobProcessed $event) {
            Log::info('Job processed info: ');
            Log::info('-------------------------------after processed task-------------------------------');
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
