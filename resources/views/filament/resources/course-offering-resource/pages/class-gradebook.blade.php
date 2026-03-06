<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Search --}}
        <div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search by student ID or name..."
                   class="w-full max-w-sm rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        </div>

        @if(count($students) > 0 && count($assessments) > 0)
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            {{-- Group header row --}}
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="sticky left-0 z-20 bg-gray-50 dark:bg-gray-900 px-4 py-2" colspan="2"></th>
                                @php
                                    $groupedAssessments = collect($assessments)->groupBy('group_name');
                                @endphp
                                @foreach($groupedAssessments as $groupName => $groupAssessments)
                                    <th colspan="{{ count($groupAssessments) }}"
                                        class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 border-l border-gray-200 dark:border-gray-700">
                                        {{ $groupName }}
                                    </th>
                                @endforeach
                                <th colspan="5" class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 border-l border-gray-200 dark:border-gray-700">
                                    Summary
                                </th>
                            </tr>
                            {{-- Assessment header row --}}
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="sticky left-0 z-20 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 min-w-[100px]">
                                    Student ID
                                </th>
                                <th class="sticky left-[100px] z-20 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 min-w-[160px]">
                                    Name
                                </th>
                                @foreach($assessments as $assessment)
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 min-w-[80px] border-l border-gray-100 dark:border-gray-700/50"
                                        title="{{ $assessment['group_name'] }}: {{ $assessment['name'] }} (max: {{ $assessment['max_raw_score'] }})">
                                        <div class="truncate max-w-[80px]">{{ $assessment['name'] }}</div>
                                        <div class="text-gray-400 dark:text-gray-500 text-[10px]">{{ $assessment['max_raw_score'] }}</div>
                                    </th>
                                @endforeach
                                <th class="px-3 py-3 text-center text-xs font-medium uppercase border-l border-gray-200 dark:border-gray-700 min-w-[60px] text-blue-600 dark:text-blue-400">CA</th>
                                <th class="px-3 py-3 text-center text-xs font-medium uppercase border-l border-gray-100 dark:border-gray-700/50 min-w-[60px] text-orange-600 dark:text-orange-400">Exam</th>
                                <th class="px-3 py-3 text-center text-xs font-medium uppercase border-l border-gray-100 dark:border-gray-700/50 min-w-[60px] text-green-600 dark:text-green-400">Total</th>
                                <th class="px-3 py-3 text-center text-xs font-medium uppercase border-l border-gray-100 dark:border-gray-700/50 min-w-[60px] text-purple-600 dark:text-purple-400">Grade</th>
                                <th class="px-3 py-3 text-center text-xs font-medium uppercase border-l border-gray-100 dark:border-gray-700/50 min-w-[50px] text-purple-600 dark:text-purple-400">GP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($students as $enrollmentId => $student)
                                <tr class="border-b border-gray-200 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 px-4 py-3 text-sm text-gray-800 dark:text-white/90 font-mono">
                                        {{ $student['student_id_number'] }}
                                    </td>
                                    <td class="sticky left-[100px] z-10 bg-white dark:bg-gray-800 px-4 py-3 text-sm text-gray-800 dark:text-white/90">
                                        {{ $student['last_name'] }}, {{ $student['first_name'] }}
                                    </td>
                                    @foreach($assessments as $assessment)
                                        @php
                                            $cell = $gradeMatrix[$enrollmentId][$assessment['id']] ?? null;
                                        @endphp
                                        <td class="px-3 py-3 text-center text-sm border-l border-gray-100 dark:border-gray-700/50">
                                            @if($cell && $cell['is_excused'])
                                                <span class="inline-flex items-center rounded-md bg-yellow-50 px-1.5 py-0.5 text-xs font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/30">EX</span>
                                            @elseif($cell && $cell['raw_score'] !== null)
                                                <span class="text-gray-800 dark:text-white/90">{{ number_format((float) $cell['raw_score'], 1) }}</span>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    {{-- Summary columns --}}
                                    <td class="px-3 py-3 text-center text-sm font-medium border-l border-gray-200 dark:border-gray-700 text-blue-700 dark:text-blue-400">
                                        {{ $student['ca_total'] !== null ? number_format((float) $student['ca_total'], 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-sm font-medium border-l border-gray-100 dark:border-gray-700/50 text-orange-700 dark:text-orange-400">
                                        {{ $student['exam_score'] !== null ? number_format((float) $student['exam_score'], 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-sm font-semibold border-l border-gray-100 dark:border-gray-700/50 text-green-700 dark:text-green-400">
                                        {{ $student['final_total'] !== null ? number_format((float) $student['final_total'], 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-sm border-l border-gray-100 dark:border-gray-700/50">
                                        @if($student['final_grade'])
                                            <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-400/10 dark:text-purple-400 dark:ring-purple-400/30">
                                                {{ $student['final_grade'] }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center text-sm font-medium border-l border-gray-100 dark:border-gray-700/50 text-purple-700 dark:text-purple-400">
                                        {{ $student['grade_points'] !== null ? number_format((float) $student['grade_points'], 1) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Showing {{ count($students) }} student(s) &middot; {{ count($assessments) }} assessment(s)
            </p>
        @elseif(count($assessments) === 0)
            <p class="text-sm text-gray-600 dark:text-gray-400">No assessments configured for this offering.</p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">No students found matching your search.</p>
        @endif
    </div>
</x-filament-panels::page>
