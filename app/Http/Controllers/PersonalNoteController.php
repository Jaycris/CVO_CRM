<?php

namespace App\Http\Controllers;

use App\Models\PersonalNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonalNoteController extends Controller
{
    public function index(Request $request): View
    {
        $notes = PersonalNote::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('notes.index', compact('notes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        PersonalNote::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('notes.index')->with('success', 'Note added successfully.');
    }

    public function update(Request $request, PersonalNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $note->update($validated);

        return redirect()->route('notes.index')->with('success', 'Note updated successfully.');
    }

    public function destroy(Request $request, PersonalNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted successfully.');
    }
}
