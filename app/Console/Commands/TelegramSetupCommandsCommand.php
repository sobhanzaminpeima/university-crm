<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetupCommandsCommand extends Command
{
    protected $signature = 'telegram:setup-commands';
    protected $description = 'Register the bot command list with Telegram (shows in the "/" menu)';

    public function handle(TelegramBotService $bot): int
    {
        if (!$bot->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return self::FAILURE;
        }
        $bot->registerCommands();
        $this->info('Telegram command list registered.');
        return self::SUCCESS;
    }
}
