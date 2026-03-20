<x-filament-panels::page>
    {{-- Create Token --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Create API Token</h3>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Generate a new Sanctum token for authenticating API requests.</p>
        </div>

        <div class="px-5 py-4">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label for="tokenName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Token Name</label>
                    <input
                        wire:model="tokenName"
                        wire:keydown.enter="createToken"
                        id="tokenName"
                        type="text"
                        placeholder="e.g. grading-pipeline, lab-autograder"
                        class="fi-input block w-full rounded-lg border-none bg-white py-1.5 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.400)] dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] dark:disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6 ring-1 ring-gray-950/10 dark:ring-white/20 px-3"
                    />
                </div>
                <button
                    wire:click="createToken"
                    class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-btn-color-primary gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus-visible:ring-primary-400/50"
                >
                    Create Token
                </button>
            </div>

            @if($plainTextToken)
                <div class="mt-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:ring-emerald-500/30 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300 mb-1">
                                Copy this token now — it won't be shown again.
                            </p>
                            <code class="block break-all rounded bg-emerald-100 dark:bg-emerald-900/50 px-3 py-2 text-sm font-mono text-emerald-900 dark:text-emerald-200 select-all">{{ $plainTextToken }}</code>
                        </div>
                        <button
                            wire:click="dismissToken"
                            class="shrink-0 text-emerald-400 hover:text-emerald-600 dark:text-emerald-500 dark:hover:text-emerald-300"
                            title="Dismiss"
                        >
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Existing Tokens --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Active Tokens</h3>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Manage your existing API tokens. Revoking a token immediately invalidates it.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Created</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Last Used</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                    @forelse($this->tokens as $token)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-key class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $token->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $token->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @if($token->last_used_at)
                                    <span class="text-gray-500 dark:text-gray-400">{{ $token->last_used_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">Never</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    wire:click="revokeToken({{ $token->id }})"
                                    wire:confirm="Are you sure you want to revoke this token? Any integrations using it will stop working."
                                    class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                    Revoke
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                                No API tokens yet. Create one above to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
