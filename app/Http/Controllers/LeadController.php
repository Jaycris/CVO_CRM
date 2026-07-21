<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\SalesEndorsement;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\LeadReturnedToAgentNotification;
use App\Notifications\LeadSentToVerificationNotification;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class LeadController extends Controller
{
    public function index(): View
    {
        return $this->leadIndexView('all', request());
    }

    public function myLeads(Request $request): View
    {
        return $this->leadIndexView('mine', $request);
    }

    public function verificationQueue(): View
    {
        return $this->leadIndexView('verification_queue', request());
    }

    public function unassignedLeads(): View
    {
        return $this->leadIndexView('unassigned', request());
    }

    public function assignedLeads(Request $request): View
    {
        return $this->leadIndexView('assigned', $request);
    }

    public function newLeads(Request $request): View
    {
        return $this->leadIndexView('new_assigned', $request);
    }

    public function returnedLeads(): View
    {
        return $this->leadIndexView('returned', request());
    }

    public function archivedLeads(): View
    {
        return $this->leadIndexView('archived', request());
    }

    public function salesPipeline(): View
    {
        return $this->leadIndexView('sales_pipeline', request());
    }

    public function salesTeamLeads(): View
    {
        return $this->leadIndexView('sales_team_leads', request());
    }

    public function salesProspect(): View
    {
        return $this->leadIndexView('sales_prospect', request());
    }

    public function salesScheduledCallback(): View
    {
        return $this->leadIndexView('sales_scheduled_callback', request());
    }

    public function salesSold(): View
    {
        return $this->leadIndexView('sales_sold', request());
    }

    public function salesRefunds(): View
    {
        return $this->salesPaymentStatusView(
            'Refund',
            'sales_refunds',
            'Refunds',
            'Track refunded sales from submitted sales endorsements.'
        );
    }

    private function salesPaymentStatusView(string $paymentStatus, string $viewMode, string $pageTitle, string $pageDescription): View
    {
        $request = request();

        $this->ensureCanViewLeadMode($viewMode, $request);

        $endorsements = SalesEndorsement::with(['agent', 'paymentRecord'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereHas('paymentRecord', fn ($query) => $query->where('status', $paymentStatus))
            ->where('agent_id', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('sales.payment-records', [
            'endorsements' => $endorsements,
            'summaryCards' => $this->summaryCards($viewMode, $request),
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'paymentStatus' => $paymentStatus,
        ]);
    }

    private function leadIndexView(string $viewMode, ?Request $request = null): View
    {
        $this->ensureCanViewLeadMode($viewMode, $request);
        $this->markSharedLeadPageSeen($viewMode, $request);

        $query = Lead::with([
            'brand', 'assignedUser', 'previousAgent', 'createdBy', 'verifiedBy',
            'verificationAssignedUser', 'returnedBy', 'archivedBy',
            'assignmentHistories.agent', 'assignmentHistories.assignedBy', 'assignmentHistories.releasedBy',
        ]);
        BrandScope::apply($query, $request?->user());

        match ($viewMode) {
            'mine' => $query->where('created_by', $request?->user()?->id)
                ->whereNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('sales_stage')
                ->whereNull('archived_at')
                ->whereNull('lead_generation_stage'),
            'verification_queue' => $query->where('lead_generation_stage', 'verification_queue')
                ->whereNull('sales_stage')
                ->whereNull('archived_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('verification_assigned_to', $request?->user()->id)),
            'unassigned' => $query->where('lead_generation_stage', 'ready_to_assign')
                ->whereNotNull('verified_at')
                ->whereNull('assigned_to')
                ->whereNull('sales_stage')
                ->whereNull('returned_at')
                ->whereNull('archived_at'),
            'new_assigned' => $query->whereNotNull('assigned_to')
                ->whereNull('sales_stage')
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->when(! $this->canViewAllAssignedLeads($request), fn ($query) => $query->where('assigned_to', $request?->user()->id)),
            'assigned' => $query->whereNotNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->when(! $this->canViewAllAssignedLeads($request), fn ($query) => $query->where('assigned_to', $request?->user()->id)),
            'returned' => $query->whereNotNull('returned_at')
                ->where(function ($query) {
                    $query->whereNull('lead_generation_stage')
                        ->orWhere('lead_generation_stage', '!=', 'verification_queue');
                })
                ->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $this->limitToOwnedOrUnclaimed($query, $request)),
            'sales_team_leads' => $query->whereNotNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->whereHas('assignedUser', fn ($query) => $query->where('department', 'Sales')),
            'sales_pipeline' => $query->whereNotNull('assigned_to')->where('sales_stage', 'pipeline')->whereNull('returned_at')->whereNull('archived_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('assigned_to', $request?->user()->id)),
            'sales_prospect' => $query->where('sales_stage', 'prospect')->whereNull('returned_at')->whereNull('archived_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('assigned_to', $request?->user()->id)),
            'sales_scheduled_callback' => $query->where('sales_stage', 'scheduled_callback')->whereNull('returned_at')->whereNull('archived_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('assigned_to', $request?->user()->id)),
            'sales_sold' => $query->where('sales_stage', 'sold')->whereNull('returned_at')->whereNull('archived_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('assigned_to', $request?->user()->id)),
            'sales_refunds' => $query->whereRaw('1 = 0'),
            default => $query->whereNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('sales_stage')
                ->whereNull('archived_at')
                ->whereNull('lead_generation_stage'),
        };

        $this->applyLeadFilters($query, $request);

        $leads = $query->latest()->paginate(10)->withQueryString();
        $summaryCards = $this->summaryCards($viewMode, $request);
        $salesAssignees = User::with('role')
            ->where('department', 'Sales')
            ->whereNull('suspended_at')
            ->when(! BrandScope::canAccessAllBrands($request?->user()), function ($query) use ($request) {
                $query->where('brand_id', $request?->user()?->brand_id);
            })
            ->whereHas('role', fn ($query) => $query->whereIn('name', [
                'Branding Specialist',
                'Team Leader',
                'Sales Director',
                'Operation Manager',
            ]))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        [$pageTitle, $pageDescription] = $this->leadPageCopy($viewMode);

        return view('leads.index', compact(
            'leads',
            'summaryCards',
            'salesAssignees',
            'pageTitle',
            'pageDescription',
            'viewMode'
        ));
    }

    public function create(): View
    {
        $this->ensureCanCreateLeads(request());

        return view('leads.create', [
            'returnTo' => $this->safeReturnUrl(request('return_to')) ?? route('leads.my'),
            'canSelfMineAndWork' => $this->userCanSelfMineAndWork(request()),
        ]);
    }

    public function importForm(): View
    {
        $this->ensureCanCreateLeads(request());

        return view('leads.import', [
            'returnTo' => $this->safeReturnUrl(request('return_to')) ?? route('leads.my'),
        ]);
    }

    public function downloadImportTemplate()
    {
        $this->ensureCanCreateLeads(request());

        $headers = [
            'Publisher',
            'Book Title',
            'Author Name',
            'Phone Numbers',
            'Email',
            'Book Link',
            'Published Date',
        ];

        $example = [
            'Forbes Books',
            'Sample Book Title',
            'Sample Author',
            '(602) 446-5352 | (602) 348-3580',
            'author@example.com',
            'https://example.com/book',
            '2026-06-16',
        ];

        return response()->streamDownload(function () use ($headers, $example) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, $example);
            fclose($file);
        }, 'lead-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->ensureCanCreateLeads($request);

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
            'lead_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = fopen($request->file('lead_csv')->getRealPath(), 'r');

        if ($file === false) {
            return back()
                ->withErrors(['lead_csv' => 'The CSV file could not be opened. Please upload the file again.'])
                ->withInput();
        }

        $headers = fgetcsv($file) ?: [];
        $headerMap = $this->csvHeaderMap($headers);
        $requiredHeaders = ['book_title', 'author_name', 'phone_numbers'];
        $missingHeaders = collect($requiredHeaders)
            ->reject(fn (string $header) => array_key_exists($header, $headerMap))
            ->values();

        if ($missingHeaders->isNotEmpty()) {
            fclose($file);

            return back()
                ->withErrors(['lead_csv' => 'The CSV template must include Book Title, Author Name, and Phone Numbers columns.'])
                ->withInput();
        }

        $created = 0;
        $skipped = [];
        $rowNumber = 1;

        try {
            DB::transaction(function () use ($file, $headerMap, $request, &$created, &$skipped, &$rowNumber) {
                while (($row = fgetcsv($file)) !== false) {
                    $rowNumber++;

                    if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                        continue;
                    }

                    $leadData = $this->leadDataFromCsvRow($row, $headerMap);
                    $validator = Validator::make($leadData, [
                        'publisher' => ['nullable', 'string', 'max:255'],
                        'book_title' => ['required', 'string', 'max:255'],
                        'author_name' => ['required', 'string', 'max:255'],
                        'phone_numbers' => ['required', 'array', 'min:1'],
                        'phone_numbers.*' => ['required', 'string'],
                        'email' => ['nullable', 'email', 'max:255'],
                        'book_link' => ['nullable', 'url', 'max:5000'],
                        'published_date' => ['nullable', 'date'],
                    ]);

                    if ($validator->fails()) {
                        $skipped[] = "Row {$rowNumber}: " . $validator->errors()->first();
                        continue;
                    }

                    if ($this->hasDuplicatePhonesInLead($leadData['phone_numbers'])) {
                        $skipped[] = "Row {$rowNumber}: Please remove duplicate phone numbers from this lead.";
                        continue;
                    }

                    if ($duplicateMessage = $this->leadDuplicateMessage($leadData)) {
                        $skipped[] = "Row {$rowNumber}: {$duplicateMessage}";
                        continue;
                    }

                    try {
                        Lead::create([
                            ...$leadData,
                            'brand_id' => BrandScope::userBrandId($request->user()),
                            'created_by' => $request->user()->id,
                        ]);
                    } catch (Throwable $exception) {
                        report($exception);

                        $skipped[] = "Row {$rowNumber}: This row could not be imported. Please check the link, email, and field values.";
                        continue;
                    }

                    $created++;
                }
            });
        } catch (Throwable $exception) {
            report($exception);
            fclose($file);

            return back()
                ->withErrors(['lead_csv' => 'The CSV import could not be completed. Please check the file format and try again.'])
                ->withInput();
        }

        fclose($file);

        $message = $created === 1
            ? '1 lead imported successfully.'
            : "{$created} leads imported successfully.";

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', $message)
            ->with('import_skipped_rows', $skipped);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanCreateLeads($request);

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
            'work_self' => ['nullable', 'boolean'],
        ]);

        $leadData = $this->validatedLeadData($request, false);
        $this->ensureLeadIsNotDuplicate($leadData);
        $workSelf = $request->boolean('work_self') && $this->userCanSelfMineAndWork($request);

        Lead::create([
            ...$leadData,
            'brand_id' => BrandScope::userBrandId($request->user()),
            'created_by' => $request->user()->id,
            'assigned_to' => $workSelf ? $request->user()->id : null,
            'assigned_date' => $workSelf ? now()->toDateString() : null,
        ]);

        $returnUrl = $this->safeReturnUrl($validated['return_to'] ?? null);

        if ($workSelf && ($returnUrl === null || $returnUrl === route('leads.my'))) {
            $returnUrl = route('leads.new');
        }

        return redirect()
            ->to($returnUrl ?? route('leads.my'))
            ->with('success', 'Lead created successfully.');
    }

    public function edit(Lead $lead): View
    {
        $this->ensureCanEditLead(request(), $lead);

        return view('leads.edit', [
            'lead' => $lead,
            'returnTo' => $this->safeReturnUrl(request('return_to')) ?? route('leads.my'),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->ensureCanEditLead($request, $lead);

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $leadData = $this->validatedLeadData($request, false);
        $this->ensureLeadIsNotDuplicate($leadData, $lead);

        $lead->update($leadData);
        $this->syncVerifiedPhoneNumbers($lead);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', 'Lead updated successfully.');
    }

    public function sendReturnedToAgent(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'send_returned_leads_back'), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $sendableLeads = Lead::whereIn('id', $validated['lead_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereNotNull('assigned_to')
            ->whereNotNull('returned_at')
            ->whereNotNull('verified_at')
            ->where('lead_generation_stage', 'ready_to_return')
            ->whereNull('archived_at')
            ->pluck('id');

        if ($sendableLeads->count() !== count(array_unique($validated['lead_ids']))) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.returned'))
                ->with('error', 'Only verified returned leads with an assigned agent can be sent back.');
        }

        $leads = Lead::with('assignedUser')->whereIn('id', $sendableLeads)->get();
        $returnNotifications = $leads
            ->filter(fn (Lead $lead) => $lead->assignedUser && $this->userCanReceiveNotification($lead->assignedUser, 'view_assigned_leads'))
            ->groupBy('assigned_to');

        Lead::whereIn('id', $sendableLeads)->update([
            'returned_at' => null,
            'returned_by' => null,
            'return_notes' => null,
            'sales_stage' => null,
            'sales_stage_updated_at' => null,
            'lead_generation_stage' => null,
            'verification_assigned_to' => null,
        ]);

        $returnNotifications->each(function ($leads) {
            $firstLead = $leads->first();
            $assignedUser = $firstLead?->assignedUser;

            if ($assignedUser) {
                $assignedUser->notify(new LeadReturnedToAgentNotification(
                    $leads->count() === 1 ? $firstLead : null,
                    $leads->count()
                ));
            }
        });

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.returned'))
            ->with('success', 'Selected leads sent back to the assigned agent.');
    }

    public function sendToVerificationQueue(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'send_leads_to_verification'), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $queueableLeads = Lead::whereIn('id', $validated['lead_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereNull('archived_at')
            ->whereNull('sales_stage')
            ->where(function ($query) {
                $query->whereNotNull('returned_at')
                    ->orWhere(function ($query) {
                        $query->whereNull('assigned_to')
                            ->whereNull('returned_at');
                    });
            })
            ->pluck('id');

        if ($queueableLeads->count() !== count(array_unique($validated['lead_ids']))) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
                ->with('error', 'Only active lead-generation records can be sent to verification.');
        }

        $verifiers = $this->availableVerificationUsers($request);

        if ($verifiers->isEmpty()) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
                ->with('error', 'No verifier users are available for Verification Queue.');
        }

        $verificationNotifications = [];

        Lead::whereIn('id', $queueableLeads)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->orderBy('id')
            ->get()
            ->each(function (Lead $lead) use ($verifiers, &$verificationNotifications) {
                $verifier = $this->leastLoadedVerifier($verifiers);

                $lead->update([
                    'lead_generation_stage' => 'verification_queue',
                    'verification_assigned_to' => $verifier->id,
                    'author_confirmed' => false,
                    'book_confirmed' => false,
                    'phone_confirmed' => false,
                    'verified_phone_numbers' => null,
                    'email_confirmed' => false,
                    'verify_score' => null,
                    'verified_at' => null,
                    'verified_by' => null,
                ]);

                $lead->verification_assigned_to = $verifier->id;
                $lead->setRelation('verificationAssignedUser', $verifier);
                $verificationNotifications[$verifier->id]['user'] = $verifier;
                $verificationNotifications[$verifier->id]['leads'][] = $lead;
            });

        collect($verificationNotifications)->each(function (array $group) {
            $leads = collect($group['leads'] ?? []);
            $verifier = $group['user'] ?? null;

            if ($verifier && $leads->isNotEmpty()) {
                $verifier->notify(new LeadSentToVerificationNotification(
                    $leads->count() === 1 ? $leads->first() : null,
                    $leads->count()
                ));
            }
        });

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', 'Selected leads sent to Verification Queue.');
    }

    public function moveVerifiedLeadsToReadyQueue(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'move_verified_leads_to_ready'), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $readyLeads = Lead::whereIn('id', $validated['lead_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->where('lead_generation_stage', 'verification_queue')
            ->whereNotNull('verified_at')
            ->whereNull('archived_at')
            ->whereNull('sales_stage')
            ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('verification_assigned_to', $request->user()->id))
            ->pluck('id');

        if ($readyLeads->count() !== count(array_unique($validated['lead_ids']))) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.verification-queue'))
                ->with('error', 'Only verified leads from Verification Queue can be moved forward.');
        }

        Lead::whereIn('id', $readyLeads)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->get()
            ->each(function (Lead $lead) use ($request) {
            $isReturnedLead = ! is_null($lead->returned_at);

            $lead->update([
                'lead_generation_stage' => $isReturnedLead ? 'ready_to_return' : 'ready_to_assign',
                'verification_assigned_to' => null,
            ]);

        });

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.verification-queue'))
            ->with('success', 'Selected verified leads moved to the next queue.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $request = request();

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        abort_unless(
            $this->userHasPermission($request, 'delete_leads')
            && $this->userCanManageLeadRecord($request, $lead)
            && $this->userCanAccessLeadBrand($request, $lead),
            403
        );

        $lead->delete();

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', 'Lead deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'delete_leads'), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $leadIds = array_unique($validated['lead_ids']);
        $leads = Lead::whereIn('id', $leadIds)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->get();

        abort_unless($leads->count() === count($leadIds), 403);

        $leads->each(function (Lead $lead) use ($request) {
            abort_unless($this->userCanManageLeadRecord($request, $lead), 403);
        });

        Lead::whereIn('id', $leadIds)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->delete();

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', $leads->count() === 1 ? 'Lead deleted successfully.' : "{$leads->count()} leads deleted successfully.");
    }

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $leadIds = array_unique($validated['lead_ids']);
        $cameFromTeamLeads = $this->requestCameFromRoute($validated['return_to'] ?? null, 'sales.team-leads');
        $isTeamReassignment = $cameFromTeamLeads && $this->userCanReassignTeamLeads($request);

        abort_unless($cameFromTeamLeads ? $isTeamReassignment : $this->userCanAssignLeads($request), 403);

        $salesAssignee = User::with('role')->findOrFail($validated['assigned_to']);
        abort_unless($this->userCanAssignToBrand($request, $salesAssignee->brand_id), 403);

        if (
            $salesAssignee->suspended_at
            ||
            $salesAssignee->department !== 'Sales'
            || ! in_array($salesAssignee->role?->name, ['Branding Specialist', 'Team Leader', 'Sales Director', 'Operation Manager'], true)
        ) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
                ->with('error', 'Selected user must be an active Sales department user.');
        }

        if ($isTeamReassignment) {
            $assignableLeads = Lead::whereIn('id', $leadIds)
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->whereNotNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->whereHas('assignedUser', fn ($query) => $query->where('department', 'Sales'))
                ->pluck('id');
        } else {
            $assignableLeads = Lead::whereIn('id', $leadIds)
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->whereNull('assigned_to')
                ->where('lead_generation_stage', 'ready_to_assign')
                ->whereNotNull('verified_at')
                ->when(! $this->userIsAdmin($request), fn ($query) => $this->limitToOwnedOrUnclaimed($query, $request))
                ->pluck('id');
        }

        if ($assignableLeads->count() !== count($leadIds)) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
                ->with('error', $isTeamReassignment
                    ? 'Only active assigned Sales leads can be reassigned.'
                    : 'Only leads in Unassigned Leads / Ready to Assign can be assigned.');
        }

        $updateData = [
            'assigned_to' => $salesAssignee->id,
            'brand_id' => $salesAssignee->brand_id ?? BrandScope::userBrandId($request->user()),
            'assigned_date' => now()->toDateString(),
            'returned_at' => null,
            'returned_by' => null,
            'return_notes' => null,
            'archived_at' => null,
            'archived_by' => null,
            'sales_stage' => null,
            'sales_stage_updated_at' => null,
            'lead_generation_stage' => null,
            'verification_assigned_to' => null,
        ];

        if ($isTeamReassignment) {
            unset($updateData['sales_stage'], $updateData['sales_stage_updated_at']);

            $updateData = [
                ...$updateData,
                'previous_agent_id' => DB::raw('assigned_to'),
                'previous_agent_released_at' => now(),
                'previous_agent_release_reason' => 'Lead reassigned by Sales Team Leader.',
            ];
        }

        DB::transaction(function () use ($assignableLeads, $request, $salesAssignee, $updateData) {
            $currentAssignments = Lead::whereIn('id', $assignableLeads)
                ->pluck('assigned_to', 'id');

            Lead::whereIn('id', $assignableLeads)
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->update($updateData);

            foreach ($assignableLeads as $leadId) {
                $previousAgentId = $currentAssignments->get($leadId);

                if ($previousAgentId && (int) $previousAgentId !== $salesAssignee->id) {
                    $this->closeLeadAssignmentHistory(
                        (int) $leadId,
                        $request->user()->id,
                        'Lead reassigned to another Sales user.'
                    );
                }

                if ((int) $previousAgentId !== $salesAssignee->id) {
                    LeadAssignmentHistory::create([
                        'lead_id' => $leadId,
                        'agent_id' => $salesAssignee->id,
                        'assigned_by' => $request->user()->id,
                        'assigned_at' => now(),
                    ]);
                }
            }
        });

        if ($this->userCanReceiveNotification($salesAssignee, 'view_assigned_leads')) {
            $assignedLeads = Lead::whereIn('id', $assignableLeads)->get();

            if ($assignedLeads->isNotEmpty()) {
                $salesAssignee->notify(new LeadAssignedNotification(
                    $assignedLeads->count() === 1 ? $assignedLeads->first() : null,
                    $assignedLeads->count()
                ));
            }
        }

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', 'Selected leads assigned successfully.');
    }

    public function unassign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $leadIds = array_unique($validated['lead_ids']);
        $cameFromTeamLeads = $this->requestCameFromRoute($validated['return_to'] ?? null, 'sales.team-leads');
        $isTeamUnassignment = $cameFromTeamLeads && $this->userCanUnassignTeamLeads($request);

        abort_unless($cameFromTeamLeads ? $isTeamUnassignment : $this->userCanAssignLeads($request), 403);

        $unassignableLeads = Lead::whereIn('id', $validated['lead_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereNotNull('assigned_to')
            ->when($isTeamUnassignment, function ($query) {
                $query->whereNull('returned_at')
                    ->whereNull('archived_at')
                    ->whereHas('assignedUser', fn ($query) => $query->where('department', 'Sales'));
            })
            ->when(! $isTeamUnassignment && ! $this->userIsAdmin($request), fn ($query) => $this->limitToOwnedOrUnclaimed($query, $request))
            ->pluck('id');

        if ($unassignableLeads->count() !== count($leadIds)) {
            return redirect()
                ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
                ->with('error', $isTeamUnassignment
                    ? 'Only active assigned Sales leads can be unassigned.'
                    : 'Only assigned leads can be unassigned.');
        }

        DB::transaction(function () use ($unassignableLeads, $request) {
            Lead::whereIn('id', $unassignableLeads)
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->update([
                'previous_agent_id' => DB::raw('assigned_to'),
                'previous_agent_released_at' => now(),
                'previous_agent_release_reason' => 'Lead manually unassigned.',
                'assigned_to' => null,
                'assigned_date' => null,
                'returned_at' => null,
                'returned_by' => null,
                'return_notes' => null,
                'sales_stage' => null,
                'sales_stage_updated_at' => null,
                'lead_generation_stage' => 'ready_to_assign',
                'verification_assigned_to' => null,
            ]);

            foreach ($unassignableLeads as $leadId) {
                $this->closeLeadAssignmentHistory(
                    (int) $leadId,
                    $request->user()->id,
                    'Lead manually unassigned.'
                );
            }
        });

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.my'))
            ->with('success', 'Selected leads unassigned successfully.');
    }

    public function moveSalesStage(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'move_sales_stage'), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'sales_stage' => ['required', 'in:pipeline,prospect,scheduled_callback,sold'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $movableLeads = $this->salesActionLeadIds($request, $validated['lead_ids']);

        Lead::whereIn('id', $movableLeads)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->update([
            'sales_stage' => $validated['sales_stage'],
            'sales_stage_updated_at' => now(),
            'returned_at' => null,
            'returned_by' => null,
            'return_notes' => null,
            'archived_at' => null,
            'archived_by' => null,
            'verification_assigned_to' => null,
        ]);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.assigned'))
            ->with('success', 'Selected leads moved successfully.');
    }

    public function returnLeads(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'return_leads'), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_notes' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $returnableLeads = $this->salesActionLeadIds($request, $validated['lead_ids']);

        Lead::whereIn('id', $returnableLeads)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->update([
            'returned_at' => now(),
            'returned_by' => $request->user()->id,
            'return_notes' => $validated['return_notes'] ?? null,
            'sales_stage' => null,
            'sales_stage_updated_at' => null,
            'archived_at' => null,
            'archived_by' => null,
            'lead_generation_stage' => null,
            'verification_assigned_to' => null,
        ]);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.returned'))
            ->with('success', 'Selected leads returned successfully.');
    }

    public function sidebarCounts(Request $request): JsonResponse
    {
        return response()->json($this->leadSidebarCounts($request));
    }

    public function archiveLeads(Request $request): RedirectResponse
    {
        abort_unless($this->userCanArchiveLeads($request), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $archivableLeads = Lead::whereIn('id', $validated['lead_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when(! $this->userIsAdmin($request), fn ($query) => $this->limitToOwnedOrUnclaimed($query, $request))
            ->pluck('id');

        abort_unless($archivableLeads->count() === count(array_unique($validated['lead_ids'])), 403);

        Lead::whereIn('id', $archivableLeads)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'verification_assigned_to' => null,
        ]);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.archived'))
            ->with('success', 'Selected leads archived successfully.');
    }

    public function restoreLeads(Request $request): RedirectResponse
    {
        abort_unless($this->userCanArchiveLeads($request), 403);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $restorableLeads = Lead::whereIn('id', $validated['lead_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when(! $this->userIsAdmin($request), fn ($query) => $this->limitToOwnedOrUnclaimed($query, $request))
            ->pluck('id');

        abort_unless($restorableLeads->count() === count(array_unique($validated['lead_ids'])), 403);

        Lead::whereIn('id', $restorableLeads)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->update([
            'archived_at' => null,
            'archived_by' => null,
            'sales_stage' => null,
            'sales_stage_updated_at' => null,
            'returned_at' => null,
            'returned_by' => null,
            'return_notes' => null,
            'verification_assigned_to' => null,
        ]);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.index'))
            ->with('success', 'Selected leads moved back to Leads successfully.');
    }

    public function updatePhoneStatuses(Request $request, Lead $lead): RedirectResponse
    {
        $this->ensureCanEditLead($request, $lead);

        $validated = $request->validate([
            'phone_number_statuses' => ['nullable', 'array'],
            'phone_number_statuses.*' => ['nullable', Rule::in($this->allowedPhoneNumberStatuses())],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $phoneStatuses = $this->validPhoneNumberStatuses(
            $lead,
            $validated['phone_number_statuses'] ?? []
        );
        $verifiedPhoneNumbers = collect($phoneStatuses)
            ->filter(fn (?string $status) => $status === 'Verified')
            ->keys()
            ->values()
            ->all();
        $hasDnc = in_array('DNC', $phoneStatuses, true);
        $checks = [
            'author_confirmed' => $lead->author_confirmed,
            'book_confirmed' => $lead->book_confirmed,
            'phone_confirmed' => $this->allPhoneNumbersVerified($lead, $verifiedPhoneNumbers),
            'email_confirmed' => $lead->email_confirmed,
        ];
        $score = $this->manualVerificationScore($checks);
        $isVerified = $lead->verified_at && $this->leadIsFullyVerified($checks);

        $lead->update([
            'phone_number_statuses' => $phoneStatuses,
            'verified_phone_numbers' => $verifiedPhoneNumbers,
            'phone_confirmed' => $checks['phone_confirmed'],
            'verify_score' => $score > 0 ? $score : null,
            'verified_at' => $isVerified ? $lead->verified_at : null,
            'verified_by' => $isVerified ? $lead->verified_by : null,
            'archived_at' => $hasDnc ? now() : $lead->archived_at,
            'archived_by' => $hasDnc ? $request->user()->id : $lead->archived_by,
            'verification_assigned_to' => $hasDnc ? null : $lead->verification_assigned_to,
        ]);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.index'))
            ->with('success', $hasDnc ? 'Phone status saved. DNC lead moved to Archived Leads.' : 'Phone status saved successfully.');
    }

    public function verify(Lead $lead): View
    {
        abort_unless($this->userHasPermission(request(), 'verify_leads'), 403);
        $this->ensureCanAccessVerificationLead(request(), $lead);

        return view('leads.verify', [
            'lead' => $lead,
            'verificationLinks' => $this->verificationLinks($lead),
            'returnTo' => $this->safeReturnUrl(request('return_to')) ?? route('leads.index'),
        ]);
    }

    public function storeVerification(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'verify_leads'), 403);
        $this->ensureCanAccessVerificationLead($request, $lead);

        $validated = $request->validate([
            'author_confirmed' => ['nullable', 'boolean'],
            'book_confirmed' => ['nullable', 'boolean'],
            'phone_number_statuses' => ['nullable', 'array'],
            'phone_number_statuses.*' => ['nullable', Rule::in($this->allowedPhoneNumberStatuses())],
            'email_confirmed' => ['nullable', 'boolean'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $phoneStatuses = $this->validPhoneNumberStatuses($lead, $validated['phone_number_statuses'] ?? []);
        $verifiedPhoneNumbers = collect($phoneStatuses)
            ->filter(fn (?string $status) => $status === 'Verified')
            ->keys()
            ->values()
            ->all();
        $hasDnc = in_array('DNC', $phoneStatuses, true);

        $checks = [
            'author_confirmed' => $request->boolean('author_confirmed'),
            'book_confirmed' => $request->boolean('book_confirmed'),
            'phone_confirmed' => $this->allPhoneNumbersVerified($lead, $verifiedPhoneNumbers),
            'email_confirmed' => $request->boolean('email_confirmed'),
        ];
        $score = $this->manualVerificationScore($checks);
        $isVerified = $this->leadIsFullyVerified($checks);

        $lead->update([
            ...$checks,
            'verified_phone_numbers' => $verifiedPhoneNumbers,
            'phone_number_statuses' => $phoneStatuses,
            'verify_score' => $score > 0 ? $score : null,
            'verification_notes' => $validated['verification_notes'] ?? null,
            'verified_at' => $isVerified ? now() : null,
            'verified_by' => $isVerified ? $request->user()->id : null,
            'archived_at' => $hasDnc ? now() : $lead->archived_at,
            'archived_by' => $hasDnc ? $request->user()->id : $lead->archived_by,
            'verification_assigned_to' => $hasDnc ? null : $lead->verification_assigned_to,
        ]);

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('leads.index'))
            ->with('success', $hasDnc ? 'Lead verification saved. DNC lead moved to Archived Leads.' : 'Lead verification saved successfully.');
    }

    private function validatedLeadData(Request $request, bool $requireAssignment = true): array
    {
        $validated = $request->validate([
            'publisher' => ['nullable', 'string', 'max:255'],
            'book_title' => ['required', 'string', 'max:255'],
            'author_name' => ['required', 'string', 'max:255'],
            'phone_numbers' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'book_link' => ['nullable', 'url', 'max:5000'],
            'published_date' => ['nullable', 'date'],
        ]);

        $validated['phone_numbers'] = $this->parsePhoneNumbers($validated['phone_numbers']);

        $validated['email'] = $validated['email'] ? $this->normalizeEmailForDuplicateCheck($validated['email']) : null;

        if (count($validated['phone_numbers']) === 0) {
            back()
                ->withErrors(['phone_numbers' => 'Please provide at least one phone number.'])
                ->withInput()
                ->throwResponse();
        }

        if ($this->hasDuplicatePhonesInLead($validated['phone_numbers'])) {
            back()
                ->withErrors(['phone_numbers' => 'Please remove duplicate phone numbers from this lead.'])
                ->withInput()
                ->throwResponse();
        }

        return $validated;
    }

    private function csvHeaderMap(array $headers): array
    {
        $aliases = [
            'publisher' => 'publisher',
            'book title' => 'book_title',
            'book_title' => 'book_title',
            'author name' => 'author_name',
            "author's name" => 'author_name',
            'author_name' => 'author_name',
            'phone number' => 'phone_numbers',
            'phone numbers' => 'phone_numbers',
            'phone_numbers' => 'phone_numbers',
            'email' => 'email',
            'book link' => 'book_link',
            'book_link' => 'book_link',
            'published date' => 'published_date',
            'published_date' => 'published_date',
        ];

        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = mb_strtolower(trim((string) $header));

            if (isset($aliases[$normalized])) {
                $map[$aliases[$normalized]] = $index;
            }
        }

        return $map;
    }

    private function leadDataFromCsvRow(array $row, array $headerMap): array
    {
        $value = fn (string $key): ?string => isset($headerMap[$key])
            ? trim((string) ($row[$headerMap[$key]] ?? ''))
            : null;

        return [
            'publisher' => $value('publisher') ?: null,
            'book_title' => $value('book_title') ?: null,
            'author_name' => $value('author_name') ?: null,
            'phone_numbers' => $this->parsePhoneNumbers($value('phone_numbers') ?? ''),
            'email' => $value('email') ? $this->normalizeEmailForDuplicateCheck($value('email')) : null,
            'book_link' => $value('book_link') ?: null,
            'published_date' => $value('published_date') ?: null,
        ];
    }

    private function parsePhoneNumbers(string $phoneNumbers): array
    {
        return collect(preg_split('/\r\n|\r|\n|\||;|,(?=\s*\(?\+?\d)/', $phoneNumbers))
            ->map(fn (string $phoneNumber) => trim($phoneNumber))
            ->filter()
            ->values()
            ->all();
    }

    private function hasDuplicatePhonesInLead(array $phoneNumbers): bool
    {
        $normalizedPhones = collect($phoneNumbers)
            ->map(fn (string $phoneNumber) => $this->normalizePhoneForDuplicateCheck($phoneNumber))
            ->filter();

        return $normalizedPhones->count() !== $normalizedPhones->unique()->count();
    }

    private function ensureLeadIsNotDuplicate(array $leadData, ?Lead $currentLead = null): void
    {
        $duplicateMessage = $this->leadDuplicateMessage($leadData, $currentLead);

        if (! $duplicateMessage) {
            return;
        }

        $errors = str_contains($duplicateMessage, 'Phone Number')
            ? ['phone_numbers' => $duplicateMessage]
            : (str_contains($duplicateMessage, 'email')
                ? ['email' => $duplicateMessage]
                : ['book_title' => $duplicateMessage]);

        $response = back()->withErrors($errors)->withInput();

        if (str_contains($duplicateMessage, 'Phone Number')) {
            $response->with('duplicate_phone_numbers', [$this->firstDuplicateLeadPhoneNumber($leadData, $currentLead)]);
        }

        $response->throwResponse();
    }

    private function leadDuplicateMessage(array $leadData, ?Lead $currentLead = null): ?string
    {
        $submittedPhoneNumbers = collect($leadData['phone_numbers'] ?? [])
            ->map(fn (string $phoneNumber) => $this->normalizePhoneForDuplicateCheck($phoneNumber))
            ->filter()
            ->unique()
            ->values();
        $submittedEmail = $this->normalizeEmailForDuplicateCheck($leadData['email'] ?? '');
        $submittedAuthor = $this->normalizeTextForDuplicateCheck($leadData['author_name'] ?? '');
        $submittedBook = $this->normalizeTextForDuplicateCheck($leadData['book_title'] ?? '');

        $existingLeads = Lead::query()
            ->when($currentLead, fn ($query) => $query->whereKeyNot($currentLead->id))
            ->get(['id', 'book_title', 'author_name', 'phone_numbers', 'email']);

        foreach ($existingLeads as $existingLead) {
            $existingPhones = collect($existingLead->phone_numbers ?? [])
                ->map(fn (string $phoneNumber) => $this->normalizePhoneForDuplicateCheck($phoneNumber))
                ->filter()
                ->unique()
                ->values();
            $existingEmail = $this->normalizeEmailForDuplicateCheck($existingLead->email ?? '');

            $duplicatePhoneNumber = $submittedPhoneNumbers->intersect($existingPhones)->first();

            if ($duplicatePhoneNumber) {
                return 'Phone Number Already Exist';
            }

            if ($submittedEmail !== '' && $submittedEmail === $existingEmail) {
                return 'This email already exists.';
            }

            if (
                $submittedAuthor !== ''
                && $submittedBook !== ''
                && $submittedAuthor === $this->normalizeTextForDuplicateCheck($existingLead->author_name)
                && $submittedBook === $this->normalizeTextForDuplicateCheck($existingLead->book_title)
            ) {
                return 'This book title already exists for this author.';
            }
        }

        return null;
    }

    private function firstDuplicateLeadPhoneNumber(array $leadData, ?Lead $currentLead = null): ?string
    {
        $submittedPhoneNumbers = collect($leadData['phone_numbers'] ?? [])
            ->mapWithKeys(fn (string $phoneNumber) => [$this->normalizePhoneForDuplicateCheck($phoneNumber) => $phoneNumber])
            ->filter(fn (string $phoneNumber, string $normalizedPhone) => $normalizedPhone !== '');

        $existingLeads = Lead::query()
            ->when($currentLead, fn ($query) => $query->whereKeyNot($currentLead->id))
            ->get(['phone_numbers']);

        foreach ($existingLeads as $existingLead) {
            $existingPhones = collect($existingLead->phone_numbers ?? [])
                ->map(fn (string $phoneNumber) => $this->normalizePhoneForDuplicateCheck($phoneNumber))
                ->filter();

            $duplicatePhoneNumber = $submittedPhoneNumbers->keys()->intersect($existingPhones)->first();

            if ($duplicatePhoneNumber) {
                return $submittedPhoneNumbers[$duplicatePhoneNumber] ?? $duplicatePhoneNumber;
            }
        }

        return null;
    }

    private function normalizeEmailForDuplicateCheck(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizePhoneForDuplicateCheck(string $phoneNumber): string
    {
        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
    }

    private function normalizeTextForDuplicateCheck(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }

    private function syncVerifiedPhoneNumbers(Lead $lead): void
    {
        $verifiedPhoneNumbers = $this->validVerifiedPhoneNumbers($lead, $lead->verified_phone_numbers ?? []);

        $checks = [
            'author_confirmed' => $lead->author_confirmed,
            'book_confirmed' => $lead->book_confirmed,
            'phone_confirmed' => $this->allPhoneNumbersVerified($lead, $verifiedPhoneNumbers),
            'email_confirmed' => $lead->email_confirmed,
        ];
        $score = $this->manualVerificationScore($checks);
        $isVerified = $lead->verified_at && $this->leadIsFullyVerified($checks);

        $lead->update([
            'verified_phone_numbers' => $verifiedPhoneNumbers,
            'phone_number_statuses' => $this->validPhoneNumberStatuses($lead, $lead->phone_number_statuses ?? []),
            'phone_confirmed' => $checks['phone_confirmed'],
            'verify_score' => $score > 0 ? $score : null,
            'verified_at' => $isVerified ? $lead->verified_at : null,
            'verified_by' => $isVerified ? $lead->verified_by : null,
        ]);
    }

    private function validVerifiedPhoneNumbers(Lead $lead, array $verifiedPhoneNumbers): array
    {
        return collect($verifiedPhoneNumbers)
            ->intersect($lead->phone_numbers ?? [])
            ->values()
            ->all();
    }

    private function validPhoneNumberStatuses(Lead $lead, array $phoneStatuses): array
    {
        return collect($lead->phone_numbers ?? [])
            ->mapWithKeys(function (string $phoneNumber) use ($phoneStatuses) {
                $status = $phoneStatuses[$phoneNumber] ?? null;

                return [
                    $phoneNumber => in_array($status, $this->allowedPhoneNumberStatuses(), true) ? $status : null,
                ];
            })
            ->filter()
            ->all();
    }

    private function allowedPhoneNumberStatuses(): array
    {
        return ['Verified', 'Voice Mail', 'No Answer', 'NIS', 'DNC', 'Wrong Number'];
    }

    private function allPhoneNumbersVerified(Lead $lead, array $verifiedPhoneNumbers): bool
    {
        $phoneNumbers = $lead->phone_numbers ?? [];

        return count($phoneNumbers) > 0 && count($verifiedPhoneNumbers) === count($phoneNumbers);
    }

    private function manualVerificationScore(array $checks): int
    {
        return ($checks['author_confirmed'] ? 25 : 0)
            + ($checks['book_confirmed'] ? 25 : 0)
            + ($checks['phone_confirmed'] ? 25 : 0)
            + ($checks['email_confirmed'] ? 25 : 0);
    }

    private function leadIsFullyVerified(array $checks): bool
    {
        return $checks['author_confirmed']
            && $checks['book_confirmed']
            && $checks['phone_confirmed']
            && $checks['email_confirmed'];
    }

    private function verificationLinks(Lead $lead): array
    {
        $author = trim($lead->author_name);
        $book = trim($lead->book_title);

        return [
            'Amazon' => 'https://www.amazon.com/s?' . http_build_query(['k' => "{$book} {$author}"]),
        ];
    }

    private function applyLeadFilters($query, ?Request $request): void
    {
        $search = trim((string) $request?->query('search', ''));
        $assignmentStatus = $request?->query('assignment_status');
        $verificationStatus = $request?->query('verification_status');

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('publisher', 'like', "%{$search}%")
                    ->orWhere('book_title', 'like', "%{$search}%")
                    ->orWhere('author_name', 'like', "%{$search}%")
                    ->orWhere('phone_numbers', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('assignedUser', function ($query) use ($search) {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"]);
                    });
            });
        }

        if ($assignmentStatus === 'assigned') {
            $query->whereNotNull('assigned_to');
        }

        if ($assignmentStatus === 'unassigned') {
            $query->whereNull('assigned_to');
        }

        if ($verificationStatus === 'verified') {
            $query->whereNotNull('verified_at');
        }

        if ($verificationStatus === 'unverified') {
            $query->whereNull('verified_at');
        }
    }

    private function safeReturnUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return str_starts_with($url, url('/')) ? $url : null;
    }

    private function requestCameFromRoute(?string $url, string $routeName): bool
    {
        $safeUrl = $this->safeReturnUrl($url);

        if (! $safeUrl) {
            return false;
        }

        return parse_url($safeUrl, PHP_URL_PATH) === parse_url(route($routeName), PHP_URL_PATH);
    }

    private function userIsAdmin(?Request $request): bool
    {
        return $request?->user()?->role?->name === 'Admin';
    }

    private function userHasPermission(?Request $request, string $permission): bool
    {
        return $this->userIsAdmin($request)
            || (bool) $request?->user()?->hasPermission($permission);
    }

    private function userIsLeadMiner(?Request $request): bool
    {
        return $request?->user()?->role?->name === 'Lead Miner';
    }

    private function canViewAllAssignedLeads(?Request $request): bool
    {
        return $this->userIsAdmin($request) || $this->userIsLeadMiner($request);
    }

    private function closeLeadAssignmentHistory(int $leadId, ?int $releasedBy, string $reason): void
    {
        LeadAssignmentHistory::where('lead_id', $leadId)
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
                'released_by' => $releasedBy,
                'release_reason' => $reason,
            ]);
    }

    private function userIsSales(?Request $request): bool
    {
        return ! $this->userIsLeadMiner($request)
            && (
                $request?->user()?->department === 'Sales'
                || in_array($request?->user()?->role?->name, [
                    'Branding Specialist',
                    'Sales Director',
                    'Team Leader',
                    'Operation Manager',
                    'Trainee',
                ], true)
            );
    }

    private function userCanCreateLeads(?Request $request): bool
    {
        return $this->userHasPermission($request, 'create_leads')
            || $this->userCanSelfMineAndWork($request);
    }

    private function userCanSelfMineAndWork(?Request $request): bool
    {
        return $this->userIsSales($request)
            && $this->userHasPermission($request, 'self_mine_work_leads');
    }

    private function userCanAssignLeads(?Request $request): bool
    {
        return $this->userHasPermission($request, 'assign_leads');
    }

    private function userCanReassignTeamLeads(?Request $request): bool
    {
        return $this->userHasPermission($request, 'reassign_team_leads');
    }

    private function userCanUnassignTeamLeads(?Request $request): bool
    {
        return $this->userHasPermission($request, 'unassign_team_leads');
    }

    private function userCanArchiveLeads(?Request $request): bool
    {
        return $this->userHasPermission($request, 'archive_leads');
    }

    private function notificationRecipients(array $permissions, ?string $department = null)
    {
        return User::with(['role.permissionRecords', 'permissionOverrides'])
            ->when($department, fn ($query) => $query->where('department', $department))
            ->get()
            ->filter(function (User $user) use ($permissions) {
                return collect($permissions)
                    ->every(fn (string $permission) => $user->hasPermission($permission));
            })
            ->unique('id')
            ->values();
    }

    private function availableVerificationUsers(Request $request)
    {
        return $this->notificationRecipients(['view_verification_queue', 'verify_leads'], 'Lead Generation')
            ->when(! BrandScope::canAccessAllBrands($request->user()), function ($users) use ($request) {
                return $users->filter(fn (User $user) => (int) $user->brand_id === (int) $request->user()->brand_id);
            })
            ->loadCount([
                'verificationQueueLeads as active_verification_count' => function ($query) {
                    $query->where('lead_generation_stage', 'verification_queue')
                        ->whereNull('verified_at')
                        ->whereNull('archived_at')
                        ->whereNull('sales_stage');
                },
            ])
            ->sortBy([
                ['active_verification_count', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    private function leastLoadedVerifier($verifiers): User
    {
        $lowestCount = $verifiers->min('active_verification_count');
        $verifier = $verifiers
            ->filter(fn (User $user) => $user->active_verification_count === $lowestCount)
            ->random();

        $verifier->active_verification_count++;

        return $verifier;
    }

    private function markSharedLeadPageSeen(string $viewMode, ?Request $request): void
    {
        if (! $request?->user() || ! in_array($viewMode, ['unassigned', 'returned', 'archived'], true)) {
            return;
        }

        DB::table('lead_page_views')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'page_key' => $viewMode,
            ],
            [
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function leadSidebarCounts(Request $request): array
    {
        return [
            'unassigned' => $this->userHasPermission($request, 'view_unassigned_leads')
                ? $this->unseenLeadCount($request, 'unassigned')
                : 0,
            'returned' => $this->userHasPermission($request, 'view_returned_leads')
                ? $this->unseenLeadCount($request, 'returned')
                : 0,
            'archived' => $this->userHasPermission($request, 'view_archived_leads')
                ? $this->unseenLeadCount($request, 'archived')
                : 0,
        ];
    }

    private function unseenLeadCount(Request $request, string $pageKey): int
    {
        $lastSeenAt = DB::table('lead_page_views')
            ->where('user_id', $request->user()->id)
            ->where('page_key', $pageKey)
            ->value('last_seen_at');

        $query = match ($pageKey) {
            'unassigned' => Lead::query()
                ->where('lead_generation_stage', 'ready_to_assign')
                ->whereNotNull('verified_at')
                ->whereNull('assigned_to')
                ->whereNull('sales_stage')
                ->whereNull('returned_at')
                ->whereNull('archived_at'),
            'returned' => Lead::query()
                ->whereNotNull('returned_at')
                ->where(function ($query) {
                    $query->whereNull('lead_generation_stage')
                        ->orWhere('lead_generation_stage', '!=', 'verification_queue');
                })
                ->whereNull('archived_at'),
            'archived' => Lead::query()
                ->whereNotNull('archived_at'),
            default => Lead::query()->whereRaw('1 = 0'),
        };

        BrandScope::apply($query, $request->user());

        if ($pageKey === 'archived' && ! $this->userIsAdmin($request) && ! $this->userHasPermission($request, 'view_all_leads')) {
            $this->limitToOwnedOrUnclaimed($query, $request);
        }

        if ($lastSeenAt) {
            $query->where('updated_at', '>', $lastSeenAt);
        }

        return $query->count();
    }

    private function userCanReceiveNotification(User $user, string $permission): bool
    {
        return $user->role?->name === 'Admin'
            || (bool) $user->hasPermission($permission);
    }

    private function userCanUseSalesWorkflow(?Request $request): bool
    {
        return $this->userHasPermission($request, 'move_sales_stage')
            || $this->userHasPermission($request, 'return_leads');
    }

    private function ensureCanCreateLeads(?Request $request): void
    {
        abort_unless($this->userCanCreateLeads($request), 403);
    }

    private function ensureCanEditLead(?Request $request, Lead $lead): void
    {
        abort_unless(
            $this->userIsAdmin($request)
            || (
                $this->userHasPermission($request, 'edit_leads')
                && $this->userCanManageLeadRecord($request, $lead)
                && $this->userCanAccessLeadBrand($request, $lead)
            ),
            403
        );
    }

    private function ensureCanAccessVerificationLead(?Request $request, Lead $lead): void
    {
        abort_unless(
            $this->userIsAdmin($request)
            || (
                $this->userCanAccessLeadBrand($request, $lead)
                &&
                $lead->lead_generation_stage === 'verification_queue'
                && (
                    is_null($lead->verification_assigned_to)
                    || $lead->verification_assigned_to === $request?->user()?->id
                )
            ),
            403
        );
    }

    private function userCanManageLeadRecord(?Request $request, Lead $lead): bool
    {
        $userId = $request?->user()?->id;

        return $this->userIsAdmin($request)
            || $this->userHasPermission($request, 'view_all_leads')
            || ($userId !== null && $lead->created_by === $userId)
            || ($lead->returned_at !== null && $this->userHasPermission($request, 'view_returned_leads'))
            || $lead->created_by === null;
    }

    private function userCanAccessLeadBrand(?Request $request, Lead $lead): bool
    {
        return BrandScope::canAccessAllBrands($request?->user())
            || (int) $request?->user()?->brand_id === (int) $lead->brand_id;
    }

    private function userCanAssignToBrand(?Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request?->user())
            || (int) $request?->user()?->brand_id === (int) $brandId;
    }

    private function limitToOwnedOrUnclaimed($query, ?Request $request)
    {
        if ($this->userHasPermission($request, 'view_all_leads')) {
            return $query;
        }

        return $query->where(function ($query) use ($request) {
            $userId = $request?->user()?->id;

            if ($userId !== null) {
                $query->where('created_by', $userId)
                    ->orWhereNull('created_by');

                return;
            }

            $query->whereNull('created_by');
        });
    }

    private function ensureCanViewLeadMode(string $viewMode, ?Request $request): void
    {
        if ($this->userIsAdmin($request)) {
            return;
        }

        $allowed = match ($viewMode) {
            'all' => $this->userHasPermission($request, 'view_all_leads'),
            'mine' => $this->userHasPermission($request, 'view_my_leads'),
            'returned' => $this->userHasPermission($request, 'view_returned_leads'),
            'archived' => $this->userHasPermission($request, 'view_archived_leads'),
            'assigned' => $this->userHasPermission($request, 'view_assigned_leads_monitor'),
            'new_assigned' => $this->userHasPermission($request, 'view_assigned_leads') || $this->userCanSelfMineAndWork($request),
            'verification_queue' => $this->userHasPermission($request, 'view_verification_queue'),
            'unassigned' => $this->userHasPermission($request, 'view_unassigned_leads'),
            'sales_team_leads' => $this->userHasPermission($request, 'view_team_leads'),
            default => str_starts_with($viewMode, 'sales_') && $this->userHasPermission($request, 'view_sales'),
        };

        abort_unless($allowed, 403);
    }

    private function salesActionLeadIds(Request $request, array $leadIds)
    {
        $actionLeadIds = Lead::whereIn('id', $leadIds)
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when(! $this->userIsAdmin($request), fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->pluck('id');

        abort_unless($actionLeadIds->count() === count(array_unique($leadIds)), 403);

        return $actionLeadIds;
    }

    private function summaryCards(string $viewMode, ?Request $request = null): array
    {
        if ($viewMode === 'sales_team_leads') {
            $teamLeadQuery = Lead::query()
                ->whereNotNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->whereHas('assignedUser', fn ($query) => $query->where('department', 'Sales'));
            BrandScope::apply($teamLeadQuery, $request?->user());

            return [
                [
                    'label' => 'Team Leads',
                    'count' => (clone $teamLeadQuery)->count(),
                    'hint' => 'Assigned to Sales agents',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Not Started',
                    'count' => (clone $teamLeadQuery)->whereNull('sales_stage')->count(),
                    'hint' => 'No pipeline status yet',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'In Progress',
                    'count' => (clone $teamLeadQuery)->whereIn('sales_stage', ['pipeline', 'prospect', 'scheduled_callback'])->count(),
                    'hint' => 'Being worked by agents',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Sold',
                    'count' => (clone $teamLeadQuery)->where('sales_stage', 'sold')->count(),
                    'hint' => 'Closed by team',
                    'tone' => 'emerald',
                ],
            ];
        }

        if (str_starts_with($viewMode, 'sales_')) {
            $salesQuery = Lead::query()
                ->whereNotNull('sales_stage')
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->where('assigned_to', $request?->user()->id);
            BrandScope::apply($salesQuery, $request?->user());
            $refundCount = SalesPayment::query()
                ->tap(fn ($query) => BrandScope::apply($query, $request?->user()))
                ->where('status', 'Refund')
                ->whereHas('endorsement', fn ($query) => $query->where('agent_id', $request?->user()->id))
                ->count();
            $overallSalesAmount = SalesActivity::query()
                ->tap(fn ($query) => BrandScope::apply($query, $request?->user()))
                ->where('payment_status', 'Payment Success')
                ->where(function ($query) use ($request) {
                    $query->where('agent_id', $request?->user()->id)
                        ->orWhere('frankie_agent_id', $request?->user()->id);
                })
                ->get()
                ->sum(fn (SalesActivity $activity) => (int) $activity->agent_id === (int) $request?->user()->id
                    ? (float) ($activity->agent_credit_amount ?: $activity->amount)
                    : (float) $activity->frankie_credit_amount);

            return [
                [
                    'label' => 'Sales Leads',
                    'count' => (clone $salesQuery)->count(),
                    'hint' => 'In sales workflow',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Pipeline',
                    'count' => (clone $salesQuery)->where('sales_stage', 'pipeline')->count(),
                    'hint' => 'Active opportunities',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Prospect',
                    'count' => (clone $salesQuery)->where('sales_stage', 'prospect')->count(),
                    'hint' => 'Under sales review',
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Scheduled Callback',
                    'count' => (clone $salesQuery)->where('sales_stage', 'scheduled_callback')->count(),
                    'hint' => 'Needs callback',
                    'tone' => 'rose',
                ],
                [
                    'label' => $viewMode === 'sales_sold' ? 'Overall Sales Amount' : 'Sold',
                    'count' => $viewMode === 'sales_sold'
                        ? $overallSalesAmount
                        : (clone $salesQuery)->where('sales_stage', 'sold')->count(),
                    'hint' => $viewMode === 'sales_sold' ? 'Total amount you sold' : 'Closed sales',
                    'tone' => 'emerald',
                    'format' => $viewMode === 'sales_sold' ? 'currency' : null,
                ],
                [
                    'label' => 'Refunds',
                    'count' => $refundCount,
                    'hint' => 'Refund tracking',
                    'tone' => 'slate',
                ],
            ];
        }

        if (in_array($viewMode, ['assigned', 'new_assigned'], true)) {
            $assignedQuery = Lead::query()
                ->whereNotNull('assigned_to')
                ->whereNull('returned_at')
                ->whereNull('archived_at');
            BrandScope::apply($assignedQuery, $request?->user());

            if ($viewMode === 'new_assigned') {
                $assignedQuery->whereNull('sales_stage');
            }

            $returnedQuery = Lead::query()
                ->whereNotNull('assigned_to')
                ->whereNotNull('returned_at')
                ->whereNull('archived_at');
            BrandScope::apply($returnedQuery, $request?->user());

            if (! $this->canViewAllAssignedLeads($request)) {
                $assignedQuery->where('assigned_to', $request?->user()->id);
                $returnedQuery->where('assigned_to', $request?->user()->id);
            }

            if ($viewMode === 'new_assigned') {
                return [
                    [
                        'label' => 'New Leads',
                        'count' => (clone $assignedQuery)->count(),
                        'hint' => 'Awaiting first sales action',
                        'tone' => 'amber',
                    ],
                ];
            }

            return [
                [
                    'label' => 'Total Assigned Leads',
                    'count' => (clone $assignedQuery)->count(),
                    'hint' => 'Active assigned portfolio',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Not Started',
                    'count' => (clone $assignedQuery)->whereNull('sales_stage')->count(),
                    'hint' => 'Still in New Leads',
                    'tone' => 'slate',
                ],
                [
                    'label' => 'In Progress',
                    'count' => (clone $assignedQuery)->whereIn('sales_stage', ['pipeline', 'prospect', 'scheduled_callback'])->count(),
                    'hint' => 'Being worked by Sales',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Sold',
                    'count' => (clone $assignedQuery)->where('sales_stage', 'sold')->count(),
                    'hint' => 'Completed sales',
                    'tone' => 'emerald',
                ],
            ];
        }

        $leadQuery = Lead::query()
            ->whereNull('sales_stage')
            ->whereNull('archived_at');
        BrandScope::apply($leadQuery, $request?->user());

        if ($this->userIsLeadMiner($request)) {
            $this->limitToOwnedOrUnclaimed($leadQuery, $request);
        }

        $miningPoolQuery = (clone $leadQuery)
            ->whereNull('assigned_to')
            ->whereNull('returned_at')
            ->whereNull('lead_generation_stage');
        $pendingVerificationQuery = (clone $leadQuery)
            ->where('lead_generation_stage', 'verification_queue')
            ->when(
                ! $this->userIsAdmin($request) && $this->userHasPermission($request, 'view_verification_queue'),
                fn ($query) => $query->where('verification_assigned_to', $request?->user()->id)
            );

        return [
            [
                'label' => 'Mine Leads',
                'count' => (clone $miningPoolQuery)->count(),
                'hint' => 'New mining pool',
                'tone' => 'amber',
            ],
            [
                'label' => 'Pending Verification',
                'count' => $pendingVerificationQuery->count(),
                'hint' => 'In verifier queue',
                'tone' => 'rose',
            ],
            [
                'label' => 'Ready to Assign',
                'count' => (clone $leadQuery)->where('lead_generation_stage', 'ready_to_assign')->whereNotNull('verified_at')->count(),
                'hint' => 'Verified and ready',
                'tone' => 'emerald',
            ],
        ];
    }

    private function leadPageCopy(string $viewMode): array
    {
        return match ($viewMode) {
            'mine' => [
                'My Leads',
                'View leads you added or imported.',
            ],
            'verification_queue' => [
                'Verification Queue',
                'Review leads sent by Lead Miners for verification or re-verification.',
            ],
            'unassigned' => [
                'Unassigned Leads',
                'View verified leads that are ready to assign to Sales.',
            ],
            'assigned' => [
                'Assigned Leads',
                'View the complete active portfolio assigned to Sales, including leads already in a sales stage.',
            ],
            'new_assigned' => [
                'New Leads',
                'Review newly assigned leads that have not entered the sales workflow yet.',
            ],
            'sales_team_leads' => [
                'Team Leads',
                'Monitor Sales department leads assigned to agents and inspect their current status.',
            ],
            'returned' => [
                'Returned Leads',
                'Repair returned leads, send them for re-verification, then return them to the assigned agent.',
            ],
            'archived' => [
                'Archived Leads',
                'View leads that are no longer active.',
            ],
            'sales_pipeline' => [
                'Pipeline',
                'Track active sales opportunities.',
            ],
            'sales_prospect' => [
                'Prospect',
                'Review prospects being prepared for sales follow-up.',
            ],
            'sales_scheduled_callback' => [
                'Scheduled Callback',
                'View sales leads with callback schedules.',
            ],
            'sales_sold' => [
                'Sold',
                'View completed sales records.',
            ],
            'sales_refunds' => [
                'Refunds',
                'Track refund requests and refunded sales.',
            ],
            default => [
                'Mine Leads',
                'Start here with newly mined leads before sending them to Verification Queue.',
            ],
        };
    }
}
