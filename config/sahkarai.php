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
        'interpretation_model' => env('SAHKAR_INTERPRETATION_MODEL', 'deepseek-v4-flash'),
        'chat_model' => env('SAHKAR_CHAT_MODEL', 'deepseek-v4-flash'),
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
    'video' => [
        'enabled' => (bool) env('EXPLAINER_VIDEO_ENABLED', false),
        'storage_disk' => env('EXPLAINER_VIDEO_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'storage_prefix' => trim((string) env('EXPLAINER_VIDEO_STORAGE_PREFIX', 'explainer-videos'), '/'),
        'queue' => env('EXPLAINER_VIDEO_QUEUE', 'video'),
        'queue_connection' => env('EXPLAINER_VIDEO_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'credits' => (int) env('EXPLAINER_VIDEO_CREDITS', 10),
        'node_binary' => env('EXPLAINER_VIDEO_NODE_BINARY', 'node'),
        'render_timeout' => (int) env('EXPLAINER_VIDEO_RENDER_TIMEOUT', 3600),
        'quality' => env('EXPLAINER_VIDEO_QUALITY', 'high'),
        'fps' => (int) env('EXPLAINER_VIDEO_FPS', 30),
        'narration_driver' => env('EXPLAINER_VIDEO_NARRATION_DRIVER', 'elevenlabs'),
        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'voice_id' => env('ELEVENLABS_VOICE_ID', 'rAsfH6d68tmh0XRGXp4D'),
            'model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_multilingual_v2'),
        ],
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
        'minimum_extracted_characters' => (int) env('REGULATORY_MINIMUM_EXTRACTED_CHARACTERS', 1),
        'user_agent' => env('REGULATORY_USER_AGENT', 'SahkarAI/1.0 (+https://app.168.144.27.66.sslip.io)'),
        'browser_user_agent' => env('REGULATORY_BROWSER_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/128 Safari/537.36'),
        'sources' => [
            'rbi' => [
                'notifications_url' => env('RBI_NOTIFICATIONS_URL', 'https://www.rbi.org.in/Scripts/NotificationUser.aspx'),
                'years' => (int) env('RBI_OBSERVER_YEARS', 2),
            ],
            'income_tax' => ['feed_url' => env(
                'INCOME_TAX_FEED_URL',
                'https://wmstatic-prd.incometaxindia.gov.in/circular-rss-feed/-/asset_publisher/bxhj/rss',
            )],
            'gst' => ['feed_url' => env('GST_FEED_URL')],
            'cbic' => [
                'years' => (int) env('CBIC_OBSERVER_YEARS', 2),
                'base_url' => env('CBIC_BASE_URL', 'https://taxinformation.cbic.gov.in'),
            ],
            'nabard' => [
                'circulars_url' => env('NABARD_CIRCULARS_URL', 'https://www.nabard.org/circulars.aspx?cid=504&id=24'),
                'years' => (int) env('NABARD_OBSERVER_YEARS', 2),
            ],
        ],
        'kimi' => [
            'enabled' => (bool) env('KIMI_OCR_ENABLED', false),
            'api_key' => env('KIMI_API_KEY'),
            'base_url' => rtrim((string) env('KIMI_BASE_URL', 'https://api.moonshot.ai/v1'), '/'),
            'timeout' => (int) env('KIMI_OCR_TIMEOUT', 180),
            'max_attempts' => (int) env('KIMI_OCR_ATTEMPTS', 3),
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
