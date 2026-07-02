<?php

namespace App\Http\Controllers;

use App\Models\ProductionProject;
use App\Models\ProductionTask;
use App\Models\User;
use App\Notifications\ProductionProjectCompletedNotification;
use App\Notifications\ProductionTaskAssignedNotification;
use App\Notifications\ProductionTaskDoneNotification;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ProductionProjectController extends Controller
{
    public function tasks(Request $request): View
    {
        return $this->taskList($request, false);
    }

    public function completedTasks(Request $request): View
    {
        return $this->taskList($request, true);
    }

    public function taskTracker(Request $request): View
    {
        abort_unless($this->canViewTaskTracker($request), 403);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');
        $tracker = $request->query('tracker', 'all');
        $allowedTrackers = $this->allowedTrackers($request);

        $tasks = ProductionTask::with([
            'project.endorsement.agent',
            'project.endorsement.paymentRecord',
            'project.brand',
            'project.fulfillmentOfficer',
            'assignedUser',
            'items',
        ])
            ->whereHas('project', function ($query) use ($request, $tracker, $allowedTrackers) {
                BrandScope::apply($query, $request->user());
                $this->applyFulfillmentVisibilityScope($query, $request);

                if ($tracker !== 'all' && in_array($tracker, $allowedTrackers, true)) {
                    $query->where('tracker_type', $tracker);
                }
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('instructions', 'like', "%{$search}%")
                        ->orWhere('result_link', 'like', "%{$search}%")
                        ->orWhereHas('items', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('assignedUser', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('project.endorsement', function ($query) use ($search) {
                            $query->where('author_name', 'like', "%{$search}%")
                                ->orWhere('book_title', 'like', "%{$search}%")
                                ->orWhere('services', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('production.task-tracker', [
            'tasks' => $tasks,
            'search' => $search,
            'status' => $status,
            'tracker' => $tracker,
            'allowedTrackers' => $allowedTrackers,
        ]);
    }

    private function taskList(Request $request, bool $completed): View
    {
        abort_unless($this->canViewOwnProductionTasks($request), 403);

        $search = trim((string) $request->query('search', ''));
        $status = $completed ? 'all' : $request->query('status', 'all');

        $tasks = ProductionTask::with(['project.endorsement.agent', 'project.endorsement.paymentRecord', 'project.brand', 'project.fulfillmentOfficer', 'assignedUser', 'items'])
            ->whereHas('project', fn ($query) => BrandScope::apply($query, $request->user()))
            ->where('assigned_to', $request->user()->id)
            ->when(
                $completed,
                fn ($query) => $query->where('status', 'fulfilled'),
                fn ($query) => $query->where('status', '!=', 'fulfilled')
            )
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('status', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('instructions', 'like', "%{$search}%")
                        ->orWhereHas('items', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('project.endorsement', function ($query) use ($search) {
                            $query->where('author_name', 'like', "%{$search}%")
                                ->orWhere('book_title', 'like', "%{$search}%")
                                ->orWhere('services', 'like', "%{$search}%")
                                ->orWhere('contact_number', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $summaryQuery = ProductionTask::where('assigned_to', $request->user()->id)
            ->whereHas('project', fn ($query) => BrandScope::apply($query, $request->user()));

        return view('production.tasks', [
            'tasks' => $tasks,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
                'in_progress' => (clone $summaryQuery)->where('status', 'in_progress')->count(),
                'fulfilled' => (clone $summaryQuery)->where('status', 'fulfilled')->count(),
                'hold_off' => (clone $summaryQuery)->where('status', 'hold_off')->count(),
            ],
            'search' => $search,
            'status' => $status,
            'canUpdateProjects' => $this->canUpdateStatus($request),
            'taskMode' => $completed ? 'completed' : 'new',
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless($this->canViewFulfillmentTracker($request), 403);

        $this->markProductionPageSeen($request);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');
        $tracker = $request->query('tracker', 'all');
        $allowedTrackers = $this->allowedTrackers($request);

        $projectsQuery = ProductionProject::with([
            'endorsement.agent',
            'endorsement.paymentRecord',
            'endorsement.service.inclusions',
            'fulfillmentOfficer',
            'endorsedBy',
            'brand',
            'tasks.assignedUser',
            'tasks.items',
        ]);
        BrandScope::apply($projectsQuery, $request->user());
        $this->applyFulfillmentVisibilityScope($projectsQuery, $request);

        $projects = $projectsQuery
            ->when($tracker !== 'all' && in_array($tracker, $allowedTrackers, true), fn ($query) => $query->where('tracker_type', $tracker))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('status', 'like', "%{$search}%")
                        ->orWhere('tracker_type', 'like', "%{$search}%")
                        ->orWhereHas('endorsement', function ($query) use ($search) {
                            $query->where('author_name', 'like', "%{$search}%")
                                ->orWhere('book_title', 'like', "%{$search}%")
                                ->orWhere('services', 'like', "%{$search}%")
                                ->orWhere('contact_number', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('street_name', 'like', "%{$search}%")
                                ->orWhere('city_state', 'like', "%{$search}%")
                                ->orWhereHas('agent', function ($query) use ($search) {
                                    $query->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('tasks.assignedUser', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('tasks', function ($query) use ($search) {
                            $query->where('title', 'like', "%{$search}%")
                                ->orWhere('instructions', 'like', "%{$search}%");
                        })
                        ->orWhereHas('fulfillmentOfficer', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => (clone $this->baseProjectQuery($request))->count(),
            'pending' => (clone $this->baseProjectQuery($request))->where('status', 'pending')->count(),
            'in_progress' => (clone $this->baseProjectQuery($request))->where('status', 'in_progress')->count(),
            'fulfilled' => (clone $this->baseProjectQuery($request))->where('status', 'fulfilled')->count(),
            'hold_off' => (clone $this->baseProjectQuery($request))->where('status', 'hold_off')->count(),
        ];

        return view('production.projects', [
            'projects' => $projects,
            'summary' => $summary,
            'search' => $search,
            'status' => $status,
            'tracker' => $tracker,
            'allowedTrackers' => $allowedTrackers,
            'canAssignProjects' => $this->canAssign($request),
            'canUpdateProjects' => $this->canUpdateStatus($request),
            'canDeleteProjects' => $this->canDelete($request),
            'canSelectProjects' => $this->canSelectProjects($request),
            'showAgentColumn' => $this->showAgentColumn($request),
            'productionStaff' => User::with('role')
                ->where('department', 'Production')
                ->whereNull('suspended_at')
                ->when(! BrandScope::canAccessAllBrands($request->user()), function ($query) use ($request) {
                    $query->where(function ($query) use ($request) {
                        $query->where('brand_id', $request->user()->brand_id)
                            ->orWhere('brand_id', BrandScope::parentBrandId());
                    });
                })
                ->whereHas('role', fn ($query) => $query->whereIn('name', [
                    'Fulfillment Officer',
                    'Web Designer',
                    'Video Editor',
                    'Writer',
                    'Graphic Designer',
                ]))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        abort_unless($this->canViewFulfillmentTracker($request) && $this->canAssign($request), 403);

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:production_projects,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'service_item_ids' => ['nullable', 'array'],
            'service_item_ids.*' => ['integer', 'exists:service_items,id'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $project = ProductionProject::with(['endorsement.service.inclusions', 'tasks.items'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->findOrFail($validated['project_id']);

        abort_unless($this->canTouchFulfillmentProject($request, $project), 403);

        if (! $this->isProductionStaffAssignee((int) $validated['assigned_to'], [$project->id], $request)) {
            return back()->with('error', 'Tasks can only be assigned to active Production staff.');
        }

        $selectedIds = collect($validated['service_item_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $availableItems = $project->endorsement?->service?->inclusions ?? collect();

        if ($selectedIds->diff($availableItems->pluck('id'))->isNotEmpty()) {
            return back()->with('error', 'One or more selected inclusions do not belong to this project service.');
        }

        $alreadyAssigned = $project->tasks
            ->flatMap(fn (ProductionTask $task) => $task->items->pluck('service_item_id'))
            ->filter();

        if ($selectedIds->intersect($alreadyAssigned)->isNotEmpty()) {
            return back()->with('error', 'One or more selected inclusions are already assigned to another task.');
        }

        $tasks = DB::transaction(function () use ($validated, $project, $selectedIds, $availableItems) {
            $taskPayload = [
                'assigned_to' => $validated['assigned_to'],
                'instructions' => $validated['instructions'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'pending',
                'progress' => 0,
            ];

            $createdTasks = collect();

            if ($selectedIds->isEmpty()) {
                $createdTasks->push($project->tasks()->create($taskPayload + [
                    'title' => $validated['title'],
                ]));
            } else {
                $selectedIds->each(function (int $itemId) use ($project, $taskPayload, $availableItems, $createdTasks) {
                    $item = $availableItems->firstWhere('id', $itemId);

                    $task = $project->tasks()->create($taskPayload + [
                        'title' => $item?->name ?: 'Production Task',
                    ]);

                    $task->items()->create([
                        'service_item_id' => $itemId,
                        'name' => $item?->name ?: $task->title,
                    ]);

                    $createdTasks->push($task);
                });
            }

            $project->syncStatusFromTasks();

            return $createdTasks;
        });

        $assignee = User::find((int) $validated['assigned_to']);
        if ($assignee && $this->userCanReceiveTaskNotification($assignee)) {
            $project->loadMissing('endorsement');
            $assignee->notify(new ProductionTaskAssignedNotification(
                $tasks->count() === 1 ? $project : null,
                max(1, $tasks->count())
            ));
        }

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
            ->with('success', $tasks->count() === 1
                ? 'Production task created and assigned successfully.'
                : "{$tasks->count()} production tasks created and assigned successfully.");
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        abort_unless($this->canViewFulfillmentTracker($request) && $this->canUpdateStatus($request), 403);

        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['integer', 'exists:production_projects,id'],
            'welcome_email_status' => ['required', 'in:pending,done,other_reason'],
            'welcome_email_reason' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $projects = ProductionProject::whereIn('id', $validated['project_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->get();

        $projects
            ->filter(fn (ProductionProject $project) => $this->canTouchFulfillmentProject($request, $project))
            ->each(function (ProductionProject $project) use ($validated) {
                $project->update([
                    'welcome_email_status' => $validated['welcome_email_status'],
                    'welcome_email_reason' => $validated['welcome_email_status'] === 'other_reason'
                        ? ($validated['welcome_email_reason'] ?? null)
                        : null,
                ]);
            });

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
            ->with('success', 'Welcome email status updated successfully.');
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        abort_unless($this->canViewFulfillmentTracker($request) && $this->canAssign($request), 403);

        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['integer', 'exists:production_projects,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'include_instruction' => ['nullable', 'boolean'],
            'assignment_instruction' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! $this->isProductionStaffAssignee((int) $validated['assigned_to'], $validated['project_ids'], $request)) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
                ->with('error', 'Projects can only be assigned to production staff.');
        }

        $assignee = User::find((int) $validated['assigned_to']);
        $assignedNotificationCount = 0;
        $firstAssignedProject = null;

        ProductionProject::whereIn('id', $validated['project_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->get()
            ->filter(fn (ProductionProject $project) => $this->canTouchFulfillmentProject($request, $project))
            ->each(function (ProductionProject $project) use ($validated, $assignee, &$assignedNotificationCount, &$firstAssignedProject) {
                $oldAssigneeId = $project->assigned_to;

                $project->update([
                    'assigned_to' => $validated['assigned_to'],
                    'assignment_instruction' => (bool) ($validated['include_instruction'] ?? false)
                        ? ($validated['assignment_instruction'] ?? null)
                        : null,
                    'status' => $project->status === 'fulfilled' ? 'in_progress' : $project->status,
                    'started_at' => $project->started_at ?? now(),
                    'completed_at' => $project->status === 'fulfilled' ? null : $project->completed_at,
                ]);

                if ($assignee && $oldAssigneeId !== $assignee->id && $this->userCanReceiveTaskNotification($assignee)) {
                    $project->loadMissing('endorsement');
                    $assignedNotificationCount++;
                    $firstAssignedProject ??= $project;
                }
            });

        if ($assignee && $assignedNotificationCount > 0) {
            $assignee->notify(new ProductionTaskAssignedNotification(
                $assignedNotificationCount === 1 ? $firstAssignedProject : null,
                $assignedNotificationCount
            ));
        }

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
            ->with('success', 'Selected project(s) assigned successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->canViewFulfillmentTracker($request) && $this->canDelete($request), 403);

        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['integer', 'exists:production_projects,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        ProductionProject::whereIn('id', $validated['project_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->get()
            ->filter(fn (ProductionProject $project) => $this->canTouchFulfillmentProject($request, $project))
            ->each(function (ProductionProject $project) {
                $project->delete();
            });

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
            ->with('success', 'Selected fulfillment record(s) deleted successfully.');
    }

    public function bulkTaskUpdate(Request $request): RedirectResponse
    {
        abort_unless($this->canViewOwnProductionTasks($request) && $this->canUpdateStatus($request), 403);

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:production_tasks,id'],
            'status' => ['nullable', 'in:pending,in_progress,fulfilled,hold_off'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'result_link' => ['nullable', 'string', 'max:2048'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! $request->has('status') && ! $request->has('notes') && ! $request->has('progress') && ! $request->has('result_link')) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.tasks.index'))
                ->with('error', 'Choose a task update before saving.');
        }

        $completedTaskIds = [];

        ProductionTask::with('project')
            ->whereIn('id', $validated['task_ids'])
            ->where('assigned_to', $request->user()->id)
            ->whereHas('project', fn ($query) => BrandScope::apply($query, $request->user()))
            ->get()
            ->each(function (ProductionTask $task) use ($validated, $request, &$completedTaskIds) {
                $oldStatus = $task->status;
                $oldProjectStatus = $task->project->status;
                $updates = [];

                if ($request->has('status')) {
                    $updates['status'] = $validated['status'];

                    if ($validated['status'] === 'pending') {
                        $updates['progress'] = 0;
                    }

                    if ($validated['status'] === 'in_progress' && ! $task->started_at) {
                        $updates['started_at'] = now();
                    }

                    if ($validated['status'] === 'fulfilled') {
                        $updates['progress'] = 100;
                        $updates['started_at'] = $task->started_at ?? now();
                        $updates['completed_at'] = now();
                    }

                    if ($validated['status'] !== 'fulfilled') {
                        $updates['completed_at'] = null;
                    }
                }

                if ($request->filled('progress') && ($validated['status'] ?? $task->status) !== 'fulfilled') {
                    $updates['progress'] = $validated['progress'];
                    if ($validated['progress'] > 0 && ($validated['status'] ?? null) === null) {
                        $updates['status'] = 'in_progress';
                        $updates['started_at'] = $task->started_at ?? now();
                    }
                }

                if ($request->has('notes')) {
                    $updates['notes'] = $validated['notes'] ?? null;
                }

                if ($request->has('result_link')) {
                    $updates['result_link'] = $validated['result_link'] ?? null;
                }

                if ($updates !== []) {
                    $task->update($updates);
                    $task->project->syncStatusFromTasks();
                    $task->project->refresh();

                    if (($updates['status'] ?? null) === 'fulfilled' && $oldStatus !== 'fulfilled') {
                        $completedTaskIds[] = $task->id;
                    }

                    if ($oldProjectStatus !== 'fulfilled' && $task->project->status === 'fulfilled') {
                        $this->notifyProjectCompleted($task->project);
                    }
                }
            });

        $this->notifyFulfillmentOfficersTasksDone($completedTaskIds);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.tasks.index'))
            ->with('success', 'Selected task(s) updated successfully.');
    }

    public function update(Request $request, ProductionProject $project): RedirectResponse
    {
        abort_unless(
            $this->canUpdateStatus($request) && $this->canTouchProject($request, $project),
            403
        );

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,in_progress,fulfilled,hold_off'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assignment_instruction' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'welcome_email_status' => ['nullable', 'in:pending,done,other_reason'],
            'welcome_email_reason' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($request->has('welcome_email_status')) {
            abort_unless(
                $this->canViewFulfillmentTracker($request)
                && $this->canTouchFulfillmentProject($request, $project),
                403
            );
        }

        if ($request->has('assigned_to')) {
            abort_unless(
                $this->canViewFulfillmentTracker($request)
                && $this->canAssign($request)
                && $this->canTouchFulfillmentProject($request, $project),
                403
            );
        }

        $updates = [];
        $oldAssigneeId = $project->assigned_to;

        if ($this->canAssign($request)) {
            if ($request->has('assigned_to')) {
                if (! empty($validated['assigned_to']) && ! $this->isProductionStaffAssignee((int) $validated['assigned_to'], [$project->id], $request)) {
                    return redirect()
                        ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
                        ->with('error', 'Projects can only be assigned to production staff.');
                }

                $updates['assigned_to'] = $validated['assigned_to'] ?? null;
                $updates['assignment_instruction'] = $validated['assignment_instruction'] ?? null;
            }
        }

        if ($request->has('status')) {
            $updates['status'] = $validated['status'];
        }

        if ($request->has('notes')) {
            $updates['notes'] = $validated['notes'] ?? null;
        }

        if ($request->has('welcome_email_status')) {
            $updates['welcome_email_status'] = $validated['welcome_email_status'] ?? 'pending';
            $updates['welcome_email_reason'] = $updates['welcome_email_status'] === 'other_reason'
                ? ($validated['welcome_email_reason'] ?? null)
                : null;
        }

        $nextStatus = $updates['status'] ?? null;

        if ($nextStatus !== null) {
            if ($nextStatus === 'in_progress' && ! $project->started_at) {
                $updates['started_at'] = now();
            }

            if ($nextStatus === 'fulfilled') {
                $updates['started_at'] = $project->started_at ?? now();
                $updates['completed_at'] = now();
            }

            if ($nextStatus !== 'fulfilled') {
                $updates['completed_at'] = null;
            }
        }

        $oldStatus = $project->status;

        if ($updates !== []) {
            $project->update($updates);

            if (
                array_key_exists('assigned_to', $updates)
                && ! empty($updates['assigned_to'])
                && $oldAssigneeId !== (int) $updates['assigned_to']
            ) {
                $assignee = User::find((int) $updates['assigned_to']);

                if ($assignee && $this->userCanReceiveTaskNotification($assignee)) {
                    $project->loadMissing('endorsement');
                    $assignee->notify(new ProductionTaskAssignedNotification($project));
                }
            }

            if (($updates['status'] ?? null) === 'fulfilled' && $oldStatus !== 'fulfilled') {
                $this->notifyProjectCompleted($project);
            }
        }

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('production.projects.index'))
            ->with('success', 'Production project updated successfully.');
    }

    public function sidebarCounts(Request $request)
    {
        return response()->json([
            'new_endorsed_projects' => $this->canViewFulfillmentTracker($request)
                ? $this->unseenProductionProjectCount($request)
                : 0,
        ]);
    }

    private function baseProjectQuery(Request $request)
    {
        $query = ProductionProject::query();
        BrandScope::apply($query, $request->user());

        $this->applyFulfillmentVisibilityScope($query, $request);

        return $query;
    }

    private function applyFulfillmentVisibilityScope($query, Request $request): void
    {
        if ($this->canViewAllTrackers($request)) {
            return;
        }

        $allowedTrackers = $this->allowedTrackers($request);
        $clientProgressAgentIds = $this->clientProgressAgentIds($request);

        $query->where(function ($query) use ($allowedTrackers, $clientProgressAgentIds) {
            if ($allowedTrackers !== []) {
                $query->whereIn('tracker_type', $allowedTrackers);
            }

            if ($clientProgressAgentIds !== []) {
                $allowedTrackers === []
                    ? $query->whereHas('endorsement', fn ($query) => $query->whereIn('agent_id', $clientProgressAgentIds))
                    : $query->orWhereHas('endorsement', fn ($query) => $query->whereIn('agent_id', $clientProgressAgentIds));
            }
        });
    }

    private function canViewFulfillmentTracker(Request $request): bool
    {
        return $this->canViewTaskTracker($request)
            || $this->canViewClientProgress($request)
            || $this->canViewTeamClientProgress($request);
    }

    private function canViewTaskTracker(Request $request): bool
    {
        return $this->canViewAllTrackers($request)
            || $this->allowedTrackers($request) !== [];
    }

    private function canViewAllTrackers(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('view_all_fulfillment_trackers');
    }

    private function allowedTrackers(Request $request): array
    {
        if ($this->canViewAllTrackers($request)) {
            return ['publishing', 'marketing', 'events'];
        }

        return collect([
            'publishing' => $request->user()?->hasPermission('view_publishing_tracker'),
            'marketing' => $request->user()?->hasPermission('view_marketing_tracker'),
            'events' => $request->user()?->hasPermission('view_events_tracker'),
        ])
            ->filter()
            ->keys()
            ->values()
            ->all();
    }

    private function canAssign(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('assign_production_projects');
    }

    private function canUpdateStatus(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('manage_production_projects')
            || (bool) $request->user()?->hasPermission('update_production_project_status');
    }

    private function canDelete(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('delete_fulfillment_records');
    }

    private function canSelectProjects(Request $request): bool
    {
        return $this->canViewFulfillmentTracker($request)
            && (
                $request->user()?->role?->name === 'Admin'
                || $request->user()?->department === 'Production'
            );
    }

    private function canTouchFulfillmentProject(Request $request, ProductionProject $project): bool
    {
        return $this->canViewAllTrackers($request)
            || in_array($project->tracker_type, $this->allowedTrackers($request), true)
            || (
                $this->canViewClientProgress($request)
                && $project->endorsement?->agent_id === $request->user()->id
            );
    }

    private function canTouchProject(Request $request, ProductionProject $project): bool
    {
        if (! $this->userCanAccessBrand($request, $project->brand_id)) {
            return false;
        }

        return $this->canTouchFulfillmentProject($request, $project)
            || (
                $this->canViewOwnProductionTasks($request)
                && $project->assigned_to === $request->user()->id
            );
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }

    private function markProductionPageSeen(Request $request): void
    {
        if (! $request->user() || ! Schema::hasTable('production_page_views')) {
            return;
        }

        DB::table('production_page_views')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'page_key' => 'endorsed_projects',
            ],
            [
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function unseenProductionProjectCount(Request $request): int
    {
        if (! Schema::hasTable('production_page_views')) {
            return 0;
        }

        $lastSeenAt = DB::table('production_page_views')
            ->where('user_id', $request->user()->id)
            ->where('page_key', 'endorsed_projects')
            ->value('last_seen_at');

        $query = $this->baseProjectQuery($request);

        if ($lastSeenAt) {
            $query->where('created_at', '>', $lastSeenAt);
        }

        return $query->count();
    }

    private function userCanReceiveTaskNotification(User $user): bool
    {
        return $user->role?->name === 'Admin'
            || (bool) $user->hasPermission('view_my_production_tasks');
    }

    private function notifyFulfillmentOfficerTaskDone(ProductionProject $project): void
    {
        $project->loadMissing('endorsement', 'fulfillmentOfficer');

        if (! $project->fulfillmentOfficer || ! $this->userCanReceiveFulfillmentNotification($project->fulfillmentOfficer)) {
            return;
        }

        $project->fulfillmentOfficer->notify(new ProductionTaskDoneNotification($project));
    }

    private function notifyProjectCompleted(ProductionProject $project): void
    {
        $project->loadMissing('endorsement.agent', 'fulfillmentOfficer');

        $recipients = collect([$project->fulfillmentOfficer, $project->endorsement?->agent])
            ->filter()
            ->unique('id');

        $recipients->each(function (User $user) use ($project) {
            if (
                $user->id === $project->fulfillment_officer_id
                && ! $this->userCanReceiveFulfillmentNotification($user)
            ) {
                return;
            }

            $user->notify(new ProductionProjectCompletedNotification($project));
        });
    }

    private function notifyFulfillmentOfficersTasksDone(array $taskIds): void
    {
        if ($taskIds === []) {
            return;
        }

        ProductionTask::with(['project.endorsement', 'project.fulfillmentOfficer'])
            ->whereIn('id', array_unique($taskIds))
            ->get()
            ->filter(fn (ProductionTask $task) => $task->project?->fulfillmentOfficer && $this->userCanReceiveFulfillmentNotification($task->project->fulfillmentOfficer))
            ->groupBy(fn (ProductionTask $task) => $task->project->fulfillment_officer_id)
            ->each(function ($tasks) {
                $firstTask = $tasks->first();
                $firstProject = $firstTask?->project;
                $fulfillmentOfficer = $firstProject?->fulfillmentOfficer;

                if (! $fulfillmentOfficer) {
                    return;
                }

                $fulfillmentOfficer->notify(new ProductionTaskDoneNotification(
                    $tasks->count() === 1 ? $firstProject : null,
                    $tasks->count()
                ));
            });
    }

    private function userCanReceiveFulfillmentNotification(User $user): bool
    {
        return $user->role?->name === 'Admin'
            || $user->hasPermission('view_all_fulfillment_trackers')
            || $user->hasPermission('view_publishing_tracker')
            || $user->hasPermission('view_marketing_tracker')
            || $user->hasPermission('view_events_tracker');
    }

    private function isProductionStaffAssignee(int $userId, array $projectIds = [], ?Request $request = null): bool
    {
        $allowedBrandIds = [];

        if ($projectIds !== []) {
            $allowedBrandIds = ProductionProject::query()
                ->whereIn('id', $projectIds)
                ->pluck('brand_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return User::query()
            ->whereKey($userId)
            ->where('department', 'Production')
            ->whereNull('suspended_at')
            ->when($allowedBrandIds !== [] && ! BrandScope::canAccessAllBrands($request?->user()), function ($query) use ($allowedBrandIds) {
                $query->where(function ($query) use ($allowedBrandIds) {
                    $query->whereIn('brand_id', $allowedBrandIds)
                        ->orWhere('brand_id', BrandScope::parentBrandId());
                });
            })
            ->whereHas('role', fn ($query) => $query->whereIn('name', [
                'Fulfillment Officer',
                'Web Designer',
                'Video Editor',
                'Writer',
                'Graphic Designer',
            ]))
            ->exists();
    }

    private function canViewOwnProductionTasks(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('view_my_production_tasks');
    }

    private function canViewClientProgress(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('view_client_project_progress');
    }

    private function canViewTeamClientProgress(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('view_team_client_project_progress');
    }

    private function clientProgressAgentIds(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        $agentIds = collect();

        if ($this->canViewClientProgress($request)) {
            $agentIds->push($user->id);
        }

        if ($this->canViewTeamClientProgress($request)) {
            $teamIds = collect($user->managedTeams()->pluck('id'))
                ->merge($user->ledTeams()->pluck('id'));

            if ($user->team_id) {
                $teamIds->push($user->team_id);
            }

            $teamIds = $teamIds->filter()->unique()->values();

            if ($teamIds->isNotEmpty()) {
                $agentIds = $agentIds->merge(
                    User::query()
                        ->whereIn('team_id', $teamIds)
                        ->where('department', 'Sales')
                        ->pluck('id')
                );
            }
        }

        return $agentIds->filter()->unique()->values()->all();
    }

    private function showAgentColumn(Request $request): bool
    {
        if ($request->user()?->department !== 'Sales' || $request->user()?->role?->name === 'Admin') {
            return true;
        }

        return in_array($request->user()?->role?->name, [
            'Team Leader',
            'Sales Director',
            'Operation Manager',
        ], true);
    }

    private function safeReturnUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return str_starts_with($url, url('/')) ? $url : null;
    }
}
