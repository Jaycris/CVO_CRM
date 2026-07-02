<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::with('creator')
            ->latest('published_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('announcements.index', compact('announcements'));
    }
}
