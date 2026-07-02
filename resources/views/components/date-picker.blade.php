@props([
    'name',
    'id' => null,
    'value' => '',
    'required' => false,
    'placeholder' => 'Select a date',
    'min' => null,
    'max' => null,
])

@php
    $inputId = $id ?: $name;
    $inputClass = $attributes->get('class', 'w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100');
    $externalModel = $attributes->get('x-model');
@endphp

<div class="relative"
     x-data="datePicker(@js((string) $value), @js($min), @js($max))"
     @if ($externalModel) x-modelable="value" x-model="{{ $externalModel }}" @endif>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <button id="{{ $inputId }}"
            x-ref="trigger"
            type="button"
            x-on:click="toggle()"
            x-on:keydown.arrow-down.prevent="openPicker()"
            class="{{ $inputClass }} flex items-center justify-between gap-3 text-left focus:border-[var(--brand-primary,#d97706)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-primary,#d97706)]"
            aria-haspopup="dialog"
            x-bind:aria-expanded="open.toString()"
            @if ($required) aria-required="true" @endif>
        <span x-text="displayValue || @js($placeholder)"
              x-bind:class="displayValue ? 'text-slate-900 dark:text-zinc-100' : 'text-slate-400 dark:text-zinc-500'"></span>
        <svg class="h-5 w-5 shrink-0 text-slate-500 dark:text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
            <path d="M16 3v4M8 3v4M3 10h18"></path>
        </svg>
    </button>

    <template x-teleport="body">
    <div x-show="open"
         x-cloak
         x-transition.opacity
         x-on:click.outside="close()"
         x-on:keydown.escape.window="close()"
         x-on:resize.window="updatePosition()"
         x-on:scroll.window="updatePosition()"
         x-bind:style="popupStyle"
         class="fixed z-[9999] w-[21rem] rounded-xl border border-slate-200 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
         role="dialog"
         aria-label="Choose date">
        <div class="flex items-center justify-between gap-3">
            <button type="button" x-on:click="previousMonth()" class="grid h-9 w-9 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="Previous month">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"></path></svg>
            </button>
            <p class="font-semibold text-slate-900 dark:text-zinc-100" x-text="monthLabel"></p>
            <button type="button" x-on:click="nextMonth()" class="grid h-9 w-9 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="Next month">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>
            </button>
        </div>

        <div class="mt-3 grid grid-cols-7 text-center text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">
            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
        </div>

        <div class="mt-2 grid grid-cols-7 gap-1">
            <template x-for="day in calendarDays" :key="day.date">
                <button type="button"
                        x-on:click="selectDate(day)"
                        x-bind:disabled="day.disabled"
                        x-bind:aria-label="day.label"
                        x-bind:aria-pressed="day.date === value"
                        x-text="day.day"
                        class="grid aspect-square place-items-center rounded-lg text-sm transition disabled:cursor-not-allowed disabled:opacity-30"
                        x-bind:class="day.date === value
                            ? 'bg-[var(--brand-primary,#d97706)] font-semibold text-white'
                            : day.today
                                ? 'ring-1 ring-inset ring-[var(--brand-primary,#d97706)] text-[var(--brand-primary,#d97706)]'
                                : day.currentMonth
                                    ? 'text-slate-700 hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800'
                                    : 'text-slate-300 hover:bg-slate-50 dark:text-zinc-600 dark:hover:bg-zinc-800/60'">
                </button>
            </template>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-zinc-800">
            <button type="button" x-on:click="clearDate()" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                Clear
            </button>
            <button type="button" x-on:click="selectToday()" class="rounded-lg px-3 py-2 text-sm font-semibold text-[var(--brand-primary,#d97706)] hover:bg-slate-100 dark:hover:bg-zinc-800">
                Today
            </button>
        </div>
    </div>
    </template>
</div>
