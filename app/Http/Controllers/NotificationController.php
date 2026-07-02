<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        if (! $request->expectsJson()) {
            $notifications = $request->user()
                ->notifications()
                ->latest()
                ->paginate(10)
                ->withQueryString();

            return view('notifications.index', compact('notifications'));
        }

        return response()->json($this->notificationPayload($request));
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $record = $request->user()
            ->notifications()
            ->where('id', $notification)
            ->firstOrFail();

        $record->markAsRead();

        if ($request->expectsJson()) {
            return response()->json($this->notificationPayload($request));
        }

        return redirect($record->data['url'] ?? url()->previous());
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json($this->notificationPayload($request));
        }

        return back();
    }

    private function notificationPayload(Request $request): array
    {
        return [
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $request->user()
                ->notifications()
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? null,
                    'author_name' => $notification->data['author_name'] ?? 'Client',
                    'book_title' => $notification->data['book_title'] ?? 'Untitled',
                    'url' => $notification->data['url'] ?? url()->previous(),
                    'read' => ! is_null($notification->read_at),
                    'created_at' => $notification->created_at?->diffForHumans(),
                ])
                ->values(),
        ];
    }
}
