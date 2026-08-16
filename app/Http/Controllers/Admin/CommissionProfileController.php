<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CommissionProfile;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommissionProfileController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? (string) $request->query('month')
            : now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $brandId = $request->integer('brand_id') ?: null;

        $brands = Brand::orderBy('imprint_name')->get();
        $profiles = CommissionProfile::with(['rules' => fn ($query) => $query->orderBy('minimum_mtd_percent')])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $employees = User::query()
            ->with(['brand', 'role', 'commissionProfile.rules'])
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $targets = SalesTarget::query()
            ->whereDate('target_month', $monthStart->toDateString())
            ->where('target_type', 'agent')
            ->whereNotNull('user_id')
            ->get()
            ->keyBy('user_id');

        return view('admin.commission-profiles.index', [
            'brands' => $brands,
            'profiles' => $profiles,
            'employees' => $employees,
            'targets' => $targets,
            'month' => $month,
            'brandId' => $brandId,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:commission_profiles,name'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $rules = $this->validatedRules($request);

        DB::transaction(function () use ($validated, $rules) {
            $profile = CommissionProfile::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            $profile->rules()->createMany($rules);

            if ($profile->is_default) {
                $this->makeDefaultProfile($profile);
            }
        });

        return back()->with('success', 'Commission profile created successfully.');
    }

    public function update(Request $request, CommissionProfile $commissionProfile)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('commission_profiles', 'name')->ignore($commissionProfile->id)],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $rules = $this->validatedRules($request);

        DB::transaction(function () use ($commissionProfile, $validated, $rules) {
            $commissionProfile->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            $commissionProfile->rules()->delete();
            $commissionProfile->rules()->createMany($rules);

            if ($commissionProfile->is_default) {
                $this->makeDefaultProfile($commissionProfile);
            }
        });

        return back()->with('success', 'Commission profile updated successfully.');
    }

    public function updateAssignments(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'users' => ['nullable', 'array'],
            'users.*.commission_profile_id' => ['nullable', 'exists:commission_profiles,id'],
            'users.*.target' => ['nullable', 'numeric', 'min:0'],
            'users.*.markup_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'users.*.commission_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'users.*.is_commission_threshold_exempt' => ['nullable', 'boolean'],
        ]);

        $monthStart = Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth();

        DB::transaction(function () use ($request, $validated, $monthStart) {
            foreach ($request->input('users', []) as $userId => $config) {
                $user = User::find($userId);

                if (! $user) {
                    continue;
                }

                $isExempt = (bool) ($config['is_commission_threshold_exempt'] ?? false);

                $user->update([
                    'commission_profile_id' => ($config['commission_profile_id'] ?? null) ?: null,
                    'markup_commission_percent' => (float) ($config['markup_commission_percent'] ?? 50),
                    'commission_threshold_amount' => $isExempt ? 0 : (float) ($config['commission_threshold_amount'] ?? 500),
                    'is_commission_threshold_exempt' => $isExempt,
                ]);

                SalesTarget::updateOrCreate(
                    [
                        'target_month' => $monthStart->toDateString(),
                        'target_type' => 'agent',
                        'user_id' => $user->id,
                        'brand_id' => $user->brand_id,
                    ],
                    [
                        'amount' => (float) ($config['target'] ?? 0),
                        'work_setup' => $user->work_type,
                    ]
                );
            }
        });

        return redirect()
            ->route('admin.commission-profiles.index', [
                'month' => $validated['month'],
                'brand_id' => $validated['brand_id'] ?? null,
            ])
            ->with('success', 'Employee commission profiles updated successfully.');
    }

    public function showEmployee(Request $request, User $user)
    {
        $this->ensureAdmin($request);

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? (string) $request->query('month')
            : now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();

        $profiles = CommissionProfile::with(['rules' => fn ($query) => $query->orderBy('minimum_mtd_percent')])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $user->load(['brand', 'role', 'commissionProfile.rules']);

        $target = SalesTarget::query()
            ->whereDate('target_month', $monthStart->toDateString())
            ->where('target_type', 'agent')
            ->where('user_id', $user->id)
            ->where('brand_id', $user->brand_id)
            ->first();

        return view('admin.commission-profiles.employee', [
            'user' => $user,
            'profiles' => $profiles,
            'target' => $target,
            'month' => $month,
        ]);
    }

    public function updateEmployee(Request $request, User $user)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'commission_profile_id' => ['nullable', Rule::exists('commission_profiles', 'id')],
            'target' => ['nullable', 'numeric', 'min:0'],
            'markup_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'is_commission_threshold_exempt' => ['nullable', 'boolean'],
        ]);

        $monthStart = Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth();
        $isExempt = (bool) ($validated['is_commission_threshold_exempt'] ?? false);

        DB::transaction(function () use ($user, $validated, $monthStart, $isExempt) {
            $user->update([
                'commission_profile_id' => ($validated['commission_profile_id'] ?? null) ?: null,
                'markup_commission_percent' => (float) ($validated['markup_commission_percent'] ?? 50),
                'commission_threshold_amount' => $isExempt ? 0 : (float) ($validated['commission_threshold_amount'] ?? 500),
                'is_commission_threshold_exempt' => $isExempt,
            ]);

            SalesTarget::updateOrCreate(
                [
                    'target_month' => $monthStart->toDateString(),
                    'target_type' => 'agent',
                    'user_id' => $user->id,
                    'brand_id' => $user->brand_id,
                ],
                [
                    'amount' => (float) ($validated['target'] ?? 0),
                    'work_setup' => $user->work_type,
                ]
            );
        });

        return redirect()
            ->route('admin.users.commission-profile.show', ['user' => $user, 'month' => $validated['month']])
            ->with('success', 'Employee commission profile updated successfully.');
    }

    private function validatedRules(Request $request): array
    {
        $rules = collect($request->input('rules', []))
            ->filter(fn ($rule) => ($rule['minimum_mtd_percent'] ?? '') !== '' || ($rule['commission_percent'] ?? '') !== '')
            ->map(function ($rule) {
                return [
                    'minimum_mtd_percent' => (float) ($rule['minimum_mtd_percent'] ?? 0),
                    'commission_percent' => (float) ($rule['commission_percent'] ?? 0),
                ];
            })
            ->sortBy('minimum_mtd_percent')
            ->values();

        if ($rules->isEmpty()) {
            throw ValidationException::withMessages([
                'rules' => 'Add at least one service commission rule.',
            ]);
        }

        foreach ($rules as $rule) {
            if ($rule['minimum_mtd_percent'] < 0 || $rule['minimum_mtd_percent'] > 1000) {
                throw ValidationException::withMessages([
                    'rules' => 'Service MTD percent must be between 0 and 1000.',
                ]);
            }

            if ($rule['commission_percent'] < 0 || $rule['commission_percent'] > 100) {
                throw ValidationException::withMessages([
                    'rules' => 'Commission percent must be between 0 and 100.',
                ]);
            }
        }

        return $rules->all();
    }

    private function makeDefaultProfile(CommissionProfile $profile): void
    {
        CommissionProfile::whereKeyNot($profile->id)->update(['is_default' => false]);
        $profile->forceFill(['is_default' => true])->save();
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);
    }
}
