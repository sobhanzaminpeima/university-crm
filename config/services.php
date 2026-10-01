<?php

return [
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_header_secret' => env('TELEGRAM_WEBHOOK_HEADER_SECRET'),
    ],
];
