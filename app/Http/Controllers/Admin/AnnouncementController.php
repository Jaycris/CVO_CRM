<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AnnouncementController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        $announcements = Announcement::with('creator')
            ->latest('published_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'email_sent' => false,
            'published_at' => now(),
        ]);

        $users = User::whereNull('suspended_at')->get();

        Notification::send($users, new AnnouncementNotification($announcement));

        if ($request->boolean('send_email')) {
            try {
                foreach ($users as $user) {
                    Mail::to($user->email)->send(new AnnouncementMail($announcement, $user));
                }

                $announcement->update(['email_sent' => true]);
            } catch (TransportExceptionInterface $exception) {
                report($exception);

                return redirect()
                    ->route('admin.announcements.index')
                    ->with('error', 'Announcement posted, but the email copy could not be sent. Please check the mail connection.');
            }
        }

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement posted successfully.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            request()->user()?->role?->name === 'Admin'
            || request()->user()?->hasPermission('manage_announcements'),
            403
        );
    }
}
