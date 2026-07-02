<?php

namespace App\Http\Controllers;

use App\Models\CalendarTodo;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarTodoController extends Controller
{
    public function index(Request $request): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))
            ->startOfMonth();
        $calendarStart = $month->copy()->startOfWeek();
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek();

        $todos = CalendarTodo::where('user_id', $request->user()->id)
            ->whereBetween('due_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
            ->orderBy('due_date')
            ->orderBy('due_time')
            ->get()
            ->groupBy(fn (CalendarTodo $todo) => $todo->due_date->toDateString());

        $upcomingTodos = CalendarTodo::where('user_id', $request->user()->id)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereNull('completed_at')
            ->orderBy('due_date')
            ->orderBy('due_time')
            ->take(8)
            ->get();

        return view('calendar.index', [
            'month' => $month,
            'calendarStart' => $calendarStart,
            'calendarEnd' => $calendarEnd,
            'todos' => $todos,
            'upcomingTodos' => $upcomingTodos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['required', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
        ]);

        CalendarTodo::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('calendar.index', ['month' => Carbon::parse($validated['due_date'])->format('Y-m')])
            ->with('success', 'To-do added to calendar.');
    }

    public function update(Request $request, CalendarTodo $todo): RedirectResponse
    {
        abort_unless($todo->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['required', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
        ]);

        $todo->update($validated);

        return redirect()
            ->route('calendar.index', ['month' => Carbon::parse($validated['due_date'])->format('Y-m')])
            ->with('success', 'To-do updated successfully.');
    }

    public function toggle(Request $request, CalendarTodo $todo): RedirectResponse
    {
        abort_unless($todo->user_id === $request->user()->id, 403);

        $todo->update([
            'completed_at' => $todo->completed_at ? null : now(),
        ]);

        return back()->with('success', $todo->completed_at ? 'To-do marked complete.' : 'To-do reopened.');
    }

    public function destroy(Request $request, CalendarTodo $todo): RedirectResponse
    {
        abort_unless($todo->user_id === $request->user()->id, 403);

        $todo->delete();

        return back()->with('success', 'To-do deleted successfully.');
    }
}
