<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LeafMachine2 Microservice
    |--------------------------------------------------------------------------
    |
    | Connection settings for the LeafMachine2 digitisation microservice.
    | LM2_API_KEY is used when Laravel calls the microservice.
    | LM2_CALLBACK_TOKEN is the bearer token the microservice must include
    | when it calls back to Laravel's internal callback endpoint.
    |
    */
    'leafmachine2' => [
        'url'            => env('LM2_SERVICE_URL'),
        'api_key'        => env('LM2_API_KEY'),
        'callback_token' => env('LM2_CALLBACK_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Quality Check (IQC) Microservice
    |--------------------------------------------------------------------------
    |
    | Pre-flight quality checks for uploaded images. Laravel stores uploads,
    | dispatches IQC asynchronously, then accepts/rejects each image from the
    | callback payload before submitting accepted images downstream.
    |
    */
    'image_quality_check' => [
        'url'            => env('IQC_SERVICE_URL'),
        'api_key'        => env('IQC_API_KEY'),
        'callback_token' => env('IQC_CALLBACK_TOKEN'),
        'uploads_dir'    => env('DIGITISATION_UPLOADS_DIR', base_path('uploads')),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Pipeline Microservice
    |--------------------------------------------------------------------------
    |
    | OCR extraction pipeline used after IQC is accepted. Laravel submits the
    | accepted images and receives asynchronous callbacks with per-image
    | llm_verified fields.
    |
    */
    'ocr_pipeline' => [
        'url' => env('OCR_PIPELINE_URL', 'http://127.0.0.1:8002'),
    ],

];
