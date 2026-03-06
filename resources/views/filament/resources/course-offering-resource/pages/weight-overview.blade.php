<x-filament-panels::page>
    @php
        $caWeight = (float) $offering->ca_weight;
        $examWeight = (float) $offering->exam_weight;

        $caGroups = collect($groups)->where('type', 'ca');
        $examGroups = collect($groups)->where('type', 'exam');

        // Validate group weight sums
        $caGroupWeightSum = $caGroups->sum('weight_percentage');
        $examGroupWeightSum = $examGroups->sum('weight_percentage');
        $caGroupsValid = abs($caGroupWeightSum - 100) < 0.01 || $caGroups->isEmpty();
        $examGroupsValid = abs($examGroupWeightSum - 100) < 0.01 || $examGroups->isEmpty();

        // Calculate grand total of all effective weights
        $grandTotal = 0;
        foreach ($caGroups as $group) {
            $groupPct = (float) $group['weight_percentage'];
            foreach ($group['assessments'] as $a) {
                $grandTotal += $caWeight * $groupPct * (float) $a['weight'] / 10000;
            }
        }
        foreach ($examGroups as $group) {
            $groupPct = (float) $group['weight_percentage'];
            foreach ($group['assessments'] as $a) {
                $grandTotal += $examWeight * $groupPct * (float) $a['weight'] / 10000;
            }
        }
        $totalValid = abs($grandTotal - 100) < 0.01;
    @endphp

    <div class="space-y-6">
        {{-- Top banner --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <span class="text-sm font-semibold text-gray-800 dark:text-white/90">
                    {{ $offering->course->code ?? '' }} &mdash; {{ $offering->course->name ?? '' }}
                </span>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                    CA: {{ number_format($caWeight, 0) }}%
                </span>
                <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700 dark:bg-orange-500/15 dark:text-orange-400">
                    Exam: {{ number_format($examWeight, 0) }}%
                </span>
            </div>
        </div>

        {{-- Explanation --}}
        <p class="text-xs text-gray-500 dark:text-gray-400">
            This page shows how each assessment contributes to the <strong class="text-gray-700 dark:text-gray-300">overall grade (100%)</strong>.
            The hierarchy is: <strong class="text-gray-700 dark:text-gray-300">CA/Exam split</strong> &rarr; <strong class="text-gray-700 dark:text-gray-300">Assessment Group weight</strong> &rarr; <strong class="text-gray-700 dark:text-gray-300">Assessment weight within group</strong>.
        </p>

        {{-- CA Section --}}
        @if($caGroups->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-200 bg-blue-50 px-5 py-3 dark:border-gray-800 dark:bg-blue-500/10">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">
                        Continuous Assessment &mdash; {{ number_format($caWeight, 0) }}% of total
                    </h3>
                    <span class="text-xs font-medium {{ $caGroupsValid ? 'text-green-600 dark:text-green-500' : 'text-amber-600 dark:text-amber-400' }}">
                        Groups sum: {{ number_format($caGroupWeightSum, 1) }}%
                        {!! $caGroupsValid ? '&#10003;' : '&#9888;' !!}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Group</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Group Weight</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Assessment</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Weight in Group</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">% of CA</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php $caEffectiveTotal = 0; @endphp
                            @foreach($caGroups as $group)
                                @php
                                    $groupPct = (float) $group['weight_percentage'];
                                    $assessmentWeightSum = collect($group['assessments'])->sum('weight');
                                    $assessmentsValid = abs($assessmentWeightSum - 100) < 0.01 || empty($group['assessments']);
                                    $groupEffectiveOfCaSum = 0;
                                    $groupEffectiveOfTotalSum = 0;
                                @endphp
                                @foreach($group['assessments'] as $index => $assessment)
                                    @php
                                        $weight = (float) $assessment['weight'];
                                        $effectiveOfCa = $groupPct * $weight / 100;
                                        $effectiveOfTotal = $caWeight * $groupPct * $weight / 10000;
                                        $groupEffectiveOfCaSum += $effectiveOfCa;
                                        $groupEffectiveOfTotalSum += $effectiveOfTotal;
                                    @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            @if($index === 0)
                                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $group['name'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                            @if($index === 0)
                                                {{ number_format($groupPct, 1) }}%
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $assessment['name'] }}</td>
                                        <td class="px-5 py-3 text-center text-sm text-gray-500 dark:text-gray-400">{{ number_format($weight, 1) }}%</td>
                                        <td class="px-5 py-3 text-center text-sm text-gray-500 dark:text-gray-400">{{ number_format($effectiveOfCa, 2) }}%</td>
                                        <td class="px-5 py-3 text-center text-sm font-medium text-gray-800 dark:text-white/90">{{ number_format($effectiveOfTotal, 2) }}%</td>
                                    </tr>
                                @endforeach
                                @php $caEffectiveTotal += $groupEffectiveOfTotalSum; @endphp
                                {{-- Group subtotal --}}
                                <tr class="bg-gray-50 dark:bg-gray-900">
                                    <td class="px-5 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400" colspan="3">
                                        {{ $group['name'] }} subtotal
                                    </td>
                                    <td class="px-5 py-2.5 text-center text-xs font-semibold {{ $assessmentsValid ? 'text-green-600 dark:text-green-500' : 'text-amber-600 dark:text-amber-400' }}">
                                        {{ number_format($assessmentWeightSum, 1) }}%
                                        {!! $assessmentsValid ? '&#10003;' : '&#9888;' !!}
                                    </td>
                                    <td class="px-5 py-2.5 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">{{ number_format($groupEffectiveOfCaSum, 2) }}%</td>
                                    <td class="px-5 py-2.5 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">{{ number_format($groupEffectiveOfTotalSum, 2) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php $caTotalValid = abs($caEffectiveTotal - $caWeight) < 0.01; @endphp
                            <tr class="border-t border-gray-200 bg-blue-50/60 dark:border-gray-800 dark:bg-blue-500/5">
                                <td class="px-5 py-3 text-sm font-semibold text-blue-700 dark:text-blue-400" colspan="5">CA Total</td>
                                <td class="px-5 py-3 text-center text-sm font-bold {{ $caTotalValid ? 'text-blue-700 dark:text-blue-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ number_format($caEffectiveTotal, 2) }}%
                                    @if(!$caTotalValid)
                                        <span class="ml-1 text-xs font-normal">(should be {{ number_format($caWeight, 0) }}%)</span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Exam Section --}}
        @if($examGroups->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-200 bg-orange-50 px-5 py-3 dark:border-gray-800 dark:bg-orange-500/10">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-400">
                        Examination &mdash; {{ number_format($examWeight, 0) }}% of total
                    </h3>
                    <span class="text-xs font-medium {{ $examGroupsValid ? 'text-green-600 dark:text-green-500' : 'text-amber-600 dark:text-amber-400' }}">
                        Groups sum: {{ number_format($examGroupWeightSum, 1) }}%
                        {!! $examGroupsValid ? '&#10003;' : '&#9888;' !!}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Group</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Group Weight</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Assessment</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Weight in Group</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">% of Exam</th>
                                <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php $examEffectiveTotal = 0; @endphp
                            @foreach($examGroups as $group)
                                @php
                                    $groupPct = (float) $group['weight_percentage'];
                                    $assessmentWeightSum = collect($group['assessments'])->sum('weight');
                                    $assessmentsValid = abs($assessmentWeightSum - 100) < 0.01 || empty($group['assessments']);
                                    $groupEffectiveOfExamSum = 0;
                                    $groupEffectiveOfTotalSum = 0;
                                @endphp
                                @foreach($group['assessments'] as $index => $assessment)
                                    @php
                                        $weight = (float) $assessment['weight'];
                                        $effectiveOfExam = $groupPct * $weight / 100;
                                        $effectiveOfTotal = $examWeight * $groupPct * $weight / 10000;
                                        $groupEffectiveOfExamSum += $effectiveOfExam;
                                        $groupEffectiveOfTotalSum += $effectiveOfTotal;
                                    @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            @if($index === 0)
                                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $group['name'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                            @if($index === 0)
                                                {{ number_format($groupPct, 1) }}%
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $assessment['name'] }}</td>
                                        <td class="px-5 py-3 text-center text-sm text-gray-500 dark:text-gray-400">{{ number_format($weight, 1) }}%</td>
                                        <td class="px-5 py-3 text-center text-sm text-gray-500 dark:text-gray-400">{{ number_format($effectiveOfExam, 2) }}%</td>
                                        <td class="px-5 py-3 text-center text-sm font-medium text-gray-800 dark:text-white/90">{{ number_format($effectiveOfTotal, 2) }}%</td>
                                    </tr>
                                @endforeach
                                @php $examEffectiveTotal += $groupEffectiveOfTotalSum; @endphp
                                {{-- Group subtotal --}}
                                <tr class="bg-gray-50 dark:bg-gray-900">
                                    <td class="px-5 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400" colspan="3">
                                        {{ $group['name'] }} subtotal
                                    </td>
                                    <td class="px-5 py-2.5 text-center text-xs font-semibold {{ $assessmentsValid ? 'text-green-600 dark:text-green-500' : 'text-amber-600 dark:text-amber-400' }}">
                                        {{ number_format($assessmentWeightSum, 1) }}%
                                        {!! $assessmentsValid ? '&#10003;' : '&#9888;' !!}
                                    </td>
                                    <td class="px-5 py-2.5 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">{{ number_format($groupEffectiveOfExamSum, 2) }}%</td>
                                    <td class="px-5 py-2.5 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">{{ number_format($groupEffectiveOfTotalSum, 2) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php $examTotalValid = abs($examEffectiveTotal - $examWeight) < 0.01; @endphp
                            <tr class="border-t border-gray-200 bg-orange-50/60 dark:border-gray-800 dark:bg-orange-500/5">
                                <td class="px-5 py-3 text-sm font-semibold text-orange-700 dark:text-orange-400" colspan="5">Exam Total</td>
                                <td class="px-5 py-3 text-center text-sm font-bold {{ $examTotalValid ? 'text-orange-700 dark:text-orange-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ number_format($examEffectiveTotal, 2) }}%
                                    @if(!$examTotalValid)
                                        <span class="ml-1 text-xs font-normal">(should be {{ number_format($examWeight, 0) }}%)</span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Grand Total --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-800 dark:text-white/90">Grand Total</span>
                <span class="text-base font-bold {{ $totalValid ? 'text-green-600 dark:text-green-500' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ number_format($grandTotal, 2) }}%
                    @if($totalValid)
                        <span class="ml-1">&#10003;</span>
                    @else
                        <span class="ml-1 text-sm font-normal">&#9888; (should be 100%)</span>
                    @endif
                </span>
            </div>
        </div>

        @if($caGroups->isEmpty() && $examGroups->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">No assessment groups configured for this offering yet.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
