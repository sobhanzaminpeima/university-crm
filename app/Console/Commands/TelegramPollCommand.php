<?php

namespace App\Console\Commands;

use App\Models\TelegramUpdatesOffset;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Long-poll Telegram for updates (local/dev use — production should use a webhook instead)';

    public function handle(TelegramBotService $bot): int
    {
        if (!$bot->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $bot->registerCommands();
        $this->info('Telegram bot polling started. Press Ctrl+C to stop.');
        $offsetRow = TelegramUpdatesOffset::query()->first() ?: TelegramUpdatesOffset::query()->create(['last_update_id' => 0]);

        while (true) {
            $updates = $bot->getUpdates($offsetRow->last_update_id + 1);
            foreach ($updates as $update) {
                $bot->handleUpdate($update);
                $offsetRow->last_update_id = (int) $update['update_id'];
                $offsetRow->save();
            }
        }
    }
}
