<?php

namespace App\Http\Controllers;

use App\Models\ProductionProject;
use App\Models\ProductionTask;
use App\Support\BrandScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductionReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('view_production_reports')
            || (bool) $request->user()?->hasPermission('view_reports'),
            403
        );

        $search = trim((string) $request->query('search', ''));

        $projectsQuery = ProductionProject::with([
                'brand',
                'endorsement.agent',
                'fulfillmentOfficer',
                'assignedUser',
                'tasks.assignedUser',
            ])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('tracker_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                        ->orWhereHas('fulfillmentOfficer', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('endorsement', function ($query) use ($search) {
                            $query->where('endorsement_code', 'like', "%{$search}%")
                                ->orWhere('author_name', 'like', "%{$search}%")
                                ->orWhere('book_title', 'like', "%{$search}%")
                                ->orWhere('services', 'like', "%{$search}%")
                                ->orWhereHas('agent', function ($query) use ($search) {
                                    $query->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                });
                        });
                });
            });

        $projects = $projectsQuery->latest('endorsed_at')->latest()->get();

        $summaryCards = [
            [
                'label' => 'Total Projects',
                'count' => $projects->count(),
                'hint' => 'Endorsed to Production',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Pending',
                'count' => $projects->where('status', 'pending')->count(),
                'hint' => 'Waiting fulfillment',
                'tone' => 'rose',
            ],
            [
                'label' => 'In Progress',
                'count' => $projects->where('status', 'in_progress')->count(),
                'hint' => 'Being worked',
                'tone' => 'sky',
            ],
            [
                'label' => 'Fulfilled',
                'count' => $projects->where('status', 'fulfilled')->count(),
                'hint' => 'Completed projects',
                'tone' => 'amber',
            ],
        ];

        $trackerBreakdown = $projects
            ->groupBy(fn (ProductionProject $project) => ucfirst((string) ($project->tracker_type ?: 'Uncategorized')))
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'total' => $items->count(),
                'fulfilled' => $items->where('status', 'fulfilled')->count(),
                'in_progress' => $items->where('status', 'in_progress')->count(),
                'pending' => $items->where('status', 'pending')->count(),
            ])
            ->sortBy('label')
            ->values();

        $fulfillmentWorkload = $projects
            ->groupBy(fn (ProductionProject $project) => trim(($project->fulfillmentOfficer?->first_name ?? '') . ' ' . ($project->fulfillmentOfficer?->last_name ?? '')) ?: 'Unassigned')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'total' => $items->count(),
                'pending' => $items->where('status', 'pending')->count(),
                'in_progress' => $items->where('status', 'in_progress')->count(),
                'fulfilled' => $items->where('status', 'fulfilled')->count(),
                'average_progress' => (int) round($items->avg(fn (ProductionProject $project) => $project->progress_percentage) ?? 0),
            ])
            ->sortByDesc('total')
            ->values();

        $taskQuery = ProductionTask::with(['assignedUser', 'project.brand', 'project.endorsement'])
            ->whereHas('project', fn ($query) => BrandScope::apply($query, $request->user()));

        $tasks = $taskQuery->get();

        $staffTaskLoad = $tasks
            ->groupBy(fn (ProductionTask $task) => trim(($task->assignedUser?->first_name ?? '') . ' ' . ($task->assignedUser?->last_name ?? '')) ?: 'Unassigned')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'total' => $items->count(),
                'pending' => $items->where('status', 'pending')->count(),
                'in_progress' => $items->where('status', 'in_progress')->count(),
                'done' => $items->where('status', 'fulfilled')->count(),
                'average_progress' => (int) round($items->avg('progress') ?? 0),
            ])
            ->sortByDesc('total')
            ->values();

        $recentProjects = $projects->take(10);

        return view('reports.production', compact(
            'search',
            'summaryCards',
            'trackerBreakdown',
            'fulfillmentWorkload',
            'staffTaskLoad',
            'recentProjects'
        ));
    }
}
