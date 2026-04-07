<x-filament-panels::page>
    @if($disputes->where('status', 'pending')->isEmpty() && $disputes->isEmpty())
        <div class="fi-section rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">No username disputes found.</p>
        </div>
    @else
        {{-- Pending disputes --}}
        @if($disputes->where('status', 'pending')->isNotEmpty())
            <div class="space-y-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Pending Disputes</h2>
                @foreach($disputes->where('status', 'pending') as $dispute)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30">Pending</span>
                                        <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $dispute->github_username }}</span>
                                    </div>

                                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                        <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-500/10">
                                            <p class="text-xs font-medium uppercase tracking-wider text-amber-600 dark:text-amber-400">Claimant</p>
                                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $dispute->claimant->first_name }} {{ $dispute->claimant->last_name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $dispute->claimant->student_id_number }} &middot; {{ $dispute->claimant->email }}</p>
                                            @if($dispute->claimant->github_username)
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Current GitHub: {{ $dispute->claimant->github_username }}</p>
                                            @endif
                                        </div>
                                        <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-500/10">
                                            <p class="text-xs font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">Current Holder</p>
                                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $dispute->currentHolder->first_name }} {{ $dispute->currentHolder->last_name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $dispute->currentHolder->student_id_number }} &middot; {{ $dispute->currentHolder->email }}</p>
                                        </div>
                                    </div>

                                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $dispute->courseOffering?->course?->code ?? '' }}
                                        &middot; Filed {{ $dispute->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <button
                                    wire:click="assignToClaimant({{ $dispute->id }})"
                                    wire:confirm="Reassign '{{ $dispute->github_username }}' to {{ $dispute->claimant->first_name }} {{ $dispute->claimant->last_name }}? This will remove it from {{ $dispute->currentHolder->first_name }} {{ $dispute->currentHolder->last_name }}."
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700"
                                >
                                    <x-heroicon-s-arrow-path class="h-3.5 w-3.5" />
                                    Assign to Claimant
                                </button>
                                <button
                                    wire:click="keepCurrentHolder({{ $dispute->id }})"
                                    wire:confirm="Reject this dispute? The username will stay with {{ $dispute->currentHolder->first_name }} {{ $dispute->currentHolder->last_name }}."
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-s-x-mark class="h-3.5 w-3.5" />
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Resolved disputes --}}
        @if($disputes->whereIn('status', ['resolved', 'rejected'])->isNotEmpty())
            <div class="mt-8 space-y-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Resolved Disputes</h2>
                @foreach($disputes->whereIn('status', ['resolved', 'rejected']) as $dispute)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden opacity-75">
                        <div class="p-5">
                            <div class="flex items-center gap-2">
                                @if($dispute->status === 'resolved')
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/30">Resolved</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30">Rejected</span>
                                @endif
                                <span class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ $dispute->github_username }}</span>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ $dispute->claimant->first_name }} {{ $dispute->claimant->last_name }} ({{ $dispute->claimant->student_id_number }})
                                claimed from {{ $dispute->currentHolder->first_name }} {{ $dispute->currentHolder->last_name }} ({{ $dispute->currentHolder->student_id_number }})
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $dispute->resolution_notes }}
                                &middot; {{ $dispute->resolvedByUser?->name ?? 'System' }}
                                &middot; {{ $dispute->resolved_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
