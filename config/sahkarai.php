<?php

return [
    'auth' => [
        // Temporary operational escape hatch for environments without outbound
        // mail. Product access never depends on this flag; it only controls the
        // persisted email verification timestamp.
        'auto_verify_email' => (bool) env('AUTO_VERIFY_EMAIL', false),
    ],
    'ai' => [
        'provider' => env('SAHKAR_AI_PROVIDER', 'deepseek'),
        'interpretation_model' => env('SAHKAR_INTERPRETATION_MODEL', 'deepseek-chat'),
        'chat_model' => env('SAHKAR_CHAT_MODEL', 'deepseek-chat'),
        'prompt_version' => env('SAHKAR_PROMPT_VERSION', '2026-07-16.2'),
        'interpretation_attempts' => (int) env('SAHKAR_INTERPRETATION_ATTEMPTS', 3),
        'context_window_tokens' => (int) env('CONTEXT_WINDOW_TOKEN_THRESHOLD', 16000),
    ],
    'tiers' => [
        'free' => ['monthly_price' => 0, 'monthly_credits' => 0],
        'tier_1' => ['monthly_price' => 99900, 'monthly_credits' => 0],
        'tier_2' => ['monthly_price' => 149900, 'monthly_credits' => 200],
        'tier_3' => ['monthly_price' => 249900, 'monthly_credits' => 200],
    ],
    'credits' => [
        'topup_url' => env('CREDIT_TOPUP_URL'),
    ],
    'realtime' => [
        // Sent to authenticated Inertia clients at request time. Keeping these
        // values out of the Vite build makes one immutable image deployable to
        // every environment without rebuilding its frontend assets.
        'key' => env('REVERB_PUBLIC_APP_KEY', env('REVERB_APP_KEY')),
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST')),
        'port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 443)),
        'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'https')),
    ],
    'ingestion' => [
        'storage_disk' => env('REGULATORY_STORAGE_DISK', 'local'),
        'backfill_months' => (int) env('REGULATORY_BACKFILL_MONTHS', 12),
        'max_document_bytes' => (int) env('REGULATORY_MAX_DOCUMENT_BYTES', 52428800),
        'user_agent' => env('REGULATORY_USER_AGENT', 'SahkarAI/1.0 (+https://app.168.144.26.122.sslip.io)'),
        'sources' => [
            'rbi' => ['feed_url' => env('RBI_FEED_URL')],
            'income_tax' => ['feed_url' => env(
                'INCOME_TAX_FEED_URL',
                'https://wmstatic-prd.incometaxindia.gov.in/circular-rss-feed/-/asset_publisher/bxhj/rss',
            )],
            'gst' => ['feed_url' => env('GST_FEED_URL')],
        ],
    ],
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
        'plans' => [
            'tier_1' => env('RAZORPAY_TIER_1_PLAN_ID'),
            'tier_2' => env('RAZORPAY_TIER_2_PLAN_ID'),
            'tier_3' => env('RAZORPAY_TIER_3_PLAN_ID'),
        ],
    ],
];
