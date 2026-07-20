<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ darkMode: localStorage.getItem('theme') === 'dark' }"
      x-init="$watch('darkMode', value => localStorage.setItem('theme', value ? 'dark' : 'light'))"
      x-bind:class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $headBrand = auth()->user()?->brand;
        $headBrandName = $headBrand?->imprint_name ?? 'CreatiVision Outsourcing';
        $headAppName = $headBrand?->crm_display_name ?: ($headBrandName === 'CreatiVision Outsourcing' ? 'CreatiVision CRM' : $headBrandName . ' CRM');
        $siteIdentityLogo = $headBrand?->site_logo_path
            ? asset('storage/' . $headBrand->site_logo_path)
            : ($headBrand?->logo_path
                ? asset('storage/' . $headBrand->logo_path)
                : match ($headBrandName) {
                    'Inkspire Media House' => asset('images/inkspire-logo-navsite.png'),
                    default => asset('images/CreativeVision LOGO-navsite.png'),
                });
    @endphp
    <title>{{ $headAppName }}</title>
    <link rel="icon" type="image/png" href="{{ $siteIdentityLogo }}">
    <link rel="apple-touch-icon" href="{{ $siteIdentityLogo }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            --brand-soft: color-mix(in srgb, var(--brand-primary) 12%, white);
            --brand-soft-strong: color-mix(in srgb, var(--brand-primary) 18%, white);
            --brand-border: color-mix(in srgb, var(--brand-primary) 34%, white);
            --brand-dark-soft: color-mix(in srgb, var(--brand-primary) 20%, transparent);
        }

        button[class*="bg-zinc-950"][class*="text-amber-100"],
        a[class*="bg-zinc-950"][class*="text-amber-100"],
        button[class*="bg-amber-400"],
        a[class*="bg-amber-400"],
        input[type="submit"][class*="bg-zinc-950"][class*="text-amber-100"] {
            background-color: var(--brand-primary) !important;
            color: white !important;
        }

        button[class*="bg-zinc-950"][class*="text-amber-100"]:hover,
        a[class*="bg-zinc-950"][class*="text-amber-100"]:hover,
        button[class*="bg-amber-400"]:hover,
        a[class*="bg-amber-400"]:hover {
            background-color: color-mix(in srgb, var(--brand-primary) 88%, black) !important;
        }

        button[class*="border-amber"],
        a[class*="border-amber"],
        label[class*="border-amber"] {
            border-color: var(--brand-border) !important;
            color: var(--brand-primary) !important;
        }

        button[class*="bg-amber-50"],
        a[class*="bg-amber-50"],
        label[class*="bg-amber-50"] {
            background-color: var(--brand-soft) !important;
            color: var(--brand-primary) !important;
        }

        button[class*="bg-amber-50"]:hover,
        a[class*="bg-amber-50"]:hover,
        label[class*="bg-amber-50"]:hover {
            background-color: var(--brand-soft-strong) !important;
        }

        button[class*="text-amber-700"],
        a[class*="text-amber-700"],
        button[class*="text-amber-800"],
        a[class*="text-amber-800"] {
            color: var(--brand-primary) !important;
        }

        table tbody tr[class*="bg-amber-50"],
        table tbody tr[class*="bg-amber-400"] {
            background-color: var(--brand-soft) !important;
        }

        input[type="checkbox"][class*="text-amber"],
        input[type="radio"][class*="text-amber"] {
            color: var(--brand-primary) !important;
            accent-color: var(--brand-primary);
        }

        input[class*="focus:border-amber"]:focus,
        select[class*="focus:border-amber"]:focus,
        textarea[class*="focus:border-amber"]:focus {
            border-color: var(--brand-primary) !important;
            box-shadow: 0 0 0 1px var(--brand-primary) !important;
        }

        input[class*="focus:ring-amber"]:focus,
        select[class*="focus:ring-amber"]:focus,
        textarea[class*="focus:ring-amber"]:focus,
        button[class*="focus:ring-amber"]:focus,
        a[class*="focus:ring-amber"]:focus {
            --tw-ring-color: var(--brand-primary) !important;
        }

        .dark button[class*="bg-amber-50"],
        .dark a[class*="bg-amber-50"],
        .dark label[class*="bg-amber-50"],
        .dark table tbody tr[class*="bg-amber-400"] {
            background-color: var(--brand-dark-soft) !important;
            color: var(--brand-accent) !important;
            border-color: color-mix(in srgb, var(--brand-accent) 35%, transparent) !important;
        }

        .fixed.inset-0.z-50,
        .crm-modal-backdrop {
            position: fixed !important;
            inset: 0 !important;
            top: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            min-height: 100vh !important;
            min-height: 100dvh !important;
            z-index: 1000 !important;
        }

        .crm-modal-panel {
            position: relative;
            z-index: 1001;
        }
    </style>
</head>

