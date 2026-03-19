<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Steps indicator --}}
        <div class="flex items-center gap-2 text-sm">
            @foreach(['Select Course', 'Upload File', 'Map Columns', 'Weights', 'Results'] as $i => $label)
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
                    @if($i < 4)
                        <span class="mx-1 text-gray-300 dark:text-gray-600">/</span>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Step 1-2: Form (Select + FileUpload) --}}
        @if($currentStep <= 2)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-1">Upload Course Data</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Select a course offering and upload a UNZA-format Excel file containing student info, CA scores, and exam scores.
                        </p>
                    </div>
                    <x-filament::button wire:click="downloadTemplate" color="gray" icon="heroicon-o-arrow-down-tray" size="sm">
                        Download Sample
                    </x-filament::button>
                </div>

                {{ $this->form }}

                @if(filled($data['courseOfferingId'] ?? null) && filled($data['file'] ?? null))
                    <x-filament::button wire:click="parseFile" class="mt-4" icon="heroicon-o-magnifying-glass">
                        Parse & Detect Columns
                    </x-filament::button>
                @endif
            </div>
        @endif

        {{-- Step 3: Column mapping --}}
        @if($currentStep === 3)
            @php
                // R1: Check if all required identity columns are mapped
                $mappedRoles = collect($columnMappings)->pluck('confirmed_role');
                $hasStudentId = $mappedRoles->contains('student_id');
                $hasName = $mappedRoles->contains('full_name') || ($mappedRoles->contains('first_name') && $mappedRoles->contains('last_name'));
                $hasEmail = $mappedRoles->contains('email');
                $allIdentityMapped = $hasStudentId && $hasName && $hasEmail;

                // R2: Detect duplicate headers
                $headerGroups = [];
                foreach ($columnMappings as $idx => $m) {
                    $h = $m['header'] ?? '';
                    if ($h !== '') {
                        $headerGroups[$h][] = $idx;
                    }
                }
                $duplicateInfo = [];
                foreach ($headerGroups as $header => $indices) {
                    if (count($indices) > 1) {
                        foreach ($indices as $pos => $idx) {
                            $duplicateInfo[$idx] = ['num' => $pos + 1, 'total' => count($indices)];
                        }
                    }
                }
            @endphp
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="p-5 pb-0">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Column Mapping</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Review the detected column roles below. Adjust any incorrect mappings before importing.
                    </p>
                    <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <p><span class="font-semibold">Detected Role:</span> system guess from the header text.</p>
                        <p><span class="font-semibold">Assigned Role:</span> what will actually be used during import.</p>
                        <p><span class="font-semibold">Max Score:</span> parsed denominator from headers like <code>Quiz 1 (30)</code> or <code>Exam/60</code>.</p>
                        <p><span class="font-semibold">Sample Values:</span> first few data values from that column.</p>
                        @if($allIdentityMapped)
                            <p class="mt-1 font-medium text-green-700 dark:text-green-300">All required identity columns are mapped.</p>
                        @else
                            @php
                                $missing = [];
                                if (!$hasStudentId) $missing[] = 'Student ID';
                                if (!$hasName) $missing[] = 'Name (Full Name or First + Last Name)';
                                if (!$hasEmail) $missing[] = 'Email';
                            @endphp
                            <p class="mt-1 font-medium text-amber-700 dark:text-amber-300">Missing required columns: {{ implode(', ', $missing) }}.</p>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto mt-4">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Column</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Detected Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Assigned Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Max Score</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Sample Values</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($columnMappings as $i => $mapping)
                                <tr class="border-b border-gray-200 dark:border-gray-700 {{ $mapping['confirmed_role'] === 'skip' ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">
                                        {{ $mapping['header'] ?: '(empty)' }}
                                        @if(isset($duplicateInfo[$i]))
                                            <span class="ml-1 inline-flex rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-medium text-orange-700 dark:bg-orange-500/10 dark:text-orange-400">
                                                Dup {{ $duplicateInfo[$i]['num'] }}/{{ $duplicateInfo[$i]['total'] }}
                                            </span>
                                        @endif
                                        @if($mapping['is_formula'] ?? false)
                                            <span class="ml-1 inline-flex rounded-full bg-cyan-100 px-1.5 py-0.5 text-[10px] font-medium text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400">
                                                Formula
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ $mapping['detected_role'] === 'skip' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' : '' }}
                                            {{ $mapping['detected_role'] === 'student_id' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : '' }}
                                            {{ in_array($mapping['detected_role'], ['first_name', 'last_name', 'full_name', 'email', 'gender', 'program']) ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400' : '' }}
                                            {{ $mapping['detected_role'] === 'ca_assessment' ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : '' }}
                                            {{ $mapping['detected_role'] === 'exam_score' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' : '' }}
                                        ">
                                            {{ $mapping['detected_role'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select
                                            wire:model.live="columnMappings.{{ $i }}.confirmed_role"
                                            class="rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                        >
                                            @foreach($this->getColumnRoleOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        @if(in_array($mapping['confirmed_role'], ['ca_assessment', 'exam_score']))
                                            <input
                                                type="number"
                                                step="0.01"
                                                wire:model.blur="columnMappings.{{ $i }}.max_score"
                                                placeholder="—"
                                                class="w-20 rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                            />
                                        @else
                                            {{ $mapping['max_score'] !== null ? $mapping['max_score'] : '—' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        @foreach(array_slice($previewRows, 0, 3) as $row)
                                            {{ $row[$mapping['index']] ?? '' }}{{ !$loop->last ? ', ' : '' }}
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-5 flex gap-3">
                    <x-filament::button wire:click="proceedToWeightConfig" icon="heroicon-o-arrow-right" color="primary">
                        Next: Configure Weights
                    </x-filament::button>
                    <x-filament::button wire:click="resetImport" color="gray" icon="heroicon-o-x-mark">
                        Cancel
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Step 4: Weight Configuration --}}
        @if($currentStep === 4)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="p-5 pb-0">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Assessment Weight Configuration</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Review the detected weights for each CA assessment. Override if needed. Weights determine how individual scores contribute to the CA total.
                    </p>
                </div>

                @if(count($weightConfig) > 0)
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Assessment</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Max Score</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Formula Weight</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">CA Points (%)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Override</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($weightConfig as $i => $wc)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $wc['header'] }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $wc['max_score'] }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                            {{ $wc['detected_weight'] !== null ? $wc['detected_weight'] . '%' : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ number_format($wc['ca_points'], 2) }}%</td>
                                        <td class="px-4 py-3">
                                            <input
                                                type="number"
                                                step="0.01"
                                                wire:model="weightConfig.{{ $i }}.override_weight"
                                                placeholder="—"
                                                class="w-24 rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No CA assessment columns detected. Weights are not applicable.</p>
                    </div>
                @endif

                @if(count($preflightInfo) > 0)
                    <div class="mx-5 mt-4 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                        <h4 class="text-sm font-semibold text-blue-700 dark:text-blue-400 mb-1">Info</h4>
                        <ul class="list-disc list-inside text-sm text-blue-600 dark:text-blue-400 space-y-0.5">
                            @foreach($preflightInfo as $info)
                                <li>{{ $info }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="p-5 flex gap-3">
                    <x-filament::button wire:click="confirmAndImport" icon="heroicon-o-arrow-down-tray" color="success">
                        Confirm & Import
                    </x-filament::button>
                    <x-filament::button wire:click="resetImport" color="gray" icon="heroicon-o-x-mark">
                        Cancel
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Step 5: Results --}}
        @if($currentStep === 5)
            <div class="space-y-4">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Import Results</h3>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $importResults['students_created'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Students Created</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $importResults['students_found'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Students Found</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $importResults['enrollments_created'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Enrollments Created</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $importResults['assessments_created'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Assessments Created</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $importResults['grades_imported'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Grades Imported</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $importResults['exam_scores_set'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Exam Scores Set</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $importResults['grades_resolved'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Grades Resolved</p>
                        </div>
                    </div>
                </div>

                @if(!empty($importResults['errors']))
                    <div class="rounded-xl bg-red-50 ring-1 ring-red-600/10 p-5 dark:bg-red-500/5 dark:ring-red-500/20">
                        <h4 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2">Errors ({{ count($importResults['errors']) }})</h4>
                        <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400 space-y-1">
                            @foreach(array_slice($importResults['errors'], 0, 20) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @if(count($importResults['errors']) > 20)
                                <li class="font-medium">...and {{ count($importResults['errors']) - 20 }} more.</li>
                            @endif
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
