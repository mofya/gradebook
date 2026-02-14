<x-filament-panels::page>
    @php
        $caWeight = (float) $offering->ca_weight;
        $examWeight = (float) $offering->exam_weight;

        $caGroups = collect($groups)->where('type', 'ca');
        $examGroups = collect($groups)->where('type', 'exam');

        $caSubtotal = 0;
        foreach ($caGroups as $group) {
            foreach ($group['assessments'] as $a) {
                $caSubtotal += (float) ($normalizedValues[$a['id']] ?? 0);
            }
        }

        $examSubtotal = 0;
        foreach ($examGroups as $group) {
            foreach ($group['assessments'] as $a) {
                $examSubtotal += (float) ($normalizedValues[$a['id']] ?? 0);
            }
        }

        $grandTotal = ($caSubtotal * $caWeight + $examSubtotal * $examWeight) / 100;
        $caValid = abs($caSubtotal - 100) < 0.01;
        $examValid = abs($examSubtotal - 100) < 0.01;
        $totalValid = abs($grandTotal - 100) < 0.01;
    @endphp

    <div class="space-y-6">
        {{-- Course info --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <span class="font-semibold text-gray-900 dark:text-white">
                    {{ $offering->course->code ?? '' }} — {{ $offering->course->name ?? '' }}
                </span>
                <span class="text-gray-600 dark:text-gray-400">
                    CA Weight: <strong>{{ number_format($caWeight, 0) }}%</strong>
                </span>
                <span class="text-gray-600 dark:text-gray-400">
                    Exam Weight: <strong>{{ number_format($examWeight, 0) }}%</strong>
                </span>
            </div>
        </div>

        {{-- Explanation --}}
        <p class="text-xs text-gray-500 dark:text-gray-400">
            <strong>Normalized To</strong> controls how raw scores are scaled for grade calculation.
            Values within each type (CA or Exam) should sum to 100.
            For the hierarchical weight overview showing each assessment's effective contribution to the total grade,
            see the <a href="{{ \App\Filament\Resources\CourseOfferingResource::getUrl('weight-overview', ['record' => $offering]) }}" class="font-medium text-primary-600 underline hover:text-primary-500 dark:text-primary-400">Weight Overview</a> page.
        </p>

        {{-- CA Section --}}
        @if($caGroups->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <div class="border-b border-gray-200 bg-blue-50 px-4 py-3 dark:border-gray-700 dark:bg-blue-900/20">
                    <h3 class="text-sm font-semibold uppercase text-blue-700 dark:text-blue-400">
                        Continuous Assessment — {{ number_format($caWeight, 0) }}% of total
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Group</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Assessment</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Max Score</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Normalized To</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">% of CA</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($caGroups as $group)
                                @foreach($group['assessments'] as $index => $assessment)
                                    @php
                                        $normVal = (float) ($normalizedValues[$assessment['id']] ?? 0);
                                        $pctOfCa = $caSubtotal > 0 ? ($normVal / $caSubtotal) * 100 : 0;
                                        $pctOfTotal = $caSubtotal > 0 ? ($normVal / $caSubtotal) * $caWeight : 0;
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">
                                            @if($index === 0)
                                                {{ $group['name'] }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $assessment['name'] }}</td>
                                        <td class="px-4 py-2.5 text-center text-sm text-gray-600 dark:text-gray-400">{{ number_format($assessment['max_raw_score'], 0) }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   wire:model.live.debounce.300ms="normalizedValues.{{ $assessment['id'] }}"
                                                   class="w-20 rounded-lg border-gray-300 bg-white text-center text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                        </td>
                                        <td class="px-4 py-2.5 text-center text-sm text-gray-600 dark:text-gray-400">{{ number_format($pctOfCa, 1) }}%</td>
                                        <td class="px-4 py-2.5 text-center text-sm text-gray-600 dark:text-gray-400">{{ number_format($pctOfTotal, 2) }}%</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300" colspan="3">CA Subtotal</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold {{ $caValid ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ number_format($caSubtotal, 2) }}
                                    @if($caValid)
                                        <span title="Sum equals 100">&#10003;</span>
                                    @else
                                        <span title="Sum should be 100">&#9888;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">100%</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($caWeight, 2) }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Exam Section --}}
        @if($examGroups->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                <div class="border-b border-gray-200 bg-orange-50 px-4 py-3 dark:border-gray-700 dark:bg-orange-900/20">
                    <h3 class="text-sm font-semibold uppercase text-orange-700 dark:text-orange-400">
                        Examination — {{ number_format($examWeight, 0) }}% of total
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Group</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Assessment</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Max Score</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Normalized To</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">% of Exam</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($examGroups as $group)
                                @foreach($group['assessments'] as $index => $assessment)
                                    @php
                                        $normVal = (float) ($normalizedValues[$assessment['id']] ?? 0);
                                        $pctOfExam = $examSubtotal > 0 ? ($normVal / $examSubtotal) * 100 : 0;
                                        $pctOfTotal = $examSubtotal > 0 ? ($normVal / $examSubtotal) * $examWeight : 0;
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">
                                            @if($index === 0)
                                                {{ $group['name'] }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $assessment['name'] }}</td>
                                        <td class="px-4 py-2.5 text-center text-sm text-gray-600 dark:text-gray-400">{{ number_format($assessment['max_raw_score'], 0) }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   wire:model.live.debounce.300ms="normalizedValues.{{ $assessment['id'] }}"
                                                   class="w-20 rounded-lg border-gray-300 bg-white text-center text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                        </td>
                                        <td class="px-4 py-2.5 text-center text-sm text-gray-600 dark:text-gray-400">{{ number_format($pctOfExam, 1) }}%</td>
                                        <td class="px-4 py-2.5 text-center text-sm text-gray-600 dark:text-gray-400">{{ number_format($pctOfTotal, 2) }}%</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300" colspan="3">Exam Subtotal</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold {{ $examValid ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ number_format($examSubtotal, 2) }}
                                    @if($examValid)
                                        <span title="Sum equals 100">&#10003;</span>
                                    @else
                                        <span title="Sum should be 100">&#9888;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">100%</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">{{ number_format($examWeight, 2) }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Grand Total & Save --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Grand Total:</span>
                    <span class="ml-2 text-lg font-bold {{ $totalValid ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ number_format($grandTotal, 2) }}%
                        @if($totalValid)
                            <span title="Total equals 100%">&#10003;</span>
                        @else
                            <span title="Total should be 100%">&#9888;</span>
                        @endif
                    </span>
                </div>
                <x-filament::button wire:click="save" icon="heroicon-o-check">
                    Save Changes
                </x-filament::button>
            </div>
        </div>

        @if($caGroups->isEmpty() && $examGroups->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">No assessment groups configured for this offering yet.</p>
        @endif
    </div>
</x-filament-panels::page>
