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
    <div class="w-full {{ $step === 'found' ? 'max-w-2xl' : 'max-w-md' }}">
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
                        This grades link is no longer active. Please contact your course lecturer for a new link.
                    </p>
                </div>

            {{-- Step: Lookup --}}
            @elseif ($step === 'lookup')
                <div class="p-8">
                    <h2 class="text-lg font-semibold text-stone-900">View Your Grades</h2>
                    <p class="mt-1 text-sm text-stone-500">Enter your student ID to see your CA results.</p>

                    <form wire:submit="viewGrades" class="mt-6">
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
                            <span wire:loading.remove wire:target="viewGrades">View Grades</span>
                            <span wire:loading wire:target="viewGrades" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Loading...
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

            {{-- Step: Found — Grade Results --}}
            @elseif ($step === 'found')
                <div class="divide-y divide-stone-100">
                    {{-- Student info header --}}
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-stone-900">{{ $studentName }}</h2>
                                <p class="text-sm text-stone-500">{{ $studentIdNumber }}</p>
                            </div>
                            @if ($githubUsername)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                                    {{ $githubUsername }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Grades table --}}
                    <div class="p-6">
                        @forelse ($gradeData as $group)
                            <div class="{{ !$loop->first ? 'mt-6' : '' }}">
                                <h3 class="mb-3 text-xs font-medium uppercase tracking-wider text-stone-400">{{ $group['group_name'] }}</h3>
                                <div class="overflow-hidden rounded-lg border border-stone-200">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-stone-50">
                                                <th class="px-4 py-2.5 text-left font-medium text-stone-600">Assessment</th>
                                                <th class="px-4 py-2.5 text-right font-medium text-stone-600">Score</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-stone-100">
                                            @foreach ($group['assessments'] as $assessment)
                                                <tr>
                                                    <td class="px-4 py-3 text-stone-900">{{ $assessment['name'] }}</td>
                                                    <td class="px-4 py-3 text-right">
                                                        @if ($assessment['is_excused'])
                                                            <span class="text-stone-400 italic">Excused</span>
                                                        @elseif ($assessment['raw_score'] !== null)
                                                            <span class="font-medium text-stone-900">{{ number_format($assessment['raw_score'], 1) }}</span>
                                                            <span class="text-stone-400"> / {{ number_format($assessment['max_score'], 0) }}</span>
                                                        @else
                                                            <span class="text-stone-300">&mdash;</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-stone-500">No assessments have been set up yet.</p>
                        @endforelse
                    </div>

                    {{-- Back button --}}
                    <div class="p-6">
                        <button
                            wire:click="resetLookup"
                            class="flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 shadow-sm transition hover:bg-stone-50"
                        >
                            Look Up Another Student
                        </button>
                    </div>
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <p class="mt-6 text-center text-xs text-stone-400">
            {{ config('app.name') }}
        </p>
    </div>
</div>
