<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramBotService $bot): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');
        if ($expected === '' || !hash_equals($expected, $secret)) {
            abort(403);
        }
        $headerSecret = (string) config('services.telegram.webhook_header_secret');
        if ($headerSecret !== '' && !hash_equals($headerSecret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            abort(403);
        }

        $update = $request->all();
        if (empty($update)) {
            return response('ok');
        }

        // Telegram re-delivers an update until it receives 200; process each update_id only once.
        $updateId = (int) ($update['update_id'] ?? 0);
        if ($updateId > 0 && !Cache::add('telegram-update:'.$updateId, 1, now()->addDay())) {
            return response('ok');
        }

        try {
            $bot->handleUpdate($update);
        } catch (\Throwable $e) {
            // Always acknowledge: a 500 makes Telegram retry the same update forever and blocks newer ones.
            Log::error('Telegram update handling failed', [
                'update_id' => $updateId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return response('ok');
    }
}
