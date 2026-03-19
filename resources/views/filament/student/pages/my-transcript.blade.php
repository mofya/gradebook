<x-filament-panels::page>
    @if($student && $transcriptData)
        <div class="space-y-6">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Academic Transcript</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $student->first_name }} {{ $student->last_name }} — {{ $student->student_id_number ?? $student->email }}
                        </p>
                    </div>
                    <x-filament::button wire:click="downloadTranscript" icon="heroicon-o-arrow-down-tray">
                        Download PDF
                    </x-filament::button>
                </div>
                <p class="text-lg font-bold text-primary-600 mt-4">
                    Cumulative GPA: {{ number_format($transcriptData['cumulative_gpa'], 2) }} / 4.00
                </p>
            </div>

            @if($semesters->isNotEmpty())
                @foreach($semesters as $semester)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-800 dark:text-white/90">{{ $semester['name'] }}</h3>
                            <span class="text-sm font-medium text-primary-600">Semester GPA: {{ number_format($semester['gpa'], 2) }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Code</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Course</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Credits</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Mark</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Grade</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($semester['enrollments'] as $enrollment)
                                        <tr class="border-b border-gray-50 dark:border-white/5">
                                            <td class="px-4 py-2 font-medium text-gray-800 dark:text-white/90">{{ $enrollment->courseOffering->course->code }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $enrollment->courseOffering->course->name }}</td>
                                            <td class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">{{ $enrollment->courseOffering->course->credits }}</td>
                                            <td class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">{{ $enrollment->final_total !== null ? number_format($enrollment->final_total, 1) : 'N/A' }}</td>
                                            <td class="px-4 py-2 text-center font-bold text-gray-800 dark:text-white/90">{{ $enrollment->final_grade ?? 'N/A' }}</td>
                                            <td class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">{{ $enrollment->grade_points !== null ? number_format($enrollment->grade_points, 1) : 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @elseif(count($transcriptData['courses']) > 0)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Code</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Course</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Credits</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Mark</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Grade</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transcriptData['courses'] as $course)
                                    <tr class="border-b border-gray-50 dark:border-white/5">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $course['course_code'] }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $course['course_name'] }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $course['credits'] }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $course['mark'] !== null ? number_format($course['mark'], 1) : 'N/A' }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-gray-800 dark:text-white/90">{{ $course['letter_grade'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $course['grade_points'] !== null ? number_format($course['grade_points'], 1) : 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No course records available.</p>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-yellow-50 ring-1 ring-yellow-600/20 p-5 text-center dark:bg-yellow-500/10 dark:ring-yellow-500/30">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">No student record found for your account.</p>
        </div>
    @endif
</x-filament-panels::page>
