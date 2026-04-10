<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Verification Link Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-200 bg-emerald-50 px-5 py-3 dark:border-gray-800 dark:bg-emerald-500/10">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                    Student Verification Link
                </h3>
                @if ($offering->hasValidVerificationToken())
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Active
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-500/20 dark:text-gray-400">
                        Not Generated
                    </span>
                @endif
            </div>

            <div class="p-5 space-y-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Students use this link to verify their details (GitHub username, email, gender) and view their individual grades.
                </p>

                @if ($offering->hasValidVerificationToken())
                    @php
                        $verifyUrls = $this->getVerificationUrls();
                    @endphp

                    <div class="space-y-3">
                        {{-- Verify Details URL --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Verify Details</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $verifyUrls['verify'] }}"
                                    class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-700 font-mono select-all dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    onclick="this.select()"
                                >
                                <button
                                    type="button"
                                    onclick="navigator.clipboard.writeText('{{ $verifyUrls['verify'] }}'); this.querySelector('span').textContent = 'Copied!'; setTimeout(() => this.querySelector('span').textContent = 'Copy', 1500)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-m-clipboard class="h-3.5 w-3.5" />
                                    <span>Copy</span>
                                </button>
                            </div>
                        </div>

                        {{-- View Grades URL --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">View Individual Grades</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $verifyUrls['grades'] }}"
                                    class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-700 font-mono select-all dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    onclick="this.select()"
                                >
                                <button
                                    type="button"
                                    onclick="navigator.clipboard.writeText('{{ $verifyUrls['grades'] }}'); this.querySelector('span').textContent = 'Copied!'; setTimeout(() => this.querySelector('span').textContent = 'Copy', 1500)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-m-clipboard class="h-3.5 w-3.5" />
                                    <span>Copy</span>
                                </button>
                            </div>
                        </div>

                        {{-- Expiry info --}}
                        <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            <x-heroicon-m-clock class="h-4 w-4 shrink-0" />
                            Expires {{ $offering->verification_expires_at->format('M j, Y g:ia') }}
                            ({{ $offering->verification_expires_at->diffForHumans() }})
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <div x-data="{ days: 3 }" class="flex items-center gap-2">
                            <input
                                type="number"
                                x-model="days"
                                min="1"
                                max="30"
                                class="w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                            >
                            <span class="text-xs text-gray-500 dark:text-gray-400">days</span>
                            <button
                                type="button"
                                x-on:click="$wire.extendVerificationLink(days)"
                                class="inline-flex items-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-amber-600"
                            >
                                Extend
                            </button>
                            <button
                                type="button"
                                x-on:click="$wire.generateVerificationLink(days)"
                                class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700"
                            >
                                Regenerate
                            </button>
                        </div>
                        <button
                            type="button"
                            wire:click="revokeVerificationLink"
                            wire:confirm="Are you sure you want to revoke this link? Students will no longer be able to access it."
                            class="inline-flex items-center rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-red-700"
                        >
                            Revoke
                        </button>
                    </div>
                @else
                    {{-- Generate form --}}
                    <div x-data="{ days: 3 }" class="flex items-center gap-2">
                        <input
                            type="number"
                            x-model="days"
                            min="1"
                            max="30"
                            class="w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >
                        <span class="text-xs text-gray-500 dark:text-gray-400">days</span>
                        <button
                            type="button"
                            x-on:click="$wire.generateVerificationLink(days)"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            Generate Link
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Public Grade Sheet Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-200 bg-blue-50 px-5 py-3 dark:border-gray-800 dark:bg-blue-500/10">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">
                    Public Class Grade Sheet
                </h3>
                @if ($offering->hasValidPublicGradeToken())
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/20 dark:text-blue-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        Active
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-500/20 dark:text-gray-400">
                        Not Generated
                    </span>
                @endif
            </div>

            <div class="p-5 space-y-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    A read-only class grade sheet showing all students' IDs, GitHub usernames, gender, and CA scores. Anyone with the link can view it.
                </p>

                @if ($offering->hasValidPublicGradeToken())
                    @php
                        $publicUrl = $this->getPublicGradeUrl();
                    @endphp

                    <div class="space-y-3">
                        {{-- URL --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Class Grade Sheet</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $publicUrl }}"
                                    class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-700 font-mono select-all dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    onclick="this.select()"
                                >
                                <button
                                    type="button"
                                    onclick="navigator.clipboard.writeText('{{ $publicUrl }}'); this.querySelector('span').textContent = 'Copied!'; setTimeout(() => this.querySelector('span').textContent = 'Copy', 1500)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-m-clipboard class="h-3.5 w-3.5" />
                                    <span>Copy</span>
                                </button>
                                <a
                                    href="{{ $publicUrl }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" />
                                    Open
                                </a>
                            </div>
                        </div>

                        {{-- Expiry info --}}
                        <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            <x-heroicon-m-clock class="h-4 w-4 shrink-0" />
                            Expires {{ $offering->public_grade_token_expires_at->format('M j, Y g:ia') }}
                            ({{ $offering->public_grade_token_expires_at->diffForHumans() }})
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <div x-data="{ days: 7 }" class="flex items-center gap-2">
                            <input
                                type="number"
                                x-model="days"
                                min="1"
                                max="90"
                                class="w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                            >
                            <span class="text-xs text-gray-500 dark:text-gray-400">days</span>
                            <button
                                type="button"
                                x-on:click="$wire.extendPublicGradeLink(days)"
                                class="inline-flex items-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-amber-600"
                            >
                                Extend
                            </button>
                            <button
                                type="button"
                                x-on:click="$wire.generatePublicGradeLink(days)"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700"
                            >
                                Regenerate
                            </button>
                        </div>
                        <button
                            type="button"
                            wire:click="revokePublicGradeLink"
                            wire:confirm="Are you sure you want to revoke this link? The public grade sheet will no longer be accessible."
                            class="inline-flex items-center rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-red-700"
                        >
                            Revoke
                        </button>
                    </div>
                @else
                    {{-- Generate form --}}
                    <div x-data="{ days: 7 }" class="flex items-center gap-2">
                        <input
                            type="number"
                            x-model="days"
                            min="1"
                            max="90"
                            class="w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >
                        <span class="text-xs text-gray-500 dark:text-gray-400">days</span>
                        <button
                            type="button"
                            x-on:click="$wire.generatePublicGradeLink(days)"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700"
                        >
                            Generate Link
                        </button>
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>
