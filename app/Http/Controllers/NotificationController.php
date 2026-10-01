<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $perPage = $this->perPage($request);
        $notifications = Notification::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('notifications.index', compact('notifications', 'perPage'));
    }

    public function markRead(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $notification = Notification::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('user_id', $user->id)
            ->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        Notification::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
