<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
        ],

        'summary' => [
            'driver' => 'custom',
            'via' => App\Logging\CreateSummaryLogger::class,
            'path' => storage_path('logs/summary/summary.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'detail' => [
            'driver' => 'custom',
            'via' => App\Logging\CreateDetailLogger::class,
            'path' => storage_path('logs//detail/detail.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'validation' => [
            'driver' => 'custom',
            'via' => App\Logging\CreateValidationLogger::class,
            'path' => storage_path('logs/validation/validation.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'faild' => [
            'driver' => 'custom',
            'via' => App\Logging\CreateFaildLogger::class,
            'path' => storage_path('logs/error/error.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        'import' => [
            'driver' => 'daily',
            'path' => storage_path('logs/import.log'),
            'level' => 'debug',
        ],

        'export' => [
            'driver' => 'daily',
            'path' => storage_path('logs/export.log'),
            'level' => 'debug',
        ],

        'delivery' => [
            'driver' => 'daily',
            'path' => storage_path('logs/delivery.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'papertrail' => [
            'driver'  => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],
    ],

];
