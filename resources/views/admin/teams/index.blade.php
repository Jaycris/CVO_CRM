<x-app-layout>
    <x-slot name="header">
        Teams
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Teams</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Create brand teams with managers, team leaders, and members.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                Please check the team form and try again.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 {{ $canManageTeams ? 'xl:grid-cols-[24rem_1fr]' : '' }}">
            @if ($canManageTeams)
            <form method="POST"
                  action="{{ route('admin.teams.store') }}"
                  class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
                  x-data="teamForm({
                      users: @js($users->map(fn ($user) => [
                          'id' => (string) $user->id,
                          'name' => trim($user->first_name.' '.$user->last_name),
                          'role' => $user->role?->name,
                          'department' => $user->department,
                          'brand_id' => (string) $user->brand_id,
                          'brand' => $user->brand?->imprint_name,
                      ])->values()),
                      parentBrandId: @js((string) $parentBrandId),
                      brandId: @js((string) old('brand_id', $parentBrandId)),
                      department: @js(old('department', '')),
                      managerId: @js((string) old('manager_id', '')),
                      teamLeaderId: @js((string) old('team_leader_id', '')),
                      selectedMembers: @js(collect(old('member_ids', []))->map(fn ($id) => (string) $id)->values()),
                  })">
                @csrf
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Team</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Teams are separated per brand and department.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Brand / Account <span class="text-rose-600">*</span></label>
                    <select name="brand_id"
                            x-model="brandId"
                            x-on:change="cleanSelections()"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->imprint_name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Department <span class="text-rose-600">*</span></label>
                    <select name="department"
                            x-model="department"
                            x-on:change="cleanSelections()"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <option value="">Select department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}" @selected(old('department') === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('department')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Team Name <span class="text-rose-600">*</span></label>
                    <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Manager</label>
                    <select name="manager_id"
                            x-model="managerId"
                            :disabled="!canChooseUsers()"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                        <option value="">No manager</option>
                        <template x-for="user in filteredUsers()" :key="`manager-${user.id}`">
                            <option :value="user.id" x-text="userLabel(user)"></option>
                        </template>
                    </select>
                    <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400" x-show="!canChooseUsers()" x-cloak>Select a brand and department first.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Team Leader</label>
                    <select name="team_leader_id"
                            x-model="teamLeaderId"
                            :disabled="!canChooseUsers()"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                        <option value="">No team leader</option>
                        <template x-for="user in filteredUsers()" :key="`leader-${user.id}`">
                            <option :value="user.id" x-text="userLabel(user)"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Members</label>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-zinc-800 dark:bg-zinc-950">
                        <template x-for="memberId in selectedMembers" :key="`create-member-input-${memberId}`">
                            <input type="hidden" name="member_ids[]" :value="memberId">
                        </template>

                        <input type="text"
                               x-model="memberSearch"
                               :disabled="!canChooseUsers()"
                               placeholder="Search and select members..."
                               class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:disabled:bg-zinc-800">

                        <div class="mt-2 max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" x-show="canChooseUsers() && availableMembers().length" x-cloak>
                            <template x-for="user in availableMembers()" :key="`create-member-option-${user.id}`">
                                <button type="button"
                                        x-on:click="addMember(user.id)"
                                        class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-amber-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                    <span class="font-semibold" x-text="user.name"></span>
                                    <span class="text-slate-500" x-text="` - ${user.role || 'No role'} / ${user.brand || 'No brand'}`"></span>
                                </button>
                            </template>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2" x-show="selectedMembers.length" x-cloak>
                            <template x-for="memberId in selectedMembers" :key="`create-selected-member-${memberId}`">
                                <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                                    <span x-text="memberName(memberId)"></span>
                                    <button type="button" x-on:click="removeMember(memberId)" class="text-amber-900 hover:text-rose-600">&times;</button>
                                </span>
                            </template>
                        </div>

                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400" x-text="canChooseUsers() ? 'Search by name, then click a user to add them as a member.' : 'Select a brand and department first.'"></p>
                    </div>
                    <x-input-error :messages="$errors->get('member_ids')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    Create Team
                </button>
            </form>
            @endif

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Team Directory</h2>
                    @unless ($canManageTeams)
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">You can view all teams, but editing requires Manage Teams permission.</p>
                    @endunless
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="px-6 py-4">Team</th>
                                <th class="px-6 py-4">Brand</th>
                                <th class="px-6 py-4">Department</th>
                                <th class="px-6 py-4">Manager</th>
                                <th class="px-6 py-4">Team Leader</th>
                                <th class="px-6 py-4">Members</th>
                                @if ($canManageTeams)
                                    <th class="px-6 py-4">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($teams as $team)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $team->name }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $team->brand?->imprint_name }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $team->department }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $team->manager?->first_name }} {{ $team->manager?->last_name }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $team->teamLeader?->first_name }} {{ $team->teamLeader?->last_name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $team->members_count }}</span>
                                            @if ($team->members->isNotEmpty())
                                                <p class="max-w-48 truncate text-xs text-slate-500 dark:text-zinc-400">
                                                    {{ $team->members->map(fn ($member) => trim($member->first_name.' '.$member->last_name))->join(', ') }}
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                    @if ($canManageTeams)
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2" x-data="{ editOpen: false }">
                                            <button type="button"
                                                    x-on:click="editOpen = true"
                                                    class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                                Edit
                                            </button>

                                            <form method="POST" action="{{ route('admin.teams.destroy', $team) }}" onsubmit="return confirm('Delete this team?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">Delete</button>
                                            </form>

                                            <div x-show="editOpen"
                                                 x-cloak
                                                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                                                 x-on:keydown.escape.window="editOpen = false">
                                                <form method="POST"
                                                      action="{{ route('admin.teams.update', $team) }}"
                                                      x-on:click.outside="editOpen = false"
                                                      class="w-full max-w-2xl space-y-4 rounded-2xl bg-white p-6 text-left shadow-2xl dark:bg-zinc-900"
                                                      x-data="teamForm({
                                                          users: @js($users->map(fn ($user) => [
                                                              'id' => (string) $user->id,
                                                              'name' => trim($user->first_name.' '.$user->last_name),
                                                              'role' => $user->role?->name,
                                                              'department' => $user->department,
                                                              'brand_id' => (string) $user->brand_id,
                                                              'brand' => $user->brand?->imprint_name,
                                                          ])->values()),
                                                          parentBrandId: @js((string) $parentBrandId),
                                                          brandId: @js((string) $team->brand_id),
                                                          department: @js($team->department),
                                                          managerId: @js((string) $team->manager_id),
                                                          teamLeaderId: @js((string) $team->team_leader_id),
                                                          selectedMembers: @js($team->members->pluck('id')->map(fn ($id) => (string) $id)->values()),
                                                      })">
                                                    @csrf
                                                    @method('PUT')

                                                    <div class="flex items-start justify-between">
                                                        <div>
                                                            <h3 class="text-xl font-bold text-slate-900 dark:text-zinc-100">Edit Team</h3>
                                                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Update team assignment details.</p>
                                                        </div>
                                                        <button type="button" x-on:click="editOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">&times;</button>
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Brand / Account</label>
                                                            <select name="brand_id"
                                                                    x-model="brandId"
                                                                    x-on:change="cleanSelections()"
                                                                    required
                                                                    class="w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                                @foreach ($brands as $brand)
                                                                    <option value="{{ $brand->id }}" @selected($team->brand_id === $brand->id)>{{ $brand->imprint_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Department</label>
                                                            <select name="department"
                                                                    x-model="department"
                                                                    x-on:change="cleanSelections()"
                                                                    required
                                                                    class="w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                                @foreach ($departments as $department)
                                                                    <option value="{{ $department }}" @selected($team->department === $department)>{{ $department }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Team Name</label>
                                                        <input name="name" value="{{ $team->name }}" required class="w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Manager</label>
                                                            <select name="manager_id"
                                                                    x-model="managerId"
                                                                    :disabled="!canChooseUsers()"
                                                                    class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                                                                <option value="">No manager</option>
                                                                <template x-for="user in filteredUsers()" :key="`edit-manager-${user.id}`">
                                                                    <option :value="user.id" x-text="userLabel(user)"></option>
                                                                </template>
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Team Leader</label>
                                                            <select name="team_leader_id"
                                                                    x-model="teamLeaderId"
                                                                    :disabled="!canChooseUsers()"
                                                                    class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                                                                <option value="">No team leader</option>
                                                                <template x-for="user in filteredUsers()" :key="`edit-leader-${user.id}`">
                                                                    <option :value="user.id" x-text="userLabel(user)"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Description</label>
                                                        <textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ $team->description }}</textarea>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Members</label>
                                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-zinc-800 dark:bg-zinc-950">
                                                            <template x-for="memberId in selectedMembers" :key="`edit-member-input-${memberId}`">
                                                                <input type="hidden" name="member_ids[]" :value="memberId">
                                                            </template>

                                                            <input type="text"
                                                                   x-model="memberSearch"
                                                                   :disabled="!canChooseUsers()"
                                                                   placeholder="Search and select members..."
                                                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:disabled:bg-zinc-800">

                                                            <div class="mt-2 max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" x-show="canChooseUsers() && availableMembers().length" x-cloak>
                                                                <template x-for="user in availableMembers()" :key="`edit-member-option-${user.id}`">
                                                                    <button type="button"
                                                                            x-on:click="addMember(user.id)"
                                                                            class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-amber-50 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                                                        <span class="font-semibold" x-text="user.name"></span>
                                                                        <span class="text-slate-500" x-text="` - ${user.role || 'No role'} / ${user.brand || 'No brand'}`"></span>
                                                                    </button>
                                                                </template>
                                                            </div>

                                                            <div class="mt-3 flex flex-wrap gap-2" x-show="selectedMembers.length" x-cloak>
                                                                <template x-for="memberId in selectedMembers" :key="`edit-selected-member-${memberId}`">
                                                                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                                                                        <span x-text="memberName(memberId)"></span>
                                                                        <button type="button" x-on:click="removeMember(memberId)" class="text-amber-900 hover:text-rose-600">&times;</button>
                                                                    </span>
                                                                </template>
                                                            </div>

                                                            <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400" x-text="canChooseUsers() ? 'Search by name, then click a user to add them as a member.' : 'Select a brand and department first.'"></p>
                                                        </div>
                                                    </div>

                                                    <div class="flex justify-end gap-3">
                                                        <button type="button" x-on:click="editOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
                                                        <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">Save Team</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManageTeams ? 7 : 6 }}" class="px-6 py-12 text-center text-slate-500 dark:text-zinc-400">No teams yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($teams->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $teams->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function teamForm(config) {
            return {
                users: config.users || [],
                parentBrandId: config.parentBrandId || '',
                brandId: config.brandId || '',
                department: config.department || '',
                managerId: config.managerId || '',
                teamLeaderId: config.teamLeaderId || '',
                selectedMembers: config.selectedMembers || [],
                memberSearch: '',

                canChooseUsers() {
                    return Boolean(this.brandId && this.department);
                },

                isParentBrand() {
                    return String(this.brandId) === String(this.parentBrandId);
                },

                filteredUsers() {
                    if (!this.canChooseUsers()) {
                        return [];
                    }

                    return this.users.filter((user) => {
                        const sameDepartment = user.department === this.department;
                        const sameBrand = this.isParentBrand() || String(user.brand_id) === String(this.brandId);

                        return sameDepartment && sameBrand;
                    });
                },

                availableMembers() {
                    const search = this.memberSearch.trim().toLowerCase();

                    return this.filteredUsers()
                        .filter((user) => !this.selectedMembers.includes(String(user.id)))
                        .filter((user) => !search || this.userLabel(user).toLowerCase().includes(search))
                        .slice(0, 12);
                },

                addMember(userId) {
                    const id = String(userId);

                    if (!this.selectedMembers.includes(id)) {
                        this.selectedMembers.push(id);
                    }

                    this.memberSearch = '';
                },

                removeMember(userId) {
                    const id = String(userId);
                    this.selectedMembers = this.selectedMembers.filter((memberId) => memberId !== id);
                },

                memberName(userId) {
                    const user = this.users.find((candidate) => String(candidate.id) === String(userId));

                    return user ? this.userLabel(user) : 'Unknown user';
                },

                userLabel(user) {
                    return `${user.name} - ${user.role || 'No role'} / ${user.brand || 'No brand'}`;
                },

                cleanSelections() {
                    const allowedIds = this.filteredUsers().map((user) => String(user.id));

                    if (this.managerId && !allowedIds.includes(String(this.managerId))) {
                        this.managerId = '';
                    }

                    if (this.teamLeaderId && !allowedIds.includes(String(this.teamLeaderId))) {
                        this.teamLeaderId = '';
                    }

                    this.selectedMembers = this.selectedMembers.filter((memberId) => allowedIds.includes(String(memberId)));
                    this.memberSearch = '';
                },
            };
        }
    </script>
</x-app-layout>
