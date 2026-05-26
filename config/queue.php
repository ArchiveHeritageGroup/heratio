<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (Issue #672 Phase 2)
    |--------------------------------------------------------------------------
    |
    | Named per-minute caps consulted by App\Jobs\Middleware\RateLimited.
    | A Job's middleware() returns `new RateLimited('htr_extract', 10)` and
    | the integer fallback (10) is overridden if a key by that name exists
    | here. Keys are arbitrary short strings - keep them stable so deploy
    | targets can tune without redeploying code.
    |
    | The limiter requires an atomic cache store (redis or database) when
    | more than one worker process is sharing the counter.
    |
    */

    'rate_limits' => [
        // AI gateway / heavy inference - keep below upstream throughput.
        'htr_extract'      => (int) env('QUEUE_RATE_LIMIT_HTR_EXTRACT', 10),
        'llm_complete'     => (int) env('QUEUE_RATE_LIMIT_LLM_COMPLETE', 60),
        'ner_extract'      => (int) env('QUEUE_RATE_LIMIT_NER_EXTRACT', 30),
        'summarize'        => (int) env('QUEUE_RATE_LIMIT_SUMMARIZE', 30),
        'translate'        => (int) env('QUEUE_RATE_LIMIT_TRANSLATE', 30),

        // External transactional fan-out.
        'email_send'       => (int) env('QUEUE_RATE_LIMIT_EMAIL_SEND', 100),
        'sms_send'         => (int) env('QUEUE_RATE_LIMIT_SMS_SEND', 30),
        'webhook_dispatch' => (int) env('QUEUE_RATE_LIMIT_WEBHOOK', 60),

        // Background indexing / housekeeping.
        'es_reindex'       => (int) env('QUEUE_RATE_LIMIT_ES_REINDEX', 120),
        'thumbnail_gen'    => (int) env('QUEUE_RATE_LIMIT_THUMBNAIL', 60),
    ],

];
