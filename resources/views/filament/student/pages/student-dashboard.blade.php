<x-filament-panels::page>
    @if($student)
        <div class="space-y-6">
            {{-- Profile Card --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-500/10 shrink-0">
                            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}</span>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $student->first_name }} {{ $student->last_name }}</h2>
                            <div class="mt-1 flex items-center gap-2 flex-wrap text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ $student->student_id_number ?? 'N/A' }}</span>
                                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                <span>{{ $student->program ?? 'N/A' }}</span>
                                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                <span>Year {{ $student->year_of_study ?? 'N/A' }}</span>
                            </div>
                            <div class="mt-1">
                                @if($editingGithub)
                                    <form wire:submit="saveGithubUsername" class="flex items-center gap-2">
                                        <span class="text-sm text-gray-400 dark:text-gray-500">GitHub:</span>
                                        <input
                                            type="text"
                                            wire:model="githubUsername"
                                            class="h-7 w-48 rounded-md border-gray-300 bg-white px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="your-github-username"
                                            autofocus
                                        >
                                        <button type="submit" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Save</button>
                                        <button type="button" wire:click="toggleEditGithub" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Cancel</button>
                                    </form>
                                @elseif($student->github_username)
                                    <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500">
                                        <span>GitHub:</span>
                                        <span class="font-medium text-gray-600 dark:text-gray-300">{{ '@' }}{{ $student->github_username }}</span>
                                        <button wire:click="toggleEditGithub" class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">Edit</button>
                                    </div>
                                @else
                                    <button wire:click="toggleEditGithub" class="flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                        <span>+ Add GitHub username</span>
                                    </button>
                                @endif
                            </div>
                            <div class="mt-1">
                                @if($editingSex)
                                    <form wire:submit="saveSex" class="flex items-center gap-2">
                                        <span class="text-sm text-gray-400 dark:text-gray-500">Sex:</span>
                                        <select
                                            wire:model="sex"
                                            class="h-7 rounded-md border-gray-300 bg-white px-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >
                                            <option value="">-- Select --</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                        <button type="submit" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Save</button>
                                        <button type="button" wire:click="toggleEditSex" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Cancel</button>
                                    </form>
                                @elseif($student->gender)
                                    <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500">
                                        <span>Sex:</span>
                                        <span class="font-medium text-gray-600 dark:text-gray-300">{{ $student->gender }}</span>
                                        <button wire:click="toggleEditSex" class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">Edit</button>
                                    </div>
                                @else
                                    <button wire:click="toggleEditSex" class="flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                        <span>+ Add sex</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if($student->study_mode)
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30">
                            {{ ucfirst($student->study_mode) }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Missing Info Prompts --}}
            @if(!$student->github_username || !$student->gender)
                <div class="space-y-3">
                    @if(!$student->github_username)
                        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-600/10 px-5 py-3 dark:bg-amber-500/5 dark:ring-amber-500/20">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm text-amber-800 dark:text-amber-200">
                                    Your GitHub username is not set. Lab grades are matched by GitHub username &mdash; add yours to ensure your grades are linked correctly.
                                </p>
                                <button wire:click="toggleEditGithub" class="shrink-0 rounded-lg bg-amber-600 px-3 py-1 text-xs font-medium text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600">
                                    Add now
                                </button>
                            </div>
                        </div>
                    @endif
                    @if(!$student->gender)
                        <div class="rounded-xl bg-blue-50 ring-1 ring-blue-600/10 px-5 py-3 dark:bg-blue-500/5 dark:ring-blue-500/20">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm text-blue-800 dark:text-blue-200">
                                    Your sex is not set. This information is used for reporting purposes &mdash; please add it to your profile.
                                </p>
                                <button wire:click="toggleEditSex" class="shrink-0 rounded-lg bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">
                                    Add now
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Stats Row --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Courses Enrolled --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Courses Enrolled</p>
                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white tabular-nums">{{ $stats['enrolled_courses'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $stats['graded_courses'] }} with CA grades</p>
                </div>

                {{-- Overall CA Average --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">CA Average</p>
                    @if($stats['overall_average'] !== null)
                        @php
                            $avgColor = match(true) {
                                $stats['overall_average'] >= 80 => 'text-emerald-600 dark:text-emerald-400',
                                $stats['overall_average'] >= 60 => 'text-blue-600 dark:text-blue-400',
                                $stats['overall_average'] >= 50 => 'text-amber-600 dark:text-amber-400',
                                default => 'text-red-600 dark:text-red-400',
                            };
                        @endphp
                        <p class="mt-1 text-2xl font-bold tabular-nums {{ $avgColor }}">{{ number_format($stats['overall_average'], 1) }}%</p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Across {{ $stats['graded_courses'] }} graded {{ Str::plural('course', $stats['graded_courses']) }}</p>
                    @else
                        <p class="mt-1 text-2xl font-bold text-gray-300 dark:text-gray-600">&ndash;</p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">No grades yet</p>
                    @endif
                </div>

                {{-- Open Queries --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Open Queries</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums {{ $openQueries > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-950 dark:text-white' }}">{{ $openQueries }}</p>
                    <a href="{{ url('/student/grade-queries') }}" class="mt-0.5 inline-block text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">View queries &rarr;</a>
                </div>
            </div>

            {{-- Course Overview --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-3">Course Overview</h3>
                @if($courseCards->isNotEmpty())
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach($courseCards as $course)
                            @php
                                $gradeBg = match(true) {
                                    $course['ca_grade'] === null => 'bg-gray-50 text-gray-400 ring-gray-600/10 dark:bg-gray-800 dark:text-gray-500 dark:ring-gray-500/20',
                                    in_array($course['ca_grade'], ['A+', 'A']) => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
                                    in_array($course['ca_grade'], ['B+', 'B']) => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
                                    in_array($course['ca_grade'], ['C+', 'C']) => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
                                    default => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
                                };
                                $statusColor = match($course['status']) {
                                    'completed' => 'text-emerald-600 dark:text-emerald-400',
                                    'enrolled' => 'text-blue-600 dark:text-blue-400',
                                    'withdrawn' => 'text-red-600 dark:text-red-400',
                                    'deferred' => 'text-amber-600 dark:text-amber-400',
                                    default => 'text-gray-500 dark:text-gray-400',
                                };
                            @endphp
                            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $course['course_code'] }}</span>
                                            <span class="text-xs {{ $statusColor }}">{{ ucfirst($course['status']) }}</span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $course['course_name'] }}</p>
                                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                            @if($course['weighted_ca'] !== null)
                                                CA: <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($course['weighted_ca'], 1) }}/{{ number_format($course['ca_weight'], 0) }}</span>
                                                <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                                {{ number_format($course['ca_total'], 1) }}%
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">No CA grades yet</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center justify-center rounded-lg px-2.5 py-1 text-base font-bold ring-1 shrink-0 {{ $gradeBg }}">
                                        {{ $course['ca_grade'] ?? '—' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-8 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No course enrollments found.</p>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="rounded-xl bg-yellow-50 ring-1 ring-yellow-600/20 p-5 text-center dark:bg-yellow-500/10 dark:ring-yellow-500/30">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">No student record found for your account.</p>
        </div>
    @endif
</x-filament-panels::page>
