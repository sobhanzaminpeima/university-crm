<?php

namespace App\Http\Controllers;

use App\Models\TelegramLink;
use App\Services\TelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramController extends Controller
{
    public function settings(Request $request): View
    {
        $user = $this->authUser($request);
        $telegramLink = TelegramLink::query()->where('user_id', $user->id)->first();

        return view('telegram.settings', compact('user', 'telegramLink'));
    }

    public function generateCode(Request $request, TelegramBotService $bot): RedirectResponse
    {
        $user = $this->authUser($request);
        $code = $bot->generateLinkCode($user);

        return back()->with('telegram_code', $code);
    }

    public function unlink(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        TelegramLink::query()->where('user_id', $user->id)->delete();
        $this->audit($request, 'telegram.unlink', 'user', $user->id);

        return back()->with('success', 'Telegram account unlinked.');
    }
}
