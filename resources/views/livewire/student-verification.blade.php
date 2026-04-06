<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">

    {{-- Course header pill --}}
    @if ($step !== 'expired')
        <div class="mb-6 text-center">
            <span class="inline-block rounded-full bg-emerald-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-emerald-800">
                {{ $courseCode }}
            </span>
            <h1 class="mt-3 text-2xl font-bold text-stone-900">{{ $courseName }}</h1>
            <p class="mt-1 text-sm text-stone-500">{{ $semesterLabel }}</p>
        </div>
    @endif

    {{-- Main card --}}
    <div class="w-full max-w-md">
        <div class="rounded-xl border border-stone-200 bg-white shadow-sm">

            {{-- Step: Expired --}}
            @if ($step === 'expired')
                <div class="p-8 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-stone-100">
                        <svg class="h-7 w-7 text-stone-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-stone-900">Link Expired</h2>
                    <p class="mt-2 text-sm leading-relaxed text-stone-500">
                        This verification link is no longer active. Please contact your course lecturer for a new link.
                    </p>
                </div>

            {{-- Step: Lookup --}}
            @elseif ($step === 'lookup')
                <div class="p-8">
                    <h2 class="text-lg font-semibold text-stone-900">Verify Your Details</h2>
                    <p class="mt-1 text-sm text-stone-500">Enter your student ID to get started.</p>

                    <form wire:submit="verifyStudent" class="mt-6">
                        <label for="studentIdNumber" class="block text-sm font-medium text-stone-700">Student ID Number</label>
                        <input
                            wire:model="studentIdNumber"
                            type="text"
                            id="studentIdNumber"
                            placeholder="e.g. 2023123456"
                            autocomplete="off"
                            class="mt-1.5 block w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-stone-900 shadow-sm transition placeholder:text-stone-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none"
                        >
                        @error('studentIdNumber')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <button
                            type="submit"
                            class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:ring-2 focus:ring-emerald-600/50 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="verifyStudent">Verify</span>
                            <span wire:loading wire:target="verifyStudent" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Checking...
                            </span>
                        </button>
                    </form>
                </div>

            {{-- Step: Not Found --}}
            @elseif ($step === 'not_found')
                <div class="p-8">
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                        <div class="flex gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-red-800">Not Found</h3>
                                <p class="mt-1 text-sm leading-relaxed text-red-700">{{ $errorMessage }}</p>
                            </div>
                        </div>
                    </div>
                    <button
                        wire:click="resetLookup"
                        class="mt-5 flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 shadow-sm transition hover:bg-stone-50"
                    >
                        Try Again
                    </button>
                </div>

            {{-- Step: Found --}}
            @elseif ($step === 'found')
                <div class="divide-y divide-stone-100">
                    {{-- Student info (read-only) --}}
                    <div class="p-8 pb-6">
                        <h2 class="text-lg font-semibold text-stone-900">Hello, {{ $studentName }}</h2>
                        <p class="mt-1 text-sm text-stone-500">Please review and update your details below.</p>

                        <dl class="mt-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-stone-500">Student ID</dt>
                                <dd class="text-sm font-medium text-stone-900">{{ $studentIdNumber }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-stone-500">Email</dt>
                                <dd class="text-sm font-medium text-stone-900">{{ $studentEmail }}</dd>
                            </div>
                            @if ($currentGithub)
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm text-stone-500">Current GitHub</dt>
                                    <dd class="text-sm font-medium text-stone-900">{{ $currentGithub }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    {{-- Editable fields --}}
                    <form wire:submit="updateDetails" class="p-8 pt-6">
                        <p class="mb-4 text-xs font-medium uppercase tracking-wider text-stone-400">Update Your Details</p>

                        <div class="space-y-4">
                            <div>
                                <label for="githubUsername" class="block text-sm font-medium text-stone-700">GitHub Username</label>
                                <div class="mt-1.5 flex">
                                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-stone-300 bg-stone-50 px-3 text-sm text-stone-500">
                                        github.com/
                                    </span>
                                    <input
                                        wire:model="githubUsername"
                                        type="text"
                                        id="githubUsername"
                                        placeholder="your-username"
                                        class="block w-full rounded-r-lg border border-stone-300 px-3.5 py-2.5 text-stone-900 shadow-sm transition placeholder:text-stone-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none"
                                    >
                                </div>
                                @error('githubUsername')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="personalEmail" class="block text-sm font-medium text-stone-700">GitHub Email</label>
                                <p class="text-xs text-stone-400">The email address linked to your GitHub account.</p>
                                <input
                                    wire:model="personalEmail"
                                    type="email"
                                    id="personalEmail"
                                    placeholder="you@example.com"
                                    class="mt-1.5 block w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-stone-900 shadow-sm transition placeholder:text-stone-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none"
                                >
                                @error('personalEmail')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-medium text-stone-700">Gender</label>
                                <select
                                    wire:model="gender"
                                    id="gender"
                                    class="mt-1.5 block w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-stone-900 shadow-sm transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none"
                                >
                                    <option value="">Select...</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                @error('gender')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button
                                type="button"
                                wire:click="resetLookup"
                                class="flex-1 rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 shadow-sm transition hover:bg-stone-50"
                            >
                                Back
                            </button>
                            <button
                                type="submit"
                                class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:ring-2 focus:ring-emerald-600/50 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="updateDetails">Save Details</span>
                                <span wire:loading wire:target="updateDetails" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

            {{-- Step: Updated --}}
            @elseif ($step === 'updated')
                <div class="p-8 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-7 w-7 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-stone-900">Details Updated</h2>
                    <p class="mt-2 text-sm text-stone-500">Your information has been saved successfully.</p>

                    @if ($backfillCount > 0)
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            {{ $backfillCount }} lab grade{{ $backfillCount > 1 ? 's were' : ' was' }} automatically linked to your account.
                        </div>
                    @endif

                    <button
                        wire:click="resetLookup"
                        class="mt-6 inline-flex items-center rounded-lg border border-stone-300 bg-white px-5 py-2.5 text-sm font-medium text-stone-700 shadow-sm transition hover:bg-stone-50"
                    >
                        Verify Another Student
                    </button>
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <p class="mt-6 text-center text-xs text-stone-400">
            {{ config('app.name') }}
        </p>
    </div>
</div>
