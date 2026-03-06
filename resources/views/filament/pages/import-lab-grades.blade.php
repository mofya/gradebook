<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Steps indicator --}}
        <div class="flex items-center gap-2 text-sm">
            @foreach(['Upload & Configure', 'Preview Matches', 'Results'] as $i => $label)
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold
                        {{ $currentStep > $i + 1 ? 'bg-green-500 text-white' : ($currentStep === $i + 1 ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400') }}">
                        @if($currentStep > $i + 1)
                            <x-heroicon-s-check class="h-4 w-4" />
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span class="{{ $currentStep === $i + 1 ? 'font-semibold text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</span>
                    @if($i < 2)
                        <span class="mx-1 text-gray-300 dark:text-gray-600">/</span>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Step 1: Upload & Configure --}}
        @if($currentStep === 1)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-1">Import Lab Grades</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Upload a GitHub Classroom grading CSV. Students are matched by their GitHub username.
                </p>

                {{ $this->form }}

                @if(filled($data['courseOfferingId'] ?? null) && filled($data['assessmentId'] ?? null) && filled($data['file'] ?? null))
                    @if(($data['assessmentId'] ?? null) !== 'new' || filled($data['newAssessmentName'] ?? null))
                        <x-filament::button wire:click="parseAndPreview" class="mt-4" icon="heroicon-o-magnifying-glass">
                            Parse & Preview
                        </x-filament::button>
                    @endif
                @endif
            </div>
        @endif

        {{-- Step 2: Preview Matches --}}
        @if($currentStep === 2)
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <div class="p-5 pb-0">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Preview: Student Matching</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $totalRows }} rows found.
                        <span class="text-green-600 dark:text-green-400 font-medium">{{ $matchedCount }} matched</span>,
                        <span class="{{ $unmatchedCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }} font-medium">{{ $unmatchedCount }} unmatched</span>.
                    </p>
                </div>

                {{-- Matched students --}}
                @if(count($previewData) > 0)
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">GitHub Username</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student ID</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Score</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewData as $row)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $row['github_username'] }}</td>
                                        <td class="px-4 py-2 text-gray-800 dark:text-white/90">{{ $row['student_name'] }}</td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $row['student_id'] }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-gray-800 dark:text-white/90">{{ number_format($row['final_score'], 1) }}%</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                                {{ $row['letter_grade'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Unmatched students --}}
                @if(count($unmatchedRows) > 0)
                    <div class="mx-5 mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <h4 class="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-2">
                            Unmatched GitHub Usernames ({{ count($unmatchedRows) }})
                        </h4>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mb-2">
                            These students could not be matched. Ensure their <code>github_username</code> is set on their student record, or they are enrolled in this course offering.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-amber-200 dark:border-amber-800">
                                        <th class="px-3 py-2 text-left text-xs font-medium text-amber-600 dark:text-amber-400">GitHub Username</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-amber-600 dark:text-amber-400">Score</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-amber-600 dark:text-amber-400">Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unmatchedRows as $row)
                                        <tr class="border-b border-amber-100 dark:border-amber-900">
                                            <td class="px-3 py-1.5 font-mono text-xs text-amber-700 dark:text-amber-300">{{ $row['github_username'] }}</td>
                                            <td class="px-3 py-1.5 text-right text-amber-700 dark:text-amber-300">{{ number_format($row['final_score'], 1) }}%</td>
                                            <td class="px-3 py-1.5 text-center text-amber-700 dark:text-amber-300">{{ $row['letter_grade'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="p-5 flex gap-3">
                    <x-filament::button wire:click="confirmAndImport" icon="heroicon-o-arrow-down-tray" color="success">
                        Import {{ $matchedCount }} Matched Grades
                    </x-filament::button>
                    <x-filament::button wire:click="resetImport" color="gray" icon="heroicon-o-x-mark">
                        Cancel
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Step 3: Results --}}
        @if($currentStep === 3)
            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Import Results</h3>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $importResults['grades_imported'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Grades Imported</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $importResults['subsections_created'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Subsections Created</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $importResults['skipped'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Skipped (Unmatched)</p>
                        </div>
                    </div>
                </div>

                @if(!empty($importResults['errors']))
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-900/20">
                        <h4 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2">Errors</h4>
                        <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400 space-y-1">
                            @foreach(array_slice($importResults['errors'], 0, 20) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <x-filament::button wire:click="resetImport" icon="heroicon-o-arrow-path">
                    Import Another
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