<body class="bg-slate-50 font-sans text-slate-900 dark:bg-zinc-950 dark:text-zinc-100"
      style="--brand-primary: {{ $headBrand?->primary_color ?? '#00563f' }}; --brand-accent: {{ $headBrand?->accent_color ?? '#d1fae5' }};">
    <div x-data="{ pageLoading: false }"
         x-init="
            document.addEventListener('submit', (event) => {
                if (!event.target.matches('[data-no-page-loader]')) {
                    pageLoading = true;
                }
            });

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');

                if (!link || link.target === '_blank' || link.hasAttribute('download') || link.dataset.noPageLoader !== undefined) {
                    return;
                }

                const href = link.getAttribute('href');

                if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                    return;
                }

                const nextUrl = new URL(link.href, window.location.href);

                if (nextUrl.origin === window.location.origin && nextUrl.href !== window.location.href) {
                    pageLoading = true;
                }
            });

            window.addEventListener('pageshow', () => pageLoading = false);
         "
         x-show="pageLoading"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm dark:bg-zinc-950/90"
         aria-live="polite"
         aria-label="Loading page">
        <div class="flex min-h-screen">
            <div class="hidden w-72 border-r border-slate-100 bg-slate-50/80 p-6 dark:border-zinc-800 dark:bg-zinc-900/80 lg:block">
                <div class="h-16 w-40 rounded-xl bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                <div class="mt-12 space-y-4">
                    <div class="h-10 rounded-xl bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                    <div class="h-10 rounded-xl bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                    <div class="h-10 rounded-xl bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                    <div class="h-10 rounded-xl bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                </div>
            </div>

            <div class="flex-1 p-8">
                <div class="flex items-center justify-between">
                    <div class="space-y-3">
                        <div class="h-7 w-48 rounded-lg bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                        <div class="h-4 w-72 rounded-lg bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                </div>

                <div class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                    <div class="space-y-6">
                        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="flex items-center gap-4">
                                <div class="h-11 w-11 rounded-full bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                <div class="flex-1 space-y-3">
                                    <div class="h-4 w-48 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                    <div class="h-4 w-28 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                </div>
                            </div>
                            <div class="mt-6 space-y-3">
                                <div class="h-4 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                <div class="h-4 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                <div class="h-4 w-4/5 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="flex items-center gap-4">
                                <div class="h-11 w-11 rounded-full bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                <div class="flex-1 space-y-3">
                                    <div class="h-4 w-48 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                    <div class="h-4 w-28 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                </div>
                            </div>
                            <div class="mt-6 space-y-3">
                                <div class="h-4 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                <div class="h-4 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                                <div class="h-4 w-4/5 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 dark:bg-zinc-900 dark:ring-zinc-800">
                        <div class="h-4 w-full rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                        <div class="h-4 w-1/3 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                        <div class="grid grid-cols-3 gap-2 pt-4">
                            <div class="h-12 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                            <div class="h-12 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                            <div class="h-12 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                            <div class="h-12 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                            <div class="h-12 rounded bg-slate-100 loading-shimmer dark:bg-zinc-800"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex min-h-screen">
        @php
            $currentBrand = auth()->user()->brand;
            $currentBrandName = $currentBrand?->imprint_name ?? 'CreatiVision Outsourcing';
            $currentBrandLogo = $currentBrand?->logo_path
                ? asset('storage/' . $currentBrand->logo_path)
                : match ($currentBrandName) {
                    'Inkspire Media House' => asset('images/inkspire-logo.png'),
                    'CreatiVision Outsourcing' => asset('images/CreativeVision-LOGO-1.png'),
                    default => null,
                };
            $brandPrimaryColor = $currentBrand?->primary_color ?? ($currentBrandName === 'CreatiVision Outsourcing' ? '#065f46' : '#d97706');
            $brandAccentColor = $currentBrand?->accent_color ?? ($currentBrandName === 'CreatiVision Outsourcing' ? '#d1fae5' : '#fef3c7');
        @endphp

        <aside class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-slate-200 bg-[#f6f7fb] dark:border-zinc-800 dark:bg-zinc-950"
               style="--brand-primary: {{ $brandPrimaryColor }}; --brand-accent: {{ $brandAccentColor }}; --brand-active-dark-bg: color-mix(in srgb, {{ $brandPrimaryColor }} 22%, transparent);">
            <div class="shrink-0 border-b border-slate-200 px-5 pb-5 pt-5 dark:border-zinc-800">
                <a href="{{ route('dashboard') }}" class="block">
                    @if ($currentBrandLogo)
                        <div class="inline-flex max-w-full items-center justify-center">
                            <img src="{{ $currentBrandLogo }}"
                                 alt="{{ $currentBrandName }}"
                                 class="{{ $currentBrandName === 'CreatiVision Outsourcing' ? 'h-20' : 'h-20' }} w-auto max-w-full object-contain">
                        </div>
                    @else
                        <div class="flex h-20 w-full items-center justify-center rounded-xl bg-white px-4 text-center text-lg font-bold text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800">
                            {{ $currentBrandName }}
                        </div>
                    @endif
                </a>
            </div>

            @php
                $roleName = auth()->user()->role?->name;
                $departmentName = auth()->user()->department;
                $isAdmin = $roleName === 'Admin';
                $canViewAllLeads = $isAdmin || auth()->user()->hasPermission('view_all_leads');
                $canViewMyLeads = $isAdmin || auth()->user()->hasPermission('view_my_leads');
                $canViewVerificationQueue = $isAdmin || auth()->user()->hasPermission('view_verification_queue');
                $canViewUnassignedLeads = $isAdmin || auth()->user()->hasPermission('view_unassigned_leads');
                $canViewReturnedLeads = $isAdmin || auth()->user()->hasPermission('view_returned_leads');
                $canViewArchivedLeads = $isAdmin || auth()->user()->hasPermission('view_archived_leads');
                $canViewAssignedLeads = $isAdmin || auth()->user()->hasPermission('view_assigned_leads');
                $canViewAssignedLeadsMonitor = $isAdmin || auth()->user()->hasPermission('view_assigned_leads_monitor');
                $canSelfMineAndWorkLeads = ! $isAdmin && $departmentName === 'Sales' && auth()->user()->hasPermission('self_mine_work_leads');
                $canViewSoldMinedLeads = $isAdmin || auth()->user()->hasPermission('view_sold_mined_leads');
                $canViewVerifiedSoldLeads = ! $isAdmin && $roleName === 'Verifier' && auth()->user()->hasPermission('view_verified_sold_leads');
                $showNewLeadsLink = ($canViewAssignedLeads || $canSelfMineAndWorkLeads) && ! $isAdmin && $departmentName === 'Sales';
                $showAssignedLeadsLink = $canViewAssignedLeadsMonitor;
                $canViewTeamLeads = $isAdmin || auth()->user()->hasPermission('view_team_leads');
                $canViewAnyLeadPage = $canViewAllLeads
                    || $canViewMyLeads
                    || $canViewVerificationQueue
                    || $canViewUnassignedLeads
                    || $canSelfMineAndWorkLeads
                    || $canViewAssignedLeads
                    || $canViewAssignedLeadsMonitor
                    || $canViewReturnedLeads
                    || $canViewArchivedLeads;
                $canViewFlatLeadsMenu = $canViewAnyLeadPage && ! $isAdmin && in_array($departmentName, ['Lead Generation', 'Sales'], true);
                $canViewLeadsDropdown = $canViewAnyLeadPage && ! $canViewFlatLeadsMenu;
                $canViewSales = $isAdmin || auth()->user()->hasPermission('view_sales');
                $canViewSalesSection = $canViewSales || $canViewTeamLeads;
                $canViewFlatSalesMenu = $canViewSalesSection && ! $isAdmin && $departmentName === 'Sales';
                $canViewSalesDropdown = $canViewSalesSection && ! $canViewFlatSalesMenu;
                $canViewSalesEndorsementForm = $isAdmin
                    || auth()->user()->hasPermission('view_sales_endorsement_form')
                    || auth()->user()->hasPermission('view_own_sales_endorsements')
                    || auth()->user()->hasPermission('view_all_sales_endorsements');
                $canViewPaymentRecords = $isAdmin || auth()->user()->hasPermission('view_payment_records');
                $canViewSalesActivity = $isAdmin || auth()->user()->hasPermission('view_sales_activity');
                $canViewSalesPerformance = $isAdmin
                    || $departmentName === 'Sales'
                    || auth()->user()->hasPermission('view_sales_performance_mtd')
                    || auth()->user()->hasPermission('manage_sales_targets');
                $canViewProductionReports = $isAdmin || auth()->user()->hasPermission('view_production_reports') || auth()->user()->hasPermission('view_reports');
                $canViewReportOverview = $isAdmin || auth()->user()->hasPermission('view_reports') || $canViewSoldMinedLeads || $canViewVerifiedSoldLeads || $canViewSalesActivity || $canViewSalesPerformance || $canViewProductionReports;
                $canViewAnyReportPage = $canViewReportOverview || $canViewSoldMinedLeads || $canViewVerifiedSoldLeads || $canViewSalesActivity || $canViewSalesPerformance || $canViewProductionReports;
                $canViewFinanceClients = $isAdmin || auth()->user()->hasPermission('view_finance_clients');
                $canViewContractRecords = $isAdmin || auth()->user()->hasPermission('view_contract_records');
                $canViewProductionTaskTracker = $isAdmin
                    || auth()->user()->hasPermission('view_all_fulfillment_trackers')
                    || auth()->user()->hasPermission('view_publishing_tracker')
                    || auth()->user()->hasPermission('view_marketing_tracker')
                    || auth()->user()->hasPermission('view_events_tracker');
                $canViewFulfillmentTracker = $canViewProductionTaskTracker
                    || auth()->user()->hasPermission('view_client_project_progress');
                $canViewProductionTasks = auth()->user()->hasPermission('view_my_production_tasks');
                $canViewProductionProjects = $canViewFulfillmentTracker || $canViewProductionTasks;
                $canManageUsers = $isAdmin || auth()->user()->hasPermission('manage_users');
                $canManageRolesPermissions = $isAdmin || auth()->user()->hasPermission('manage_roles_permissions');
                $canManageServices = $isAdmin || auth()->user()->hasPermission('manage_services');
                $canViewServicesCatalog = $isAdmin || auth()->user()->hasPermission('view_services_catalog') || $canManageServices;
                $canViewTeams = $isAdmin || auth()->user()->hasPermission('view_all_teams') || auth()->user()->hasPermission('manage_teams');
                $canManageTeams = $isAdmin || auth()->user()->hasPermission('manage_teams');
                $canManageAnnouncements = $isAdmin || auth()->user()->hasPermission('manage_announcements');
                $canManageDashboardBanners = $isAdmin || auth()->user()->hasPermission('manage_dashboard_banners');
                $canManageCommissionSettings = $isAdmin;
                $notifications = auth()->user()
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
                        'url' => $notification->data['url'] ?? url()->current(),
                        'read' => ! is_null($notification->read_at),
                        'created_at' => $notification->created_at?->diffForHumans(),
                    ])
                    ->values();
                $unreadNotificationCount = auth()->user()->unreadNotifications()->count();
                $salesWorkflowActive = request()->routeIs(
                    'sales.team-leads',
                    'sales.pipeline',
                    'sales.prospect',
                    'sales.scheduled-callback',
                    'sales.sold',
                    'sales.refunds'
                );
                $reportsActive = request()->routeIs('reports.*');
                $sidebarLink = fn (bool $active) => $active
                    ? 'flex items-center gap-3 rounded-lg bg-[var(--brand-accent)] px-3 py-2 text-sm font-semibold text-[var(--brand-primary)] dark:bg-[var(--brand-active-dark-bg)] dark:text-[var(--brand-accent)]'
                    : 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:text-zinc-300 dark:hover:bg-zinc-900';

                $sidebarIcon = fn (bool $active) => $active
                    ? 'text-[var(--brand-primary)] dark:text-[var(--brand-accent)]'
                    : 'text-slate-500 dark:text-zinc-500';

                $sidebarSubLink = fn (bool $active) => $active
                    ? 'block rounded-lg bg-[var(--brand-accent)] px-3 py-2 text-sm font-semibold text-[var(--brand-primary)] dark:bg-[var(--brand-active-dark-bg)] dark:text-[var(--brand-accent)]'
                    : 'block rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-white dark:text-zinc-400 dark:hover:bg-zinc-900';
            @endphp

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-6"
                 x-data="{
                    leadBadgeCounts: { unassigned: 0, returned: 0, archived: 0 },
                    productionBadgeCounts: { new_endorsed_projects: 0 },
                    refreshLeadBadgeCounts() {
                        fetch('{{ route('leads.sidebar-counts') }}', { headers: { 'Accept': 'application/json' } })
                            .then(response => response.ok ? response.json() : this.leadBadgeCounts)
                            .then(data => this.leadBadgeCounts = {
                                unassigned: data.unassigned || 0,
                                returned: data.returned || 0,
                                archived: data.archived || 0,
                            })
                            .catch(() => {});
                    },
                    refreshProductionBadgeCounts() {
                        fetch('{{ route('production.sidebar-counts') }}', { headers: { 'Accept': 'application/json' } })
                            .then(response => response.ok ? response.json() : this.productionBadgeCounts)
                            .then(data => this.productionBadgeCounts = {
                                new_endorsed_projects: data.new_endorsed_projects || 0,
                            })
                            .catch(() => {});
                    }
                 }"
                 x-init="refreshLeadBadgeCounts(); refreshProductionBadgeCounts(); setInterval(() => { refreshLeadBadgeCounts(); refreshProductionBadgeCounts(); }, 5000)">
                <div class="space-y-1 border-b border-slate-200 pb-4 dark:border-zinc-800">
                    <a href="{{ route('dashboard') }}" class="{{ $sidebarLink(request()->routeIs('dashboard')) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('dashboard')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3 10.5 9-7 9 7" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10v10h14V10" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20v-6h6v6" />
                        </svg>
                        Home
                    </a>

                    <a href="{{ route('notes.index') }}" class="{{ $sidebarLink(request()->routeIs('notes.*')) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('notes.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h7.086a2.25 2.25 0 0 1 1.591.659l2.414 2.414a2.25 2.25 0 0 1 .659 1.591V20.25H7.5A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 3.75V7.5a.75.75 0 0 0 .75.75h3.75M8.25 12h7.5M8.25 15h7.5" />
                        </svg>
                        Notes
                    </a>

                    <a href="{{ route('calendar.index') }}" class="{{ $sidebarLink(request()->routeIs('calendar.*')) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('calendar.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75v2.5m10.5-2.5v2.5M4.5 9.25h15" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 5.25h10.5A2.25 2.25 0 0 1 19.5 7.5v9.75a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V7.5a2.25 2.25 0 0 1 2.25-2.25Z" />
                        </svg>
                        Calendar
                    </a>

                    <a href="{{ route('announcements.index') }}" class="{{ $sidebarLink(request()->routeIs('announcements.*')) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('announcements.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9m-9 6h9m-9 6h9" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6h.01M4.5 12h.01M4.5 18h.01" />
                        </svg>
                        Announcements
                    </a>

                    @if ($canViewServicesCatalog)
                        <a href="{{ route('services.index') }}" class="{{ $sidebarLink(request()->routeIs('services.*')) }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('services.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5h15M6 7.5V18a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 18V7.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V5.25A2.25 2.25 0 0 1 10.5 3h3A2.25 2.25 0 0 1 15.75 5.25V7.5M9 12h6M9 15h6" />
                            </svg>
                            Services
                        </a>
                    @endif

                    @if ($canViewLeadsDropdown)
                    <div x-data="{ open: {{ request()->routeIs('leads.*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button type="button"
                                x-on:click="open = !open"
                                class="{{ $sidebarLink(request()->routeIs('leads.*')) }} w-full justify-between">
                            <span class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h.01M3 12h.01M3 18h.01" />
                                </svg>
                                Leads
                            </span>

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4 transition-transform {{ $sidebarIcon(request()->routeIs('leads.*')) }}"
                                 x-bind:class="{ 'rotate-180': open }"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor"
                                 stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="-translate-y-1 opacity-0"
                             x-transition:enter-end="translate-y-0 opacity-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="translate-y-0 opacity-100"
                             x-transition:leave-end="-translate-y-1 opacity-0"
                             class="ml-7 space-y-1 border-l border-slate-200 pl-3 dark:border-zinc-800">
                            @if ($canViewAllLeads)
                                <a href="{{ route('leads.index') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.index')) }}">
                                    Mine Leads
                                </a>
                            @endif
                            @if ($canViewMyLeads)
                                <a href="{{ route('leads.my') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.my')) }}">
                                    My Leads
                                </a>
                            @endif
                            @if ($canViewVerificationQueue)
                                <a href="{{ route('leads.verification-queue') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.verification-queue')) }}">
                                    Verification Queue
                                </a>
                            @endif
                            @if ($canViewUnassignedLeads)
                                <a href="{{ route('leads.unassigned') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.unassigned')) }}">
                                    <span class="flex items-center justify-between gap-2">
                                        <span>Unassigned Leads</span>
                                        <span x-show="leadBadgeCounts.unassigned > 0"
                                              x-text="leadBadgeCounts.unassigned"
                                              class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                    </span>
                                </a>
                            @endif
                            @if ($showNewLeadsLink)
                                <a href="{{ route('leads.new') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.new')) }}">
                                    New Leads
                                </a>
                            @endif
                            @if ($showAssignedLeadsLink)
                                <a href="{{ route('leads.assigned') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.assigned')) }}">
                                    Assigned Leads
                                </a>
                            @endif
                            @if ($canViewReturnedLeads)
                                <a href="{{ route('leads.returned') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.returned')) }}">
                                    <span class="flex items-center justify-between gap-2">
                                        <span>Returned Leads</span>
                                        <span x-show="leadBadgeCounts.returned > 0"
                                              x-text="leadBadgeCounts.returned"
                                              class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                    </span>
                                </a>
                            @endif
                            @if ($canViewArchivedLeads)
                                <a href="{{ route('leads.archived') }}" class="{{ $sidebarSubLink(request()->routeIs('leads.archived')) }}">
                                    <span class="flex items-center justify-between gap-2">
                                        <span>Archived Leads</span>
                                        <span x-show="leadBadgeCounts.archived > 0"
                                              x-text="leadBadgeCounts.archived"
                                              class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                    </span>
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if ($canViewSalesDropdown)
                    <div x-data="{ open: {{ $salesWorkflowActive ? 'true' : 'false' }} }" class="space-y-1">
                        <button type="button"
                                x-on:click="open = !open"
                                class="{{ $sidebarLink($salesWorkflowActive) }} w-full justify-between">
                            <span class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon($salesWorkflowActive) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 7h7v7" />
                                </svg>
                                Sales
                            </span>

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4 transition-transform {{ $sidebarIcon($salesWorkflowActive) }}"
                                 x-bind:class="{ 'rotate-180': open }"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor"
                                 stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="-translate-y-1 opacity-0"
                             x-transition:enter-end="translate-y-0 opacity-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="translate-y-0 opacity-100"
                             x-transition:leave-end="-translate-y-1 opacity-0"
                            class="ml-7 space-y-1 border-l border-slate-200 pl-3 dark:border-zinc-800">
                            @if ($canViewTeamLeads)
                                <a href="{{ route('sales.team-leads') }}" class="{{ $sidebarSubLink(request()->routeIs('sales.team-leads')) }}">
                                    Team Leads
                                </a>
                            @endif
                            @if ($canViewSales)
                            <a href="{{ route('sales.pipeline') }}" class="{{ $sidebarSubLink(request()->routeIs('sales.pipeline')) }}">
                                Pipeline
                            </a>
                            <a href="{{ route('sales.prospect') }}" class="{{ $sidebarSubLink(request()->routeIs('sales.prospect')) }}">
                                Prospect
                            </a>
                            <a href="{{ route('sales.scheduled-callback') }}" class="{{ $sidebarSubLink(request()->routeIs('sales.scheduled-callback')) }}">
                                Scheduled Callback
                            </a>
                            <a href="{{ route('sales.sold') }}" class="{{ $sidebarSubLink(request()->routeIs('sales.sold')) }}">
                                Sold
                            </a>
                            <a href="{{ route('sales.refunds') }}" class="{{ $sidebarSubLink(request()->routeIs('sales.refunds')) }}">
                                Refunds
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                @if ($canViewFlatLeadsMenu)
                    <div class="border-b border-slate-200 py-4 dark:border-zinc-800">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            Leads
                        </p>

                        @if ($canViewAllLeads)
                            <a href="{{ route('leads.index') }}" class="{{ $sidebarLink(request()->routeIs('leads.index')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.index')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h.01M3 12h.01M3 18h.01" />
                                </svg>
                                Mine Leads
                            </a>
                        @endif

                        @if ($canViewMyLeads)
                            <a href="{{ route('leads.my') }}" class="{{ $sidebarLink(request()->routeIs('leads.my')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.my')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0" />
                                </svg>
                                My Leads
                            </a>
                        @endif

                        @if ($canViewVerificationQueue)
                            <a href="{{ route('leads.verification-queue') }}" class="{{ $sidebarLink(request()->routeIs('leads.verification-queue')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.verification-queue')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 4.5 7.125v5.625c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5V7.125L12 3.75Z" />
                                </svg>
                                Verification Queue
                            </a>
                        @endif

                        @if ($canViewUnassignedLeads)
                            <a href="{{ route('leads.unassigned') }}" class="{{ $sidebarLink(request()->routeIs('leads.unassigned')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.unassigned')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7.5h6M6 12h6M6 16.5h8" />
                                </svg>
                                <span class="flex flex-1 items-center justify-between gap-2">
                                    <span>Unassigned Leads</span>
                                    <span x-show="leadBadgeCounts.unassigned > 0"
                                          x-text="leadBadgeCounts.unassigned"
                                          class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                </span>
                            </a>
                        @endif

                        @if ($showNewLeadsLink)
                            <a href="{{ route('leads.new') }}" class="{{ $sidebarLink(request()->routeIs('leads.new')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.new')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 4.5 7.125v5.625c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5V7.125L12 3.75Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6" />
                                </svg>
                                New Leads
                            </a>
                        @endif
                        @if ($showAssignedLeadsLink)
                            <a href="{{ route('leads.assigned') }}" class="{{ $sidebarLink(request()->routeIs('leads.assigned')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.assigned')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 20.25a7.5 7.5 0 0 0-15 0" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.5 12.75 1.5 1.5 3-3" />
                                </svg>
                                Assigned Leads
                            </a>
                        @endif

                        @if ($canViewReturnedLeads)
                            <a href="{{ route('leads.returned') }}" class="{{ $sidebarLink(request()->routeIs('leads.returned')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.returned')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25 4.5 9.75 9 5.25" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9.75h10.75A4.75 4.75 0 0 1 20 14.5v.25A4.75 4.75 0 0 1 15.25 19.5H12" />
                                </svg>
                                <span class="flex flex-1 items-center justify-between gap-2">
                                    <span>Returned Leads</span>
                                    <span x-show="leadBadgeCounts.returned > 0"
                                          x-text="leadBadgeCounts.returned"
                                          class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                </span>
                            </a>
                        @endif

                        @if ($canViewArchivedLeads)
                            <a href="{{ route('leads.archived') }}" class="{{ $sidebarLink(request()->routeIs('leads.archived')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('leads.archived')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5v9A2.25 2.25 0 0 1 18 18.75H6A2.25 2.25 0 0 1 3.75 16.5v-9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M9.75 10.5h4.5" />
                                </svg>
                                <span class="flex flex-1 items-center justify-between gap-2">
                                    <span>Archived Leads</span>
                                    <span x-show="leadBadgeCounts.archived > 0"
                                          x-text="leadBadgeCounts.archived"
                                          class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                </span>
                            </a>
                        @endif
                    </div>
                @endif

                @if ($canViewFlatSalesMenu)
                    <div class="border-b border-slate-200 py-4 dark:border-zinc-800">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            Sales
                        </p>

                        @if ($canViewTeamLeads)
                            <a href="{{ route('sales.team-leads') }}" class="{{ $sidebarLink(request()->routeIs('sales.team-leads')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.team-leads')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 8.25h3m-1.5-1.5v3" />
                                </svg>
                                Team Leads
                            </a>
                        @endif

                        @if ($canViewSales)
                        <a href="{{ route('sales.pipeline') }}" class="{{ $sidebarLink(request()->routeIs('sales.pipeline')) }} mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.pipeline')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V9m5 7V5m5 11v-4" />
                            </svg>
                            Pipeline
                        </a>

                        <a href="{{ route('sales.prospect') }}" class="{{ $sidebarLink(request()->routeIs('sales.prospect')) }} mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.prospect')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75 21 21" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z" />
                            </svg>
                            Prospect
                        </a>

                        <a href="{{ route('sales.scheduled-callback') }}" class="{{ $sidebarLink(request()->routeIs('sales.scheduled-callback')) }} mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.scheduled-callback')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75v2.5m10.5-2.5v2.5M4.5 9.25h15" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 5.25h10.5A2.25 2.25 0 0 1 19.5 7.5v9.75a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V7.5a2.25 2.25 0 0 1 2.25-2.25Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25h3.75" />
                            </svg>
                            Scheduled Callback
                        </a>

                        <a href="{{ route('sales.sold') }}" class="{{ $sidebarLink(request()->routeIs('sales.sold')) }} mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.sold')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 4.5 7.125v5.625c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5V7.125L12 3.75Z" />
                            </svg>
                            Sold
                        </a>

                        <a href="{{ route('sales.refunds') }}" class="{{ $sidebarLink(request()->routeIs('sales.refunds')) }} mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.refunds')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25 4.5 9.75 9 5.25" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9.75h10.75A4.75 4.75 0 0 1 20 14.5v.25A4.75 4.75 0 0 1 15.25 19.5H12" />
                            </svg>
                            Refunds
                        </a>
                        @endif
                    </div>
                @endif

                @if ($canViewAnyReportPage)
                    <div class="border-b border-slate-200 py-4 dark:border-zinc-800">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            Reports
                        </p>

                        @if ($canViewReportOverview)
                            <a href="{{ route('reports.index') }}" class="{{ $sidebarLink(request()->routeIs('reports.index')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('reports.index')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19.5h16.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 16.5v-6m4.5 6v-9m4.5 9v-3" />
                                </svg>
                                Overview
                            </a>
                        @endif

                        @if ($canViewSalesActivity)
                            <a href="{{ route('reports.sales-activity.index') }}" class="{{ $sidebarLink(request()->routeIs('reports.sales-activity.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('reports.sales-activity.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19.5h16.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 16.5V9.75m5.25 6.75V4.5m5.25 12V12" />
                                </svg>
                                Sales Activity
                            </a>
                        @endif

                        @if ($canViewSalesPerformance)
                            <a href="{{ route('reports.sales-performance.index') }}" class="{{ $sidebarLink(request()->routeIs('reports.sales-performance.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('reports.sales-performance.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19.5h16.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15.75 10.5 12l3 2.25 3.75-6" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 19.5v-3.75m4.5 3.75V12m4.5 7.5V8.25" />
                                </svg>
                                Sales Performance MTD
                            </a>
                        @endif

                        @if ($canViewSoldMinedLeads)
                            <a href="{{ route('reports.sold-mined') }}" class="{{ $sidebarLink(request()->routeIs('reports.sold-mined')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('reports.sold-mined')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 16.5V9.75m4.5 6.75V5.25m4.5 11.25v-4.5" />
                                </svg>
                                Sold Leads
                            </a>
                        @endif

                        @if ($canViewVerifiedSoldLeads)
                            <a href="{{ route('reports.verified-sold') }}" class="{{ $sidebarLink(request()->routeIs('reports.verified-sold')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('reports.verified-sold')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 4.5 7.125v5.625c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5V7.125L12 3.75Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2 2 4-5" />
                                </svg>
                                Verified Sold Leads
                            </a>
                        @endif

                        @if ($canViewProductionReports)
                            <a href="{{ route('reports.production.index') }}" class="{{ $sidebarLink(request()->routeIs('reports.production.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('reports.production.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15M6.75 16.5v-4.5m5.25 4.5V7.5m5.25 9v-7" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5h15v3h-15z" />
                                </svg>
                                Production Report
                            </a>
                        @endif
                    </div>
                @endif

                @if ($canViewSalesEndorsementForm || $canViewPaymentRecords || $canViewFinanceClients || $canViewContractRecords)
                    <div class="border-b border-slate-200 py-4 dark:border-zinc-800">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            Finance
                        </p>

                        @if ($canViewSalesEndorsementForm)
                            <a href="{{ route('sales.endorsements.index') }}" class="{{ $sidebarLink(request()->routeIs('sales.endorsements.*')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('sales.endorsements.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25 11.25 16.5 15 10.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12A2.25 2.25 0 0 1 17.25 20.25H6.75A2.25 2.25 0 0 1 4.5 18V6A2.25 2.25 0 0 1 6.75 3.75Z" />
                                </svg>
                                Sales Endorsement
                            </a>
                        @endif

                        @if ($canViewPaymentRecords)
                            <a href="{{ route('finance.payments.index') }}" class="{{ $sidebarLink(request()->routeIs('finance.payments.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('finance.payments.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M3.75 6h16.5A1.5 1.5 0 0 1 21.75 7.5v9A1.5 1.5 0 0 1 20.25 18H3.75A1.5 1.5 0 0 1 2.25 16.5v-9A1.5 1.5 0 0 1 3.75 6Z" />
                                </svg>
                                Payment Records
                            </a>
                        @endif

                        @if ($canViewFinanceClients)
                            <a href="{{ route('finance.clients.sold') }}" class="{{ $sidebarLink(request()->routeIs('finance.clients.sold')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('finance.clients.sold')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 4.5 7.125v5.625c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5V7.125L12 3.75Z" />
                                </svg>
                                Sold Clients
                            </a>

                            <a href="{{ route('finance.clients.refunds-disputes') }}" class="{{ $sidebarLink(request()->routeIs('finance.clients.refunds-disputes')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('finance.clients.refunds-disputes')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                </svg>
                                Refunds &amp; Disputes
                            </a>
                        @endif

                        @if ($canViewContractRecords)
                            <a href="{{ route('finance.contracts.index') }}" class="{{ $sidebarLink(request()->routeIs('finance.contracts.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('finance.contracts.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-6a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 8.25v7.5A2.25 2.25 0 0 0 6.75 18h5.25" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18 2.25 2.25L21 15.75" />
                                </svg>
                                Contracts
                            </a>
                        @endif
                    </div>
                @endif

                @if ($canViewProductionProjects)
                    <div class="border-b border-slate-200 py-4 dark:border-zinc-800">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            Production
                        </p>

                        @if ($canViewFulfillmentTracker)
                            <a href="{{ route('production.projects.index') }}" class="{{ $sidebarLink(request()->routeIs('production.projects.*')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('production.projects.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5h15M4.5 12h15M4.5 16.5h9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.5 16.5 1.5 1.5 3-3" />
                                </svg>
                                <span class="flex flex-1 items-center justify-between gap-2">
                                    <span>Fulfillment Tracker</span>
                                    <span x-show="productionBadgeCounts.new_endorsed_projects > 0"
                                          x-text="productionBadgeCounts.new_endorsed_projects"
                                          class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-400/15 dark:text-rose-300"></span>
                                </span>
                            </a>

                            @if ($canViewProductionTaskTracker)
                                <a href="{{ route('production.tasks.tracker') }}" class="{{ $sidebarLink(request()->routeIs('production.tasks.tracker')) }} mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('production.tasks.tracker')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12M8.25 17.25h12" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h.008v.008H3.75V6.75Zm0 5.25h.008v.008H3.75V12Zm0 5.25h.008v.008H3.75v-.008Z" />
                                    </svg>
                                    Task Tracker
                                </a>
                            @endif
                        @endif

                        @if ($canViewProductionTasks)
                            <a href="{{ route('production.tasks.index') }}" class="{{ $sidebarLink(request()->routeIs('production.tasks.index')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('production.tasks.index')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                My New Task
                            </a>

                            <a href="{{ route('production.tasks.completed') }}" class="{{ $sidebarLink(request()->routeIs('production.tasks.completed')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('production.tasks.completed')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 12 10.5 15 16.5 9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                My Complete Tasks
                            </a>
                        @endif
                    </div>
                @endif

                @if ($canManageUsers || $canManageRolesPermissions || $canManageServices || $canViewTeams || $canManageAnnouncements || $canManageDashboardBanners || $canManageCommissionSettings)
                    <div class="py-4">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            Admin
                        </p>

                        @if ($canManageUsers)
                            <a href="{{ route('admin.users.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.users.*')) }} mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.users.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11a4 4 0 1 0-8 0" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21a7 7 0 0 1 14 0" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 8v6M22 11h-6" />
                                </svg>
                                Users
                            </a>
                        @endif

                        @if ($canViewTeams)
                            <a href="{{ route('admin.teams.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.teams.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.teams.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 21a5.5 5.5 0 0 1 11 0M10.5 21a5.5 5.5 0 0 1 11 0" />
                                </svg>
                                Teams
                            </a>
                        @endif

                        @if ($isAdmin)
                            <a href="{{ route('admin.brands.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.brands.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.brands.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 21V6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25V21" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 8.25h2.25m-2.25 3h2.25m-2.25 3h2.25m3-6h2.25m-2.25 3h2.25m-2.25 3h2.25" />
                                </svg>
                                Brands / Accounts
                            </a>
                        @endif

                        @if ($canManageDashboardBanners)
                            <a href="{{ route('admin.dashboard-banners.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.dashboard-banners.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.dashboard-banners.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5v13.5H3.75V5.25Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 15 4.5-4.5 3 3 2.25-2.25 6.75 6.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 8.25h.01" />
                                </svg>
                                Dashboard Banners
                            </a>
                        @endif

                        @if ($canManageCommissionSettings)
                            <a href="{{ route('admin.commission-settings.edit') }}" class="{{ $sidebarLink(request()->routeIs('admin.commission-settings.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.commission-settings.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m4.5-8.25c0-1.243-2.015-2.25-4.5-2.25s-4.5 1.007-4.5 2.25S9.515 12 12 12s4.5 1.007 4.5 2.25S14.485 16.5 12 16.5s-4.5-1.007-4.5-2.25" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Commission Settings
                            </a>
                        @endif

                        @if ($canManageServices)
                            <a href="{{ route('admin.services.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.services.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.services.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5M8.25 17.25h4.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 3.75h13.5A2.25 2.25 0 0 1 21 6v12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18V6a2.25 2.25 0 0 1 2.25-2.25Z" />
                                </svg>
                                Services
                            </a>
                        @endif

                        @if ($canManageAnnouncements)
                            <a href="{{ route('admin.announcements.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.announcements.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.announcements.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9m-9 6h9m-9 6h9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6h.01M4.5 12h.01M4.5 18h.01" />
                                </svg>
                                Manage Announcements
                            </a>
                        @endif

                        @if ($canManageRolesPermissions)
                            <a href="{{ route('admin.roles-permissions.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.roles-permissions.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.roles-permissions.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.5 3 8.5 7 10 4-1.5 7-5.5 7-10V6l-7-3Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12 1.5 1.5 3.5-4" />
                                </svg>
                                Roles &amp; Permissions
                            </a>
                        @endif

                        @if ($isAdmin)
                            <a href="{{ route('admin.trash.index') }}" class="{{ $sidebarLink(request()->routeIs('admin.trash.*')) }} mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $sidebarIcon(request()->routeIs('admin.trash.*')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                Trash
                            </a>
                        @endif
                    </div>
                @endif
            </nav>
        </aside>

        <main class="ml-72 flex min-h-screen w-[calc(100%-18rem)] flex-col overflow-x-hidden">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-8 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">
                        {{ $header ?? 'Dashboard' }}
                    </h2>
                </div>

                <div class="flex items-center gap-4">
                    <button type="button"
                            x-on:click="darkMode = !darkMode"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-amber-200 dark:hover:bg-zinc-800"
                            aria-label="Toggle theme">
                        <span x-show="!darkMode">🌙 Dark</span>
                        <span x-show="darkMode" x-cloak>☀ Light</span>
                    </button>

                    <div class="relative"
                         x-data="{
                            open: false,
                            unreadCount: @js($unreadNotificationCount),
                            notifications: @js($notifications),
                            async refreshNotifications() {
                                const response = await fetch('{{ route('notifications.index') }}', {
                                    headers: { 'Accept': 'application/json' },
                                });

                                if (!response.ok) return;

                                const data = await response.json();
                                this.unreadCount = data.unread_count;
                                this.notifications = data.notifications;
                            },
                            markRead(notification) {
                                const targetUrl = notification.url || '{{ route('notifications.index') }}';
                                const notificationsUrl = @js(url('/notifications'));
                                const formData = new FormData();

                                formData.append('_token', '{{ csrf_token() }}');

                                if (navigator.sendBeacon) {
                                    navigator.sendBeacon(`${notificationsUrl}/${notification.id}/read`, formData);
                                } else {
                                    fetch(`${notificationsUrl}/${notification.id}/read`, {
                                        method: 'POST',
                                        body: formData,
                                        keepalive: true,
                                        headers: {
                                            'Accept': 'application/json',
                                        },
                                    }).catch(() => {});
                                }

                                window.location.assign(targetUrl);
                            },
                            async markAllRead() {
                                const response = await fetch('{{ route('notifications.read-all') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    },
                                });

                                if (!response.ok) return;

                                const data = await response.json();
                                this.unreadCount = data.unread_count;
                                this.notifications = data.notifications;
                            }
                         }"
                         x-init="setInterval(() => refreshNotifications(), 5000)"
                         x-on:keydown.escape.window="open = false">
                        <button type="button"
                                x-on:click="open = !open"
                                class="relative inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                title="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022 23.848 23.848 0 0 0 5.455 1.31m5.714 0a3 3 0 0 1-5.714 0" />
                            </svg>

                            <span x-show="unreadCount > 0"
                                  x-cloak
                                  x-text="unreadCount > 9 ? '9+' : unreadCount"
                                  class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"></span>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition.origin.top.right
                             x-on:click.outside="open = false"
                             class="absolute right-0 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-zinc-800">
                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Notifications</p>
                                <button type="button"
                                        x-show="unreadCount > 0"
                                        x-cloak
                                        x-on:click="markAllRead()"
                                        class="text-xs font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-200">
                                    Mark all read
                                </button>
                            </div>

                            <div class="max-h-80 overflow-y-auto">
                                <template x-for="notification in notifications" :key="notification.id">
                                    <button type="button"
                                            x-on:click="markRead(notification)"
                                            x-bind:class="notification.read ? 'bg-white dark:bg-zinc-900' : 'bg-amber-50/70 dark:bg-amber-400/10'"
                                            class="block w-full border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="notification.title"></p>
                                                <p x-show="notification.message"
                                                   x-cloak
                                                   class="mt-1 text-xs leading-5 text-slate-600 dark:text-zinc-300"
                                                   x-text="notification.message"></p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-zinc-400">
                                                    <span x-text="notification.author_name"></span>
                                                    <span> - </span>
                                                    <span x-text="notification.book_title"></span>
                                                </p>
                                                <p class="mt-1 text-[11px] text-slate-400 dark:text-zinc-500" x-text="notification.created_at"></p>
                                            </div>
                                            <span x-show="!notification.read"
                                                  class="mt-1 h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                                        </div>
                                    </button>
                                </template>

                                <div x-show="notifications.length === 0"
                                     class="px-4 py-8 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No notifications yet.
                                </div>
                            </div>

                            <a href="{{ route('notifications.index') }}"
                               class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-amber-700 hover:bg-slate-50 dark:border-zinc-800 dark:text-amber-200 dark:hover:bg-zinc-800">
                                See all notifications
                            </a>
                        </div>
                    </div>

                    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <button type="button"
                                x-on:click="open = !open"
                                class="inline-flex items-center gap-3 rounded-2xl px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-zinc-900"
                                aria-haspopup="true"
                                x-bind:aria-expanded="open.toString()">
                            @if (auth()->user()->profile_photo_path)
                                <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}?v={{ auth()->user()->updated_at?->timestamp }}"
                                     alt="{{ auth()->user()->first_name ?? 'User' }} {{ auth()->user()->last_name ?? '' }}"
                                     class="h-10 w-10 rounded-full object-cover"
                                     style="box-shadow: 0 0 0 1px {{ $brandPrimaryColor }};">
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold"
                                     style="background-color: {{ $brandAccentColor }}; color: {{ $brandPrimaryColor }}; box-shadow: 0 0 0 1px {{ $brandPrimaryColor }};">
                                    {{ strtoupper(substr(auth()->user()->first_name ?? 'U', 0, 1)) }}
                                </div>
                            @endif

                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-800 dark:text-zinc-100">
                                    {{ auth()->user()->first_name ?? 'User' }} {{ auth()->user()->last_name ?? '' }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">
                                    {{ auth()->user()->role->name ?? 'No Role' }}
                                </p>
                            </div>

                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition.origin.top.right
                             x-on:click.outside="open = false"
                             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-zinc-800">
                                <div class="flex items-center gap-3">
                                    @if (auth()->user()->profile_photo_path)
                                        <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}?v={{ auth()->user()->updated_at?->timestamp }}"
                                             alt="{{ auth()->user()->first_name ?? 'User' }} {{ auth()->user()->last_name ?? '' }}"
                                             class="h-10 w-10 rounded-full object-cover"
                                             style="box-shadow: 0 0 0 1px {{ $brandPrimaryColor }};">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold"
                                             style="background-color: {{ $brandAccentColor }}; color: {{ $brandPrimaryColor }}; box-shadow: 0 0 0 1px {{ $brandPrimaryColor }};">
                                            {{ strtoupper(substr(auth()->user()->first_name ?? 'U', 0, 1)) }}
                                        </div>
                                    @endif

                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                            {{ auth()->user()->first_name ?? 'User' }} {{ auth()->user()->last_name ?? '' }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-zinc-400">
                                            {{ auth()->user()->email }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('profile.edit') }}"
                               class="block px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                Profile
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="block w-full px-4 py-3 text-left text-sm font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-400/10">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <section class="flex-1 overflow-x-hidden p-8">
                {{ $slot }}
            </section>

            <footer class="border-t border-slate-200 px-8 py-4 text-center text-sm text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
                Copyright {{ now()->year }} | CreatiVision Outsourcing Team
            </footer>
        </main>
    </div>
</body>
</html>
