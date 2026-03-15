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

    'ai' => [
        'enabled' => (bool) env('AI_ENABLED', true),
        'base_url' => env('AI_BASE_URL', 'http://127.0.0.1:8080'),
        'hmac_secret' => env('AI_HMAC_SECRET'),
        'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 20),
        'tenant_prefix' => env('AI_TENANT_PREFIX', 'budget-user'),
        'thresholds' => [
            'history_months' => (int) env('AI_HISTORY_MONTHS', 6),
            'result_negative_high_gap' => (float) env('AI_THRESHOLD_RESULT_NEGATIVE_HIGH_GAP', 300),
            'expense_pressure_ratio' => (float) env('AI_THRESHOLD_EXPENSE_PRESSURE_RATIO', 0.85),
            'expected_income_dependency_ratio' => (float) env('AI_THRESHOLD_EXPECTED_INCOME_RATIO', 0.55),
            'expected_income_dependency_high_ratio' => (float) env('AI_THRESHOLD_EXPECTED_INCOME_HIGH_RATIO', 0.75),
            'overdue_high_amount' => (float) env('AI_THRESHOLD_OVERDUE_HIGH_AMOUNT', 400),
            'single_expense_concentration_ratio' => (float) env('AI_THRESHOLD_SINGLE_EXPENSE_RATIO', 0.35),
            'single_expense_concentration_high_ratio' => (float) env('AI_THRESHOLD_SINGLE_EXPENSE_HIGH_RATIO', 0.5),
            'low_buffer_ratio' => (float) env('AI_THRESHOLD_LOW_BUFFER_RATIO', 0.25),
            'low_buffer_high_ratio' => (float) env('AI_THRESHOLD_LOW_BUFFER_HIGH_RATIO', 0.1),
            'mom_deterioration_abs' => (float) env('AI_THRESHOLD_MOM_DETERIORATION_ABS', 150),
            'mom_deterioration_high_abs' => (float) env('AI_THRESHOLD_MOM_DETERIORATION_HIGH_ABS', 350),
            'many_open_items_count' => (int) env('AI_THRESHOLD_MANY_OPEN_ITEMS_COUNT', 12),
            'many_open_items_high_count' => (int) env('AI_THRESHOLD_MANY_OPEN_ITEMS_HIGH_COUNT', 20),
        ],
    ],

];
