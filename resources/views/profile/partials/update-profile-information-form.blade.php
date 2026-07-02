@php
    $profileBrandPrimary = $user->brand?->primary_color ?? '#d97706';
    $profileBrandAccent = $user->brand?->accent_color ?? '#fef3c7';
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-zinc-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post"
          action="{{ route('profile.photo.update') }}"
          enctype="multipart/form-data"
          class="mt-6"
          x-data="{ uploading: false }">
        @csrf

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <button type="button"
                    x-on:click="$refs.profilePhoto.click()"
                    x-bind:disabled="uploading"
                    class="group relative h-24 w-24 shrink-0 overflow-hidden rounded-full transition focus:outline-none disabled:cursor-wait"
                    style="box-shadow: 0 0 0 2px {{ $profileBrandPrimary }};"
                    title="Change profile picture">
                @if ($user->profile_photo_path)
                    <img src="{{ asset('storage/' . $user->profile_photo_path) }}?v={{ $user->updated_at?->timestamp }}"
                         alt="{{ $user->first_name }} {{ $user->last_name }}"
                         class="h-full w-full object-cover">
                @else
                    <span class="flex h-full w-full items-center justify-center text-3xl font-bold"
                          style="background-color: {{ $profileBrandAccent }}; color: {{ $profileBrandPrimary }};">
                        {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                    </span>
                @endif

                <span x-bind:class="uploading ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                      class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/55 text-xs font-bold text-white transition">
                    <span x-show="uploading"
                          x-cloak
                          class="h-6 w-6 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                    <span x-text="uploading ? 'Uploading...' : 'Change'"></span>
                </span>
            </button>

            <div class="flex-1">
                <input id="profile_photo"
                       x-ref="profilePhoto"
                       name="profile_photo"
                       type="file"
                       accept="image/jpeg,image/png,image/webp"
                       x-on:change="if ($event.target.files.length) { uploading = true; $el.form.submit(); }"
                       x-bind:disabled="uploading"
                       class="sr-only">
                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Profile Picture</p>
                <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">
                    Click the picture to upload a new JPG, PNG, or WebP. Maximum file size is 2MB.
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />

                @if (session('status') === 'profile-photo-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="mt-2 text-sm text-gray-600"
                    >{{ __('Picture saved.') }}</p>
                @endif
            </div>
        </div>
    </form>

    <form method="post"
          action="{{ route('profile.update') }}"
          class="mt-8 space-y-6"
          x-data="{ dirty: false }"
          x-on:input="dirty = true"
          x-on:change="dirty = true">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="first_name" :value="__('First Name')" />
            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $user->first_name)" required autofocus autocomplete="given-name" />
            <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
        </div>

        <div>
            <x-input-label for="last_name" :value="__('Last Name')" />
            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $user->last_name)" required autocomplete="family-name" />
            <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button x-bind:disabled="! dirty" x-bind:class="! dirty ? 'cursor-not-allowed opacity-50' : ''">
                {{ __('Save') }}
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
