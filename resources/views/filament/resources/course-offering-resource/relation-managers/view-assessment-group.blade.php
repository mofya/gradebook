@if($assessments->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400 py-4">No assessments in this group.</p>
@else
    @php
        $typeWeight = $group->type === 'ca' ? (float) $offering->ca_weight : (float) $offering->exam_weight;
        $groupPct = (float) ($group->weight_percentage ?? 0);
    @endphp
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Weight (%)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Max Raw Score</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Normalized To</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">% of Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Subsections</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Sort Order</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assessments as $assessment)
                    @php
                        $assessmentWeight = (float) ($assessment->weight ?? 0);
                        $effectiveOfTotal = $typeWeight * $groupPct * $assessmentWeight / 10000;
                    @endphp
                    <tr class="border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $assessment->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $assessment->weight !== null ? number_format($assessment->weight, 2) . '%' : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $assessment->max_raw_score !== null ? number_format($assessment->max_raw_score, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $assessment->normalized_to !== null ? number_format($assessment->normalized_to, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-center font-medium text-gray-800 dark:text-white/90">{{ number_format($effectiveOfTotal, 2) }}%</td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">
                            @if($assessment->has_subsections)
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">Yes</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $assessment->sort_order }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
