<?php

namespace App\Http\Controllers;

use App\Models\TelegramLink;
use App\Services\TelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TelegramController extends Controller
{
    private const PLACEHOLDER_SECRET = 'replace-with-a-long-random-url-secret';

    public function settings(Request $request, TelegramBotService $bot): View
    {
        $user = $this->authUser($request);
        $telegramLink = TelegramLink::query()->where('user_id', $user->id)->first();
        $botUsername = $bot->botUsername();
        $setup = $this->canManageBot($user) ? $this->setupStatus($bot) : null;

        return view('telegram.settings', compact('user', 'telegramLink', 'botUsername', 'setup'));
    }

    public function generateCode(Request $request, TelegramBotService $bot): RedirectResponse
    {
        $user = $this->authUser($request);
        $code = $bot->generateLinkCode($user);

        return back()->with('telegram_code', $code)->with('telegram_code_expires', now()->addMinutes(10)->toIso8601String());
    }

    public function unlink(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        TelegramLink::query()->where('user_id', $user->id)->delete();
        $this->audit($request, 'telegram.unlink', 'user', $user->id);

        return back()->with('success', 'Telegram account unlinked.');
    }

    public function testMessage(Request $request, TelegramBotService $bot): RedirectResponse
    {
        $user = $this->authUser($request);
        $link = TelegramLink::query()->where('user_id', $user->id)->first();
        if (!$link) {
            return back()->withErrors(['telegram' => 'Link your Telegram account first.']);
        }
        $ok = $bot->sendMessage($link->chat_id, "✅ <b>Test message</b>\nVertue CRM can reach you on Telegram. (".now()->format('Y-m-d H:i').')');

        return $ok
            ? back()->with('success', 'Test message sent. Check Telegram.')
            : back()->withErrors(['telegram' => 'Telegram did not accept the message. If you blocked the bot, open it and press Start, then try again.']);
    }

    // ---- Bot setup (platform-wide: one bot serves every tenant, so only the super admin manages it) ----

    public function connectWebhook(Request $request, TelegramBotService $bot): RedirectResponse
    {
        $this->authorizeBotManagement($request);
        $problem = $this->webhookPrerequisiteProblem($bot);
        if ($problem !== null) {
            return back()->withErrors(['telegram' => $problem]);
        }
        $result = $bot->setWebhook();
        if ($result['ok']) {
            $bot->registerCommands();
            Cache::forget('telegram-bot-username');
            $this->audit($request, 'telegram.webhook.connect', 'system', 0);

            return back()->with('success', 'Webhook connected and command menu registered. Send /start to the bot to try it.');
        }

        return back()->withErrors(['telegram' => 'Telegram rejected the webhook: '.($result['description'] ?? 'unknown error')]);
    }

    public function disconnectWebhook(Request $request, TelegramBotService $bot): RedirectResponse
    {
        $this->authorizeBotManagement($request);
        $result = $bot->deleteWebhook();
        $this->audit($request, 'telegram.webhook.disconnect', 'system', 0);

        return $result['ok']
            ? back()->with('success', 'Webhook disconnected. The bot will not answer until it is connected again.')
            : back()->withErrors(['telegram' => 'Could not disconnect: '.($result['description'] ?? 'unknown error')]);
    }

    public function registerCommands(Request $request, TelegramBotService $bot): RedirectResponse
    {
        $this->authorizeBotManagement($request);

        return $bot->registerCommands()
            ? back()->with('success', 'Command menu registered in Telegram.')
            : back()->withErrors(['telegram' => 'Could not register the command menu. Check the bot token.']);
    }

    private function canManageBot($user): bool
    {
        return $user->role_slug === 'super_admin';
    }

    private function authorizeBotManagement(Request $request): void
    {
        abort_unless($this->canManageBot($this->authUser($request)), 403);
    }

    private function webhookPrerequisiteProblem(TelegramBotService $bot): ?string
    {
        if (!$bot->isConfigured()) {
            return 'Add TELEGRAM_BOT_TOKEN to the .env file first.';
        }
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '' || $secret === self::PLACEHOLDER_SECRET || strlen($secret) < 24) {
            return 'Set TELEGRAM_WEBHOOK_SECRET in .env to a random value of at least 24 characters (a suggestion is shown on this page).';
        }
        if (!str_starts_with((string) config('app.url'), 'https://')) {
            return 'APP_URL must start with https:// — Telegram only delivers webhooks over HTTPS.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function setupStatus(TelegramBotService $bot): array
    {
        $secret = (string) config('services.telegram.webhook_secret');
        $me = $bot->isConfigured() ? $bot->getMe() : ['ok' => false, 'description' => null, 'result' => null];
        $info = $me['ok'] ? $bot->getWebhookInfo() : ['ok' => false, 'result' => null];
        $current = (string) ($info['result']['url'] ?? '');
        $expected = $bot->webhookUrl();

        return [
            'token' => $bot->isConfigured(),
            'token_valid' => (bool) $me['ok'],
            'token_error' => $me['ok'] ? null : ($me['description'] ?? null),
            'bot_username' => $me['result']['username'] ?? null,
            'bot_name' => $me['result']['first_name'] ?? null,
            'secret_ok' => $secret !== '' && $secret !== self::PLACEHOLDER_SECRET && strlen($secret) >= 24,
            'header_secret' => (string) config('services.telegram.webhook_header_secret') !== '',
            'https' => str_starts_with((string) config('app.url'), 'https://'),
            'app_url' => (string) config('app.url'),
            'webhook_connected' => $current !== '' && $expected !== null && hash_equals($expected, $current),
            'webhook_other' => $current !== '' && ($expected === null || !hash_equals($expected, $current)),
            'pending_updates' => (int) ($info['result']['pending_update_count'] ?? 0),
            'last_error' => $info['result']['last_error_message'] ?? null,
            'last_error_at' => isset($info['result']['last_error_date']) ? date('Y-m-d H:i', (int) $info['result']['last_error_date']) : null,
            'suggested_secret' => bin2hex(random_bytes(24)),
        ];
    }
}
