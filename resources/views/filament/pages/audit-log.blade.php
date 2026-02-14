<x-filament-panels::page>
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
        <div class="p-5 pb-0">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Grade Audit Trail</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Recent changes to grades and enrollment records.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm mt-4">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">User</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Action</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Record</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Changes</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->logs as $log)
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-white/90">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $log->action === 'created' ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : '' }}
                                    {{ $log->action === 'updated' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : '' }}
                                    {{ $log->action === 'deleted' ? 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400' : '' }}
                                    {{ $log->action === 'overridden' ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400' : '' }}
                                ">
                                    {{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                @if($log->old_values || $log->new_values)
                                    @if($log->old_values)
                                        <span class="text-red-600 dark:text-red-400">{{ json_encode($log->old_values) }}</span>
                                    @endif
                                    @if($log->new_values)
                                        <span class="text-green-600 dark:text-green-400">{{ json_encode($log->new_values) }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $log->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No audit log entries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $this->logs->links() }}
        </div>
    </div>
</x-filament-panels::page>
