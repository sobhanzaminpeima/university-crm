<?php

namespace App\Observers;

use App\Models\Notification;
use App\Services\TelegramBotService;

class NotificationObserver
{
    public function created(Notification $notification): void
    {
        $bot = app(TelegramBotService::class);
        if (!$bot->isConfigured()) {
            return;
        }
        $text = "🔔 <b>{$notification->title}</b>";
        if (!empty($notification->body)) {
            $text .= "\n{$notification->body}";
        }
        $bot->notifyUser((int) $notification->user_id, $text);
    }
}
