<x-filament-panels::page>
    @if($student)
        <div class="space-y-6">
            {{-- Student Info Header --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                <div class="flex items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-500/10 shrink-0">
                        <span class="text-xl">🎓</span>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $student->first_name }} {{ $student->last_name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $student->student_id_number ?? 'N/A' }}
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                            {{ $student->program ?? 'N/A' }}
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                            Year {{ $student->year_of_study ?? 'N/A' }}
                            @if($student->github_username)
                                <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ '@' }}{{ $student->github_username }}</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Course Cards --}}
            @if($courses->isNotEmpty())
                @foreach($courses as $course)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                        {{-- Course Header --}}
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <span class="text-base font-bold text-gray-950 dark:text-white">{{ $course['course_code'] }}</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $course['course_name'] }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $course['semester'] }}</p>
                                </div>
                                <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $course['status'] === 'completed' ? 'bg-green-50 text-green-700 ring-1 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/30' : '' }}
                                    {{ $course['status'] === 'enrolled' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30' : '' }}
                                    {{ $course['status'] === 'withdrawn' ? 'bg-red-50 text-red-700 ring-1 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30' : '' }}
                                    {{ $course['status'] === 'deferred' ? 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-600/20 dark:bg-yellow-500/10 dark:text-yellow-400 dark:ring-yellow-500/30' : '' }}
                                ">{{ ucfirst($course['status']) }}</span>
                            </div>
                        </div>

                        <div class="p-5">
                            @if($course['assessments']->isNotEmpty())
                                {{-- Assessment List --}}
                                <div class="space-y-1">
                                    @foreach($course['assessments'] as $assessment)
                                        @php
                                            $pct = ($assessment['raw_score'] !== null && $assessment['max_score'] > 0)
                                                ? round(($assessment['raw_score'] / $assessment['max_score']) * 100, 1)
                                                : null;
                                            $barColor = match(true) {
                                                $pct === null => 'bg-gray-200 dark:bg-gray-700',
                                                $pct >= 80 => 'bg-emerald-500',
                                                $pct >= 60 => 'bg-blue-500',
                                                $pct >= 50 => 'bg-amber-500',
                                                default => 'bg-red-500',
                                            };
                                            $scoreColor = match(true) {
                                                $pct === null => 'text-gray-400 dark:text-gray-500',
                                                $pct >= 80 => 'text-emerald-600 dark:text-emerald-400',
                                                $pct >= 60 => 'text-blue-600 dark:text-blue-400',
                                                $pct >= 50 => 'text-amber-600 dark:text-amber-400',
                                                default => 'text-red-600 dark:text-red-400',
                                            };
                                        @endphp

                                        @if($assessment['has_subsections'])
                                            <div x-data="{ open: false }">
                                                <button
                                                    @click="open = !open"
                                                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                                >
                                                    <span
                                                        class="inline-block text-[10px] leading-none text-gray-400 transition-transform duration-200 shrink-0"
                                                        :class="open ? 'rotate-90' : ''"
                                                    >&#9654;</span>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between gap-4">
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $assessment['name'] }}</span>
                                                            <span class="text-sm font-semibold tabular-nums whitespace-nowrap {{ $scoreColor }}">
                                                                @if($assessment['is_excused'])
                                                                    <span class="italic font-normal text-gray-400">Excused</span>
                                                                @elseif($assessment['raw_score'] !== null)
                                                                    {{ number_format($assessment['raw_score'], 1) }}<span class="font-normal text-gray-400 dark:text-gray-500">/{{ number_format($assessment['max_score'], 0) }}</span>
                                                                @else
                                                                    <span class="text-gray-300 dark:text-gray-600">&ndash;</span>
                                                                @endif
                                                            </span>
                                                        </div>
                                                        @if($pct !== null)
                                                            <div class="mt-2 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                                                <div class="h-full rounded-full {{ $barColor }}" style="width: {{ max(min($pct, 100), 2) }}%"></div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </button>

                                                {{-- Expanded detail --}}
                                                <div x-show="open" x-collapse class="mx-3 mb-2 rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-gray-800/50 dark:ring-white/5 overflow-hidden">
                                                    <div class="px-4 py-3 space-y-2">
                                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Breakdown</p>
                                                        @foreach($assessment['subsections'] as $sub)
                                                            @php
                                                                $subPct = $sub['max_score'] > 0 ? round(($sub['score'] / $sub['max_score']) * 100, 1) : 0;
                                                                $subBar = match(true) {
                                                                    $subPct >= 80 => 'bg-emerald-500',
                                                                    $subPct >= 60 => 'bg-blue-500',
                                                                    $subPct >= 50 => 'bg-amber-500',
                                                                    default => 'bg-red-500',
                                                                };
                                                            @endphp
                                                            <div class="flex items-center justify-between gap-3 text-sm">
                                                                <span class="text-gray-600 dark:text-gray-400 truncate">{{ $sub['name'] }}</span>
                                                                <div class="flex items-center gap-2.5 shrink-0">
                                                                    <div class="w-20 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                                                        <div class="h-full rounded-full {{ $subBar }}" style="width: {{ max(min($subPct, 100), 3) }}%"></div>
                                                                    </div>
                                                                    <span class="w-14 text-right font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($sub['score'], 1) }}%</span>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    @if($assessment['student_feedback'])
                                                        <div class="border-t border-gray-200/60 dark:border-white/5 px-4 py-3">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Feedback</p>
                                                            <div class="text-[13px] text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $assessment['student_feedback'] }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-3 px-3 py-3">
                                                <span class="inline-block text-[10px] leading-none text-transparent shrink-0">&#9654;</span>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center justify-between gap-4">
                                                        <span class="text-sm text-gray-700 dark:text-gray-200">{{ $assessment['name'] }}</span>
                                                        <span class="text-sm font-semibold tabular-nums whitespace-nowrap {{ $scoreColor }}">
                                                            @if($assessment['is_excused'])
                                                                <span class="italic font-normal text-gray-400">Excused</span>
                                                            @elseif($assessment['raw_score'] !== null)
                                                                {{ number_format($assessment['raw_score'], 1) }}<span class="font-normal text-gray-400 dark:text-gray-500">/{{ number_format($assessment['max_score'], 0) }}</span>
                                                            @else
                                                                <span class="text-gray-300 dark:text-gray-600">&ndash;</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                    @if($pct !== null)
                                                        <div class="mt-2 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $barColor }}" style="width: {{ max(min($pct, 100), 2) }}%"></div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                {{-- CA Summary --}}
                                <div class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 ring-1 ring-gray-950/5 dark:ring-white/5 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-5">
                                            <div>
                                                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">CA Total</p>
                                                <p class="text-sm font-semibold text-gray-950 dark:text-white tabular-nums mt-0.5">
                                                    @if($course['weighted_ca'] !== null)
                                                        {{ number_format($course['weighted_ca'], 1) }}<span class="font-normal text-gray-400 dark:text-gray-500">/{{ number_format($course['ca_weight'], 0) }}</span>
                                                    @else
                                                        &ndash;
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                                            <div>
                                                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Percentage</p>
                                                <p class="text-sm font-semibold text-gray-950 dark:text-white tabular-nums mt-0.5">
                                                    @if($course['ca_total'] !== null)
                                                        {{ number_format($course['ca_total'], 1) }}%
                                                    @else
                                                        &ndash;
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        @if($course['ca_grade'])
                                            @php
                                                $gradeBg = match(true) {
                                                    in_array($course['ca_grade'], ['A+', 'A']) => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
                                                    in_array($course['ca_grade'], ['B+', 'B']) => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
                                                    in_array($course['ca_grade'], ['C+', 'C']) => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
                                                    default => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
                                                };
                                            @endphp
                                            <div class="text-center shrink-0">
                                                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Grade</p>
                                                <span class="inline-flex items-center justify-center rounded-lg px-3 py-1 text-lg font-bold ring-1 {{ $gradeBg }}">
                                                    {{ $course['ca_grade'] }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <p class="text-sm text-gray-400 dark:text-gray-500 italic py-2">No assessments set up yet.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No enrollments found.</p>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-yellow-50 ring-1 ring-yellow-600/20 p-5 text-center dark:bg-yellow-500/10 dark:ring-yellow-500/30">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">No student record found for your account.</p>
        </div>
    @endif
</x-filament-panels::page>
