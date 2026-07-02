<x-app-layout>
    <x-slot name="header">
        User Details
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ $user->first_name }} {{ $user->last_name }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Review user profile, access role, and account status.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.users.index') }}"
                   class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Back
                </a>

                <a href="{{ route('admin.users.edit', $user) }}"
                   class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600">
                    Edit User
                </a>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <dl class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-slate-500">Full Name</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $user->first_name }} {{ $user->last_name }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Email</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $user->email }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Department</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $user->department ?? 'Not assigned' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Phone Number</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $user->phone_number ?? 'Not provided' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Role</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $user->role->name ?? 'No Role' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Created</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $user->created_at->format('M d, Y') }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Email Status</dt>
                    <dd class="mt-1">
                        @if ($user->email_verified_at)
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Verified</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Pending</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Password Status</dt>
                    <dd class="mt-1">
                        @if ($user->password_created_at)
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Created</span>
                        @else
                            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Not Created</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
