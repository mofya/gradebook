<x-filament-panels::page>
    <form wire:submit="submit">
        <div class="mb-4">
            <label for="selectedAssessmentId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Exam Assessment
            </label>
            <select wire:model.live="selectedAssessmentId" id="selectedAssessmentId"
                    class="w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                <option value="">Select an exam assessment...</option>
                @foreach($this->examAssessments as $assessment)
                    <option value="{{ $assessment->id }}">{{ $assessment->name }} (max: {{ $assessment->max_raw_score }})</option>
                @endforeach
            </select>
        </div>

        @if(count($grades) > 0)
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student Name</th>
                                @if($hasSubsections)
                                    @foreach($subsections as $sub)
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            {{ $sub['name'] }} <span class="text-gray-400 dark:text-gray-500">({{ $sub['max_score'] }})</span>
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total</th>
                                @else
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Raw Score</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grades as $enrollmentId => $data)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $data['student_id_number'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $data['student_name'] }}</td>
                                    @if($hasSubsections)
                                        @foreach($subsections as $sub)
                                            <td class="px-4 py-3">
                                                <input type="number" step="0.01" min="0" max="{{ $sub['max_score'] }}"
                                                       wire:model="subsectionGrades.{{ $enrollmentId }}.{{ $sub['id'] }}"
                                                       class="w-20 rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-800 dark:text-white/90">
                                            {{ $data['raw_score'] !== null ? number_format($data['raw_score'], 2) : '—' }}
                                        </td>
                                    @else
                                        <td class="px-4 py-3">
                                            <input type="number" step="0.01" min="0"
                                                   wire:model="grades.{{ $enrollmentId }}.raw_score"
                                                   class="w-24 rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <x-filament::button type="submit" class="mt-4">
                Save Exam Grades
            </x-filament::button>
        @elseif($selectedAssessmentId)
            <p class="text-sm text-gray-600 dark:text-gray-400">No enrollments found for this offering.</p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">Select an exam assessment to begin entering grades.</p>
        @endif
    </form>
</x-filament-panels::page>
