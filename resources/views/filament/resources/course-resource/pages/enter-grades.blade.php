<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        @if($grades)
            <div class="mt-4 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Student</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Grade</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Letter Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grades as $student_id => $data)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="px-4 py-3 text-gray-800 dark:text-white/90">{{ $data['student_name'] }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" wire:model="grades.{{ $student_id }}.grade"
                                               class="w-24 rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($data['grade'] !== null && $data['grade'] !== '')
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                                {{ app(\App\Services\GradingService::class)->getLetterGrade((float) $data['grade']) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <x-filament::button type="submit" class="mt-4">
                Save Grades
            </x-filament::button>
        @endif
    </form>
</x-filament-panels::page>
