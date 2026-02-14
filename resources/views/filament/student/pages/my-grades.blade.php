<x-filament-panels::page>
    @if($student)
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-2">Academic Summary</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Student ID: {{ $student->student_id_number ?? 'N/A' }} |
                    Program: {{ $student->program ?? 'N/A' }} |
                    Year: {{ $student->year_of_study ?? 'N/A' }}
                </p>
                <p class="text-2xl font-bold text-primary-600 mt-2">CGPA: {{ number_format($cgpa, 2) }}</p>

                @if(count($semesterGpas) > 0)
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach($semesterGpas as $semGpa)
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ $semGpa['name'] }}: <span class="ml-1 font-bold">{{ number_format($semGpa['gpa'], 2) }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($enrollments->isNotEmpty())
                @foreach($enrollments as $enrollment)
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-gray-800 dark:text-white/90">{{ $enrollment->courseOffering->course->code }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $enrollment->courseOffering->course->name }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $enrollment->status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : '' }}
                                    {{ $enrollment->status === 'enrolled' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : '' }}
                                    {{ $enrollment->status === 'withdrawn' ? 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400' : '' }}
                                    {{ $enrollment->status === 'deferred' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400' : '' }}
                                ">
                                    {{ ucfirst($enrollment->status) }}
                                </span>
                                @if($enrollment->courseOffering->is_published && $enrollment->final_grade)
                                    <span class="text-lg font-bold text-primary-600">{{ $enrollment->final_grade }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="px-5 py-4">
                            @if($enrollment->courseOffering->is_published)
                                <div class="grid grid-cols-4 gap-4 text-center text-sm mb-3">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">CA Total</p>
                                        <p class="font-semibold text-gray-800 dark:text-white/90">{{ $enrollment->ca_total !== null ? number_format($enrollment->ca_total, 1) : '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Exam</p>
                                        <p class="font-semibold text-gray-800 dark:text-white/90">{{ $enrollment->exam_score !== null ? number_format($enrollment->exam_score, 1) : '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Final Mark</p>
                                        <p class="font-bold text-gray-800 dark:text-white/90">{{ $enrollment->final_total !== null ? number_format($enrollment->final_total, 1) : '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Grade Points</p>
                                        <p class="font-semibold text-gray-800 dark:text-white/90">{{ $enrollment->grade_points !== null ? number_format($enrollment->grade_points, 1) : '-' }}</p>
                                    </div>
                                </div>

                                @if($enrollment->gradeResults->isNotEmpty())
                                    <details class="mt-2">
                                        <summary class="text-xs text-primary-600 cursor-pointer hover:underline">View Assessment Breakdown</summary>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="w-full border-collapse text-xs">
                                                <thead>
                                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Assessment</th>
                                                        <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Raw Score</th>
                                                        <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Normalized</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($enrollment->gradeResults as $result)
                                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $result->assessment->name ?? 'Unknown' }}</td>
                                                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">
                                                                @if($result->is_excused)
                                                                    <span class="italic text-gray-400 dark:text-gray-500">Excused</span>
                                                                @else
                                                                    {{ $result->raw_score !== null ? number_format($result->raw_score, 1) : '-' }}
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $result->normalized_score !== null ? number_format($result->normalized_score, 1) : '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                @endif

                                @if($enrollment->remarks)
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">{{ $enrollment->remarks }}</p>
                                @endif
                            @else
                                <p class="text-sm text-gray-400 dark:text-gray-500 italic">Grades not yet published.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="rounded-2xl border border-gray-200 bg-white p-5 text-center dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No enrollments found.</p>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 text-center dark:border-yellow-700 dark:bg-yellow-900/30">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">No student record found for your account.</p>
        </div>
    @endif
</x-filament-panels::page>
