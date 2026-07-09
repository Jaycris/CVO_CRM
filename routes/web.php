<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CalendarTodoController;
use App\Http\Controllers\FinanceClientController;
use App\Http\Controllers\FinanceContractController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadSaleCreditController;
use App\Http\Controllers\PersonalNoteController;
use App\Http\Controllers\ProductionProjectController;
use App\Http\Controllers\ProductionReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesEndorsementController;
use App\Http\Controllers\SalesPaymentController;
use App\Http\Controllers\SalesActivityController;
use App\Http\Controllers\SalesPerformanceController;
use App\Http\Controllers\ServiceCatalogController;
use App\Models\CalendarTodo;
use App\Models\DashboardBanner;
use App\Models\Lead;
use App\Models\PersonalNote;
use App\Models\ProductionProject;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Support\BrandScope;
use App\Support\SalesMtdCalculator;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\DashboardBannerController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\CommissionSettingController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Models\SalesActivity;


Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $user = request()->user();
    $roleName = $user?->role?->name;
    $departmentName = $user?->department;
    $isAdmin = $roleName === 'Admin';

    $leadPoolQuery = Lead::query()->whereNull('sales_stage')->whereNull('archived_at');
    BrandScope::apply($leadPoolQuery, $user);
    $verifiedLeadsQuery = (clone $leadPoolQuery)->whereNotNull('verified_at');
    $salesPipelineQuery = Lead::query()
        ->whereNotNull('assigned_to')
        ->whereNull('returned_at')
        ->whereNull('archived_at')
        ->where(function ($query) {
            $query->whereNotNull('sales_stage')
                ->orWhereNull('sales_stage');
        });
    BrandScope::apply($salesPipelineQuery, $user);

    $dashboardCards = match (true) {
        $isAdmin => [
            [
                'label' => 'Total Leads',
                'count' => (clone $leadPoolQuery)->count(),
                'hint' => 'All active lead records',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Total Verified Leads',
                'count' => (clone $verifiedLeadsQuery)->count(),
                'hint' => 'Verified by Lead Generation',
                'tone' => 'amber',
            ],
            [
                'label' => 'Total Successful Transaction',
                'count' => BrandScope::apply(SalesPayment::query(), $user)->where('status', 'Payment Success')->count(),
                'hint' => 'Confirmed payments',
                'tone' => 'sky',
            ],
            [
                'label' => 'Total Completed Project',
                'count' => BrandScope::apply(ProductionProject::query(), $user)->where('status', 'fulfilled')->count(),
                'hint' => 'Completed by Production',
                'tone' => 'rose',
            ],
        ],
        $departmentName === 'Lead Generation' => [
            [
                'label' => 'Total Leads',
                'count' => (clone $leadPoolQuery)->count(),
                'hint' => 'Lead generation pool',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Total Verified Leads',
                'count' => (clone $verifiedLeadsQuery)->count(),
                'hint' => 'Ready for assignment',
                'tone' => 'amber',
            ],
            [
                'label' => 'Total Returned Leads',
                'count' => BrandScope::apply(Lead::query(), $user)->whereNotNull('returned_at')->whereNull('archived_at')->count(),
                'hint' => 'Sent back by Sales',
                'tone' => 'rose',
            ],
            [
                'label' => 'Total Assigned Leads',
                'count' => BrandScope::apply(Lead::query(), $user)->whereNotNull('assigned_to')->whereNull('returned_at')->whereNull('archived_at')->count(),
                'hint' => 'Assigned to Sales',
                'tone' => 'sky',
            ],
        ],
        $departmentName === 'Sales' => [
            [
                'label' => 'Total Assigned Leads',
                'count' => BrandScope::apply(Lead::query(), $user)->where('assigned_to', $user?->id)->whereNull('returned_at')->whereNull('archived_at')->count(),
                'hint' => 'Assigned to you',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Total Sold Leads',
                'count' => BrandScope::apply(Lead::query(), $user)->where('assigned_to', $user?->id)->where('sales_stage', 'sold')->whereNull('returned_at')->whereNull('archived_at')->count(),
                'hint' => 'Your closed sales',
                'tone' => 'sky',
            ],
            [
                'label' => 'Total Refund',
                'count' => BrandScope::apply(Lead::query(), $user)->where('assigned_to', $user?->id)->where('sales_stage', 'refunds')->whereNull('returned_at')->whereNull('archived_at')->count(),
                'hint' => 'Your refund records',
                'tone' => 'rose',
            ],
            [
                'label' => 'Total Completed Project',
                'count' => BrandScope::apply(ProductionProject::query(), $user)
                    ->where('status', 'fulfilled')
                    ->whereHas('endorsement', fn ($query) => $query->where('agent_id', $user?->id))
                    ->count(),
                'hint' => 'Your completed client projects',
                'tone' => 'amber',
            ],
        ],
        $roleName === 'Finance Officer' || $departmentName === 'Finance' => [
            [
                'label' => 'Total Customer',
                'count' => BrandScope::apply(SalesEndorsement::query(), $user)->count(),
                'hint' => 'From sales endorsements',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Total Successful Transaction',
                'count' => BrandScope::apply(SalesPayment::query(), $user)->where('status', 'Payment Success')->count(),
                'hint' => 'Confirmed payments',
                'tone' => 'sky',
            ],
            [
                'label' => 'Total Refund',
                'count' => BrandScope::apply(SalesPayment::query(), $user)->where('status', 'Refund')->count(),
                'hint' => 'Refund records',
                'tone' => 'rose',
            ],
            [
                'label' => 'Total Completed Project',
                'count' => BrandScope::apply(ProductionProject::query(), $user)->where('status', 'fulfilled')->count(),
                'hint' => 'Completed by Production',
                'tone' => 'amber',
            ],
        ],
        default => [
            [
                'label' => 'Total Leads',
                'count' => (clone $leadPoolQuery)->count(),
                'hint' => 'Active lead records',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Sales Pipeline',
                'count' => (clone $salesPipelineQuery)->count(),
                'hint' => 'Assigned sales leads',
                'tone' => 'sky',
            ],
            [
                'label' => 'Total Completed Project',
                'count' => BrandScope::apply(ProductionProject::query(), $user)->where('status', 'fulfilled')->count(),
                'hint' => 'Completed by Production',
                'tone' => 'rose',
            ],
        ],
    };

    $successfulActivities = SalesActivity::query()
        ->with(['agent', 'frankieAgent'])
        ->where('payment_status', 'Payment Success');
    BrandScope::apply($successfulActivities, $user);

    $topSalesPerformance = SalesActivity::query()
        ->with(['agent', 'frankieAgent'])
        ->where('payment_status', 'Payment Success')
        ->whereBetween('sold_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
        ->tap(fn ($query) => BrandScope::apply($query, $user))
        ->get()
        ->flatMap(function (SalesActivity $activity) {
            $rows = [[
                'agent' => $activity->agent,
                'amount' => (float) ($activity->agent_credit_amount ?: $activity->amount),
            ]];

            if ($activity->frankieAgent && (float) $activity->frankie_credit_amount > 0) {
                $rows[] = [
                    'agent' => $activity->frankieAgent,
                    'amount' => (float) $activity->frankie_credit_amount,
                ];
            }

            return $rows;
        })
        ->groupBy(fn (array $row) => $row['agent']?->id ?: 'unknown')
        ->map(function ($rows) {
            $agent = $rows->first()['agent'] ?? null;

            return [
                'agent_name' => trim(($agent?->first_name ?? '') . ' ' . ($agent?->last_name ?? '')) ?: 'Unknown Agent',
                'total_amount' => $rows->sum('amount'),
            ];
        })
        ->sortByDesc('total_amount')
        ->take(5)
        ->values();

    $currentMonthTotal = (clone $successfulActivities)
        ->whereBetween('sold_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
        ->get()
        ->sum('amount');

    $lastMonth = now()->subMonthNoOverflow();
    $lastMonthTotal = (clone $successfulActivities)
        ->whereBetween('sold_date', [$lastMonth->copy()->startOfMonth()->toDateString(), $lastMonth->copy()->endOfMonth()->toDateString()])
        ->get()
        ->sum('amount');

    $highestMonthlyTotal = max($currentMonthTotal, $lastMonthTotal, 1);
    $monthlySalesComparison = [
        [
            'label' => now()->format('F Y'),
            'short_label' => 'Current Month',
            'total' => $currentMonthTotal,
            'width' => round(($currentMonthTotal / $highestMonthlyTotal) * 100),
            'color' => 'bg-emerald-500',
            'text_color' => 'text-emerald-600 dark:text-emerald-300',
        ],
        [
            'label' => $lastMonth->format('F Y'),
            'short_label' => 'Last Month',
            'total' => $lastMonthTotal,
            'width' => round(($lastMonthTotal / $highestMonthlyTotal) * 100),
            'color' => 'bg-amber-500',
            'text_color' => 'text-amber-600 dark:text-amber-300',
        ],
    ];

    $salesMtdSummary = SalesMtdCalculator::summary($user, now());

    $recentNotes = PersonalNote::where('user_id', $user?->id)
        ->latest('updated_at')
        ->take(3)
        ->get();

    $upcomingCalendarTodos = CalendarTodo::where('user_id', $user?->id)
        ->whereDate('due_date', '>=', now()->toDateString())
        ->whereNull('completed_at')
        ->orderBy('due_date')
        ->orderBy('due_time')
        ->take(5)
        ->get();

    $dashboardBanners = DashboardBanner::query()
        ->with('brand')
        ->currentlyVisible()
        ->visibleFor($user)
        ->latest('starts_at')
        ->latest()
        ->take(2)
        ->get();

    return view('dashboard', compact(
        'dashboardCards',
        'topSalesPerformance',
        'monthlySalesComparison',
        'salesMtdSummary',
        'recentNotes',
        'upcomingCalendarTodos',
        'dashboardBanners'
    ));
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/services', [ServiceCatalogController::class, 'index'])->name('services.index');

    Route::get('/notes', [PersonalNoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [PersonalNoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [PersonalNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [PersonalNoteController::class, 'destroy'])->name('notes.destroy');

    Route::get('/calendar', [CalendarTodoController::class, 'index'])->name('calendar.index');
    Route::post('/calendar/todos', [CalendarTodoController::class, 'store'])->name('calendar.todos.store');
    Route::put('/calendar/todos/{todo}', [CalendarTodoController::class, 'update'])->name('calendar.todos.update');
    Route::patch('/calendar/todos/{todo}/toggle', [CalendarTodoController::class, 'toggle'])->name('calendar.todos.toggle');
    Route::delete('/calendar/todos/{todo}', [CalendarTodoController::class, 'destroy'])->name('calendar.todos.destroy');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/sidebar-counts', [LeadController::class, 'sidebarCounts'])->name('leads.sidebar-counts');
    Route::get('/leads/my', [LeadController::class, 'myLeads'])->name('leads.my');
    Route::get('/leads/verification-queue', [LeadController::class, 'verificationQueue'])->name('leads.verification-queue');
    Route::redirect('/leads/verified', '/leads/unassigned');
    Route::get('/leads/unassigned', [LeadController::class, 'unassignedLeads'])->name('leads.unassigned');
    Route::get('/leads/new', [LeadController::class, 'newLeads'])->name('leads.new');
    Route::get('/leads/assigned', [LeadController::class, 'assignedLeads'])->name('leads.assigned');
    Route::redirect('/leads/sold-mined', '/reports/sold-mined');
    Route::redirect('/leads/verified-sold', '/reports/verified-sold');
    Route::get('/leads/returned', [LeadController::class, 'returnedLeads'])->name('leads.returned');
    Route::get('/leads/archived', [LeadController::class, 'archivedLeads'])->name('leads.archived');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::get('/leads/import', [LeadController::class, 'importForm'])->name('leads.import');
    Route::post('/leads/import', [LeadController::class, 'import'])->name('leads.import.store');
    Route::get('/leads/import/template', [LeadController::class, 'downloadImportTemplate'])->name('leads.import.template');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::post('/leads/assign', [LeadController::class, 'assign'])->name('leads.assign');
    Route::post('/leads/unassign', [LeadController::class, 'unassign'])->name('leads.unassign');
    Route::post('/leads/send-to-verification', [LeadController::class, 'sendToVerificationQueue'])->name('leads.send-to-verification');
    Route::post('/leads/move-verified-to-ready', [LeadController::class, 'moveVerifiedLeadsToReadyQueue'])->name('leads.move-verified-to-ready');
    Route::delete('/leads/bulk-delete', [LeadController::class, 'bulkDestroy'])->name('leads.bulk-destroy');
    Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
    Route::get('/leads/{lead}/verify', [LeadController::class, 'verify'])->name('leads.verify');
    Route::post('/leads/{lead}/verify', [LeadController::class, 'storeVerification'])->name('leads.verify.store');
    Route::post('/leads/{lead}/phone-statuses', [LeadController::class, 'updatePhoneStatuses'])->name('leads.phone-statuses');
    Route::post('/leads/sales-stage', [LeadController::class, 'moveSalesStage'])->name('leads.sales-stage');
    Route::post('/leads/return', [LeadController::class, 'returnLeads'])->name('leads.return');
    Route::post('/leads/send-returned-to-agent', [LeadController::class, 'sendReturnedToAgent'])->name('leads.send-returned-to-agent');
    Route::post('/leads/archive', [LeadController::class, 'archiveLeads'])->name('leads.archive');
    Route::post('/leads/restore', [LeadController::class, 'restoreLeads'])->name('leads.restore');

    Route::get('/sales/team-leads', [LeadController::class, 'salesTeamLeads'])->name('sales.team-leads');
    Route::get('/sales/pipeline', [LeadController::class, 'salesPipeline'])->name('sales.pipeline');
    Route::get('/sales/prospect', [LeadController::class, 'salesProspect'])->name('sales.prospect');
    Route::get('/sales/scheduled-callback', [LeadController::class, 'salesScheduledCallback'])->name('sales.scheduled-callback');
    Route::get('/sales/sold', [LeadController::class, 'salesSold'])->name('sales.sold');
    Route::get('/sales/refunds', [LeadController::class, 'salesRefunds'])->name('sales.refunds');
    Route::get('/sales/endorsement', [SalesEndorsementController::class, 'index'])->name('sales.endorsements.index');
    Route::get('/sales/endorsement/create', [SalesEndorsementController::class, 'create'])->name('sales.endorsements.create');
    Route::post('/sales/endorsement', [SalesEndorsementController::class, 'store'])->name('sales.endorsements.store');
    Route::delete('/sales/endorsement', [SalesEndorsementController::class, 'bulkDestroy'])->name('sales.endorsements.bulk-destroy');
    Route::get('/finance/payments', [SalesPaymentController::class, 'index'])->name('finance.payments.index');
    Route::post('/finance/payments', [SalesPaymentController::class, 'store'])->name('finance.payments.store');
    Route::delete('/finance/payments', [SalesPaymentController::class, 'bulkDestroy'])->name('finance.payments.bulk-destroy');
    Route::put('/finance/payments/{payment}', [SalesPaymentController::class, 'update'])->name('finance.payments.update');
    Route::redirect('/finance/sales-activity', '/reports/sales-activity');
    Route::get('/finance/clients/sold', [FinanceClientController::class, 'sold'])->name('finance.clients.sold');
    Route::get('/finance/clients/refunds-disputes', [FinanceClientController::class, 'refundsDisputes'])->name('finance.clients.refunds-disputes');
    Route::delete('/finance/clients', [FinanceClientController::class, 'bulkDestroy'])->name('finance.clients.bulk-destroy');
    Route::get('/finance/contracts', [FinanceContractController::class, 'index'])->name('finance.contracts.index');
    Route::put('/finance/contracts', [FinanceContractController::class, 'bulkUpdate'])->name('finance.contracts.bulk-update');
    Route::post('/finance/contracts/endorse-production', [FinanceContractController::class, 'endorseToProduction'])->name('finance.contracts.endorse-production');
    Route::delete('/finance/contracts', [FinanceContractController::class, 'bulkDestroy'])->name('finance.contracts.bulk-destroy');
    Route::put('/finance/contracts/{endorsement}', [FinanceContractController::class, 'update'])->name('finance.contracts.update');
    Route::get('/production/tasks', [ProductionProjectController::class, 'tasks'])->name('production.tasks.index');
    Route::get('/production/tasks/completed', [ProductionProjectController::class, 'completedTasks'])->name('production.tasks.completed');
    Route::get('/production/task-tracker', [ProductionProjectController::class, 'taskTracker'])->name('production.tasks.tracker');
    Route::put('/production/tasks', [ProductionProjectController::class, 'bulkTaskUpdate'])->name('production.tasks.bulk-update');
    Route::get('/production/sidebar-counts', [ProductionProjectController::class, 'sidebarCounts'])->name('production.sidebar-counts');
    Route::get('/production/projects', [ProductionProjectController::class, 'index'])->name('production.projects.index');
    Route::post('/production/projects/tasks', [ProductionProjectController::class, 'storeTask'])->name('production.projects.tasks.store');
    Route::put('/production/projects', [ProductionProjectController::class, 'bulkUpdate'])->name('production.projects.bulk-update');
    Route::put('/production/projects/assign', [ProductionProjectController::class, 'bulkAssign'])->name('production.projects.bulk-assign');
    Route::delete('/production/projects', [ProductionProjectController::class, 'bulkDestroy'])->name('production.projects.bulk-destroy');
    Route::put('/production/projects/{project}', [ProductionProjectController::class, 'update'])->name('production.projects.update');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/{format}', [ReportController::class, 'export'])->name('export');
        Route::get('/sold-mined', [LeadSaleCreditController::class, 'soldMined'])->name('sold-mined');
        Route::get('/verified-sold', [LeadSaleCreditController::class, 'verifiedSold'])->name('verified-sold');
        Route::get('/sales-activity', [SalesActivityController::class, 'index'])->name('sales-activity.index');
        Route::get('/sales-performance', [SalesPerformanceController::class, 'index'])->name('sales-performance.index');
        Route::put('/sales-performance/targets', [SalesPerformanceController::class, 'updateTargets'])->name('sales-performance.targets');
        Route::get('/production', [ProductionReportController::class, 'index'])->name('production.index');
    });
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::get('/dashboard-banners', [DashboardBannerController::class, 'index'])->name('dashboard-banners.index');
    Route::post('/dashboard-banners', [DashboardBannerController::class, 'store'])->name('dashboard-banners.store');
    Route::put('/dashboard-banners/{dashboardBanner}', [DashboardBannerController::class, 'update'])->name('dashboard-banners.update');
    Route::delete('/dashboard-banners/{dashboardBanner}', [DashboardBannerController::class, 'destroy'])->name('dashboard-banners.destroy');
    Route::get('/commission-settings', [CommissionSettingController::class, 'edit'])->name('commission-settings.edit');
    Route::put('/commission-settings', [CommissionSettingController::class, 'update'])->name('commission-settings.update');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
    Route::post('/roles-permissions/departments', [RolePermissionController::class, 'storeDepartment'])->name('roles-permissions.departments.store');
    Route::put('/roles-permissions/departments/{department}', [RolePermissionController::class, 'updateDepartment'])->name('roles-permissions.departments.update');
    Route::post('/roles-permissions/roles', [RolePermissionController::class, 'storeRole'])->name('roles-permissions.roles.store');
    Route::put('/roles-permissions/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('roles-permissions.roles.update');
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/force-delete', [TrashController::class, 'forceDestroy'])->name('trash.force-delete');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::patch('/users/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});


// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['post', 'patch'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

require __DIR__.'/auth.php';
