<?php

/*
|--------------------------------------------------------------------------
| Environment values read by package and app code
|--------------------------------------------------------------------------
|
| Code outside config/ must not call env(): once `php artisan config:cache`
| has run, env() returns null everywhere except config files, and each of
| these would silently fall back to its default (a Qdrant or HTR service URL
| pointing at localhost, ORCID with no credentials). Code reads
| config('ahg-env.<key>') instead and keeps its own default with `??`, so
| behaviour is the same with or without the config cache.
|
| Deliberately no defaults here - a null means "not set", and each caller
| decides what that means, exactly as it did with env().
*/

return [
    'ahg_schema_cache' => env('AHG_SCHEMA_CACHE'),
    'ahg_tenant_id' => env('AHG_TENANT_ID'),
    'donut_service_url' => env('DONUT_SERVICE_URL'),
    'elasticsearch_host' => env('ELASTICSEARCH_HOST'),
    'force_root_url' => env('FORCE_ROOT_URL'),
    'heratio_tts_endpoint' => env('HERATIO_TTS_ENDPOINT'),
    'heratio_tts_key' => env('HERATIO_TTS_KEY'),
    'htr_service_url' => env('HTR_SERVICE_URL'),
    'openric_admin_email' => env('OPENRIC_ADMIN_EMAIL'),
    'openric_import_max_rows' => env('OPENRIC_IMPORT_MAX_ROWS'),
    'openric_repository_name' => env('OPENRIC_REPOSITORY_NAME'),
    'openric_upload_max_bytes' => env('OPENRIC_UPLOAD_MAX_BYTES'),
    'orcid_api_base' => env('ORCID_API_BASE'),
    'orcid_base' => env('ORCID_BASE'),
    'orcid_client_id' => env('ORCID_CLIENT_ID'),
    'orcid_client_secret' => env('ORCID_CLIENT_SECRET'),
    'orcid_redirect_uri' => env('ORCID_REDIRECT_URI'),
    'qdrant_url' => env('QDRANT_URL'),
    'ric_fuseki_pass' => env('RIC_FUSEKI_PASS'),
    'ric_fuseki_user' => env('RIC_FUSEKI_USER'),
    'sharepoint_ops_email' => env('SHAREPOINT_OPS_EMAIL'),
    'workbench_notifications_inbox' => env('WORKBENCH_NOTIFICATIONS_INBOX'),
];
