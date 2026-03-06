<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Grade Audit Trail</h3>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Recent changes to grades and enrollment records.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Date</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">User</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Action</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Record</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Changes</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                    @forelse($this->logs as $log)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $actionStyle = match($log->action) {
                                        'created' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
                                        'updated' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
                                        'deleted' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
                                        'overridden' => 'bg-purple-50 text-purple-700 ring-purple-600/20 dark:bg-purple-500/10 dark:text-purple-400 dark:ring-purple-500/30',
                                        default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $actionStyle }}">
                                    {{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                            </td>
                            <td class="px-4 py-3 max-w-md">
                                @if($log->old_values || $log->new_values)
                                    @php
                                        $oldVals = is_array($log->old_values) ? $log->old_values : [];
                                        $newVals = is_array($log->new_values) ? $log->new_values : [];
                                        $allKeys = array_unique(array_merge(array_keys($oldVals), array_keys($newVals)));
                                        // Filter out timestamps for cleaner display
                                        $displayKeys = array_filter($allKeys, fn($k) => !in_array($k, ['updated_at', 'created_at']));
                                    @endphp
                                    <div class="space-y-1">
                                        @foreach($displayKeys as $key)
                                            @php
                                                $oldVal = $oldVals[$key] ?? null;
                                                $newVal = $newVals[$key] ?? null;
                                                $label = str_replace('_', ' ', $key);
                                            @endphp
                                            <div class="text-xs">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">{{ ucfirst($label) }}:</span>
                                                @if($log->action === 'created')
                                                    <span class="text-emerald-600 dark:text-emerald-400">{{ is_numeric($newVal) ? number_format((float)$newVal, 2) : $newVal }}</span>
                                                @elseif($log->action === 'deleted')
                                                    <span class="text-red-600 dark:text-red-400 line-through">{{ is_numeric($oldVal) ? number_format((float)$oldVal, 2) : $oldVal }}</span>
                                                @else
                                                    @if($oldVal !== null)
                                                        <span class="text-red-500 dark:text-red-400 line-through">{{ is_numeric($oldVal) ? number_format((float)$oldVal, 2) : $oldVal }}</span>
                                                    @endif
                                                    <span class="text-gray-300 dark:text-gray-600 mx-0.5">&rarr;</span>
                                                    <span class="text-emerald-600 dark:text-emerald-400">{{ is_numeric($newVal) ? number_format((float)$newVal, 2) : $newVal }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if(empty($displayKeys) && !empty($allKeys))
                                            <span class="text-xs text-gray-400 dark:text-gray-500 italic">Timestamp only</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-300 dark:text-gray-600">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $log->reason ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">No audit log entries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 dark:border-white/5 px-4 py-3">
            {{ $this->logs->links() }}
        </div>
    </div>
</x-filament-panels::page>
