<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Generate Report
        </x-filament::button>
    </form>

    @if($reportData)
        <div class="space-y-6 mt-6">
            {{-- Header with export --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $reportData['course_code'] }} — {{ $reportData['course_name'] }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $reportData['semester'] }} | Lecturer: {{ $reportData['lecturer'] }}</p>
                    </div>
                    <x-filament::button wire:click="exportExcel" icon="heroicon-o-arrow-down-tray" color="success">
                        Export Excel
                    </x-filament::button>
                </div>
            </div>

            {{-- Statistics Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-2xl font-bold text-primary-600">{{ $reportData['stats']['total_enrolled'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Enrolled</p>
                </div>
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $reportData['stats']['average'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Average</p>
                </div>
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $reportData['stats']['pass_rate'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pass Rate</p>
                </div>
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $reportData['stats']['median'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Median</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $reportData['stats']['highest'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Highest</p>
                </div>
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-lg font-bold text-red-600 dark:text-red-400">{{ $reportData['stats']['lowest'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Lowest</p>
                </div>
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ number_format($reportData['stats']['std_deviation'], 2) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Std Dev</p>
                </div>
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-lg font-bold text-gray-600 dark:text-gray-300">{{ $reportData['stats']['graded'] }}/{{ $reportData['stats']['total_enrolled'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Graded</p>
                </div>
            </div>

            {{-- Grade Distribution Chart --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Grade Distribution</h3>
                <div class="flex items-end gap-2 h-48">
                    @php
                        $maxCount = max(1, max($reportData['distribution']));
                    @endphp
                    @foreach($reportData['distribution'] as $grade => $count)
                        <div class="flex-1 flex flex-col items-center">
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">{{ $count }}</span>
                            <div class="w-full rounded-t
                                @if(str_starts_with($grade, 'A')) bg-green-500
                                @elseif(str_starts_with($grade, 'B')) bg-blue-500
                                @elseif(str_starts_with($grade, 'C')) bg-yellow-500
                                @elseif(str_starts_with($grade, 'D')) bg-orange-500
                                @else bg-red-500
                                @endif
                            " style="height: {{ $maxCount > 0 ? ($count / $maxCount) * 100 : 0 }}%"></div>
                            <span class="text-xs font-medium mt-1 text-gray-600 dark:text-gray-400">{{ $grade }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Per-Assessment Stats --}}
            @if(count($reportData['assessment_stats'] ?? []) > 0)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Per-Assessment Statistics</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Assessment</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Graded</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Average</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Highest</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Lowest</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Max Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportData['assessment_stats'] as $stat)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="px-4 py-3 text-gray-800 dark:text-white/90">{{ $stat['assessment_name'] }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $stat['count'] }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $stat['average'] }}</td>
                                        <td class="px-4 py-3 text-center text-green-600 dark:text-green-400">{{ $stat['highest'] }}</td>
                                        <td class="px-4 py-3 text-center text-red-600 dark:text-red-400">{{ $stat['lowest'] }}</td>
                                        <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">{{ $stat['max_raw_score'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Student Results Table --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 p-5 pb-0">Student Results</h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-sm mt-4">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 sticky left-0 bg-white dark:bg-gray-900 z-10">Student</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">ID</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">CA</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Exam</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Total</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Grade</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Points</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['students'] as $student)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="px-4 py-3 text-gray-800 dark:text-white/90 sticky left-0 bg-white dark:bg-gray-900 z-10">{{ $student['name'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $student['student_id'] }}</td>
                                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $student['ca_total'] !== null ? number_format($student['ca_total'], 1) : '-' }}</td>
                                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $student['exam_score'] !== null ? number_format($student['exam_score'], 1) : '-' }}</td>
                                    <td class="px-4 py-3 text-center font-semibold text-gray-800 dark:text-white/90">{{ $student['final_total'] !== null ? number_format($student['final_total'], 1) : '-' }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-gray-800 dark:text-white/90">{{ $student['final_grade'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $student['grade_points'] !== null ? number_format($student['grade_points'], 1) : '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <x-status-badge :status="$student['status']" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
