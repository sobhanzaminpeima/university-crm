<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramBotService $bot): Response
    {
        if (!hash_equals((string) config('services.telegram.webhook_secret'), $secret)) {
            abort(403);
        }
        $headerSecret = (string) config('services.telegram.webhook_header_secret');
        if ($headerSecret !== '' && !hash_equals($headerSecret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            abort(403);
        }

        $update = $request->all();
        if (!empty($update)) {
            $bot->handleUpdate($update);
        }

        return response('ok');
    }
}
