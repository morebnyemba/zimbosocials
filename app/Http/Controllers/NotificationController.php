<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate(30);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Notification $notification): RedirectResponse
    {
        if ((int) $notification->user_id !== (int) Auth::id()) {
            abort(403);
        }
        $notification->markAsRead();

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        $userId = (int) Auth::id();

        Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Bulk update bypasses Eloquent model events — manually bust cache
        Cache::forget("user:{$userId}:unread_notifications");

        return back()->with('success', 'All notifications marked as read.');
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Notification::unreadCountFor((int) Auth::id()),
        ]);
    }
}
