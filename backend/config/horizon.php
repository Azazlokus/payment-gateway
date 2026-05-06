<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:',
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:payments-critical' => 30,
        'redis:payments' => 60,
        'redis:default' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silenced jobs will not show up in the Horizon dashboard. This is useful
    | for jobs that are run frequently and would otherwise pollute the list.
    |
    */

    'silenced' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many data points Horizon will store for the
    | metrics graph. This will determine the span of time that is shown on
    | the metrics dashboard as a moving average.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait for all of the workers to terminate unless the timeout value
    | expires. This is useful when you need to quickly terminate workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue'      => ['default', 'webhooks', 'crypto'],
            'balance'    => 'auto',
            'processes'  => 5,
            'tries'      => 5,
            'timeout'    => 60,
        ],
        'supervisor-payments-critical' => [
            'connection' => 'redis',
            'queue' => ['payments-critical'],
            'balance' => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'tries' => 3,
            'backoff' => [10, 30, 60],
            'timeout' => 60,
            'memory' => 128,
        ],
        'supervisor-payments' => [
            'connection' => 'redis',
            'queue' => ['payments'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'tries' => 3,
            'backoff' => [30, 60, 120],
            'timeout' => 90,
            'memory' => 128,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'tries' => 3,
            'backoff' => 60,
            'timeout' => 60,
            'memory' => 128,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue'      => ['default', 'webhooks', 'crypto'],
                'balance'    => 'auto',
                'processes'  => 5,
                'tries'      => 5,
                'timeout'    => 60,
            ],
            'supervisor-payments-critical' => [
                'minProcesses' => 3,
                'maxProcesses' => 20,
            ],
            'supervisor-payments' => [
                'minProcesses' => 2,
                'maxProcesses' => 10,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 5,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue'      => ['default', 'webhooks', 'crypto'],
                'balance'    => 'simple',
                'processes'  => 3,
                'tries'      => 3,
                'timeout'    => 60,
            ],
            'supervisor-payments-critical' => [
                'minProcesses' => 1,
                'maxProcesses' => 3,
            ],
            'supervisor-payments' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
        ],
    ],

];
