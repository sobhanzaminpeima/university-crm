<?php

namespace App\Console\Commands;

use App\Models\TelegramUpdatesOffset;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Fetch one batch of updates and exit (useful for testing)}';
    protected $description = 'Long-poll Telegram for updates (local/dev use — production should use a webhook instead)';

    public function handle(TelegramBotService $bot): int
    {
        if (!$bot->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $webhook = $bot->getWebhookInfo();
        if ($webhook['ok'] && !empty($webhook['result']['url'])) {
            $this->error('A webhook is active for this bot, so polling is not allowed by Telegram.');
            $this->line('Remove it first from CRM → Telegram Bot → "Disconnect webhook" (or use the webhook in production).');
            return self::FAILURE;
        }

        $bot->registerCommands();
        $this->info('Telegram bot polling started. Press Ctrl+C to stop.');
        $offsetRow = TelegramUpdatesOffset::query()->first() ?: TelegramUpdatesOffset::query()->create(['last_update_id' => 0]);
        $failures = 0;

        while (true) {
            try {
                $updates = $bot->getUpdates($offsetRow->last_update_id + 1);
                $failures = 0;
            } catch (\Throwable $e) {
                $failures++;
                $wait = min(60, 2 ** min($failures, 6));
                $this->warn("getUpdates failed ({$e->getMessage()}). Retrying in {$wait}s…");
                if ((int) $e->getCode() === 409) {
                    $this->error('Conflict: another poller or a webhook is using this bot.');
                }
                sleep($wait);
                continue;
            }

            foreach ($updates as $update) {
                try {
                    $bot->handleUpdate($update);
                } catch (\Throwable $e) {
                    $this->error('Update '.($update['update_id'] ?? '?').' failed: '.$e->getMessage());
                }
                // Advance even on failure so one bad update cannot block the queue.
                $offsetRow->last_update_id = (int) $update['update_id'];
                $offsetRow->save();
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }
        }
    }
}
