<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if($currentYear)
                        Academic Year {{ $currentYear->name }}
                    @else
                        No active academic year
                    @endif
                </p>
            </div>
        </div>

        {{-- Key Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Total Students</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white tabular-nums">{{ number_format($totalStudents) }}</p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $studentsWithGrades }} with CA grades</p>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Current Enrollments</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white tabular-nums">{{ number_format($currentEnrollments) }}</p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Across {{ $offeringStats->count() }} {{ Str::plural('offering', $offeringStats->count()) }}</p>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Course Offerings</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white tabular-nums">{{ $offeringStats->count() }}</p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">This academic year</p>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                <p class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Open Queries</p>
                <p class="mt-1 text-2xl font-bold tabular-nums {{ $openQueries > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-950 dark:text-white' }}">{{ $openQueries }}</p>
                <a href="{{ \App\Filament\Resources\GradeQueryResource::getUrl() }}" class="mt-0.5 inline-block text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">Manage queries &rarr;</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Course Offerings Overview (2/3 width) --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Course Offerings</h3>
                            <a href="{{ \App\Filament\Resources\CourseOfferingResource::getUrl() }}" class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">View all &rarr;</a>
                        </div>
                    </div>
                    @if($offeringStats->isNotEmpty())
                        <div class="divide-y divide-gray-50 dark:divide-white/5">
                            @foreach($offeringStats as $offering)
                                @php
                                    $progress = $offering['enrolled'] > 0 ? round(($offering['graded'] / $offering['enrolled']) * 100) : 0;
                                    $avgColor = match(true) {
                                        $offering['average'] === null => 'text-gray-400 dark:text-gray-500',
                                        $offering['average'] >= 70 => 'text-emerald-600 dark:text-emerald-400',
                                        $offering['average'] >= 50 => 'text-blue-600 dark:text-blue-400',
                                        default => 'text-red-600 dark:text-red-400',
                                    };
                                @endphp
                                <div class="px-5 py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $offering['code'] }}</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $offering['name'] }}</span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $offering['semester'] }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            @if($offering['average'] !== null)
                                                <span class="text-sm font-bold tabular-nums {{ $avgColor }}">{{ number_format($offering['average'], 1) }}%</span>
                                                <p class="text-[11px] text-gray-400 dark:text-gray-500">avg</p>
                                            @else
                                                <span class="text-sm text-gray-300 dark:text-gray-600">&ndash;</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $offering['graded'] }} of {{ $offering['enrolled'] }} graded">
                                            <div class="h-full rounded-full {{ $progress >= 80 ? 'bg-emerald-500' : ($progress >= 40 ? 'bg-blue-500' : 'bg-amber-500') }}" style="width: {{ max($progress, 2) }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">{{ $offering['graded'] }}/{{ $offering['enrolled'] }} graded</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 py-8 text-center">
                            <p class="text-sm text-gray-400 dark:text-gray-500">No course offerings for the current year.</p>
                        </div>
                    @endif
                </div>

                {{-- Recent Activity --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent Activity</h3>
                            <a href="{{ \App\Filament\Pages\AuditLog::getUrl() }}" class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">Full log &rarr;</a>
                        </div>
                    </div>
                    @if($recentActivity->isNotEmpty())
                        <div class="divide-y divide-gray-50 dark:divide-white/5">
                            @foreach($recentActivity as $activity)
                                @php
                                    $dotColor = match($activity['action']) {
                                        'created' => 'bg-emerald-500',
                                        'updated' => 'bg-blue-500',
                                        'deleted' => 'bg-red-500',
                                        'overridden' => 'bg-purple-500',
                                        default => 'bg-gray-400',
                                    };
                                @endphp
                                <div class="px-5 py-3 flex items-start gap-3">
                                    <div class="mt-1.5 h-2 w-2 rounded-full {{ $dotColor }} shrink-0"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                                <span class="font-medium">{{ $activity['user'] }}</span>
                                                {{ $activity['action'] }}
                                                <span class="text-gray-500 dark:text-gray-400">{{ $activity['record'] }}</span>
                                            </span>
                                        </div>
                                        @if($activity['summary'] && $activity['summary'] !== 'Timestamp update')
                                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 truncate">{{ $activity['summary'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 whitespace-nowrap">{{ $activity['time'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 py-8 text-center">
                            <p class="text-sm text-gray-400 dark:text-gray-500">No recent activity.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right sidebar (1/3 width) --}}
            <div class="space-y-6">
                {{-- Pending Queries --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Pending Queries</h3>
                    </div>
                    @if($pendingQueries->isNotEmpty())
                        <div class="divide-y divide-gray-50 dark:divide-white/5">
                            @foreach($pendingQueries as $query)
                                @php
                                    $statusStyle = match($query->status) {
                                        'open' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
                                        'under_review' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
                                        default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30',
                                    };
                                @endphp
                                <div class="px-5 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $query->subject }}</p>
                                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                                {{ $query->student->first_name ?? '' }} {{ $query->student->last_name ?? '' }}
                                                <span class="mx-0.5">&middot;</span>
                                                {{ $query->enrollment?->courseOffering?->course?->code ?? '' }}
                                            </p>
                                        </div>
                                        <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 {{ $statusStyle }}">
                                            {{ str_replace('_', ' ', ucfirst($query->status)) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $query->created_at->diffForHumans() }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 py-6 text-center">
                            <p class="text-sm text-gray-400 dark:text-gray-500">No pending queries.</p>
                        </div>
                    @endif
                </div>

                {{-- Quick Actions --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Quick Actions</h3>
                    </div>
                    <div class="p-3 space-y-1">
                        <a href="{{ \App\Filament\Pages\ImportStudents::getUrl() }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5 transition-colors">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/10 shrink-0">
                                <x-heroicon-o-user-plus class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            </span>
                            <span>Import Students</span>
                        </a>
                        <a href="{{ \App\Filament\Pages\ImportCourseData::getUrl() }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5 transition-colors">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-500/10 shrink-0">
                                <x-heroicon-o-arrow-up-tray class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            </span>
                            <span>Import Grades</span>
                        </a>
                        <a href="{{ \App\Filament\Pages\ImportLabGrades::getUrl() }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5 transition-colors">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-500/10 shrink-0">
                                <x-heroicon-o-beaker class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                            </span>
                            <span>Import Lab Grades</span>
                        </a>
                        <a href="{{ \App\Filament\Pages\ClassReport::getUrl() }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5 transition-colors">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-500/10 shrink-0">
                                <x-heroicon-o-chart-bar class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                            </span>
                            <span>Class Report</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
