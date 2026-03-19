<x-filament-panels::page>
    @if($student)
        <div class="space-y-6">
            {{-- Header with New Query button --}}
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Submit queries about your grades and track their status.
                </p>
                <x-filament::button wire:click="toggleCreateForm" icon="heroicon-o-plus">
                    New Grade Query
                </x-filament::button>
            </div>

            {{-- Create Form --}}
            @if($showCreateForm)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Submit a Grade Query</h3>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Select a course and describe your concern</p>
                    </div>
                    <form wire:submit="submitQuery" class="p-5 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Course</label>
                                <select wire:model.live="selectedEnrollmentId" class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                    <option value="">Select a course...</option>
                                    @foreach($enrollments as $enrollment)
                                        <option value="{{ $enrollment->id }}">{{ $enrollment->courseOffering->course->code }} - {{ $enrollment->courseOffering->course->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedEnrollmentId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            @if($selectedEnrollmentId)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assessment <span class="font-normal text-gray-400">(optional)</span></label>
                                    <select wire:model="selectedAssessmentId" class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                        <option value="">General query</option>
                                        @foreach($this->assessmentsForEnrollment as $assessment)
                                            <option value="{{ $assessment->id }}">{{ $assessment->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                            <input type="text" wire:model="querySubject" class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Brief description of your query">
                            @error('querySubject') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                            <textarea wire:model="queryBody" rows="4" class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Describe your grade query in detail..."></textarea>
                            @error('queryBody') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-3 pt-1">
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="submitQuery">
                                <span wire:loading.remove wire:target="submitQuery">Submit Query</span>
                                <span wire:loading wire:target="submitQuery">Submitting...</span>
                            </x-filament::button>
                            <x-filament::button color="gray" wire:click="toggleCreateForm">Cancel</x-filament::button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Query List --}}
            @if($queries->isNotEmpty())
                <div class="space-y-4">
                    @foreach($queries as $query)
                        @php
                            $statusStyles = match($query->status) {
                                'open' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
                                'under_review' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
                                'resolved' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
                                'rejected' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
                                default => 'bg-gray-50 text-gray-700 ring-1 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30',
                            };
                            $statusLabel = str_replace('_', ' ', ucfirst($query->status));
                        @endphp

                        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                            {{-- Query Header --}}
                            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white truncate">{{ $query->subject }}</h3>
                                        <div class="mt-1 flex items-center gap-2 flex-wrap text-xs text-gray-400 dark:text-gray-500">
                                            <span class="font-medium text-gray-500 dark:text-gray-400">{{ $query->enrollment->courseOffering->course->code ?? 'Unknown' }}</span>
                                            @if($query->assessment)
                                                <span class="text-gray-300 dark:text-gray-600">/</span>
                                                <span>{{ $query->assessment->name }}</span>
                                            @endif
                                            <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                            <span>{{ $query->created_at->diffForHumans() }}</span>
                                            @if($query->priority !== 'normal')
                                                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                                <span class="font-medium {{ $query->priority === 'urgent' ? 'text-red-500 dark:text-red-400' : 'text-amber-500 dark:text-amber-400' }}">{{ ucfirst($query->priority) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusStyles }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                            </div>

                            {{-- Messages Thread --}}
                            <div class="p-5 space-y-3">
                                {{-- Student's Original Message --}}
                                <div class="flex gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                        {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">You</span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $query->created_at->format('M j, Y g:ia') }}</span>
                                        </div>
                                        <div class="rounded-lg bg-gray-50 px-4 py-3 ring-1 ring-gray-950/5 dark:bg-gray-800/50 dark:ring-white/5">
                                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $query->student_message }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Threaded Messages --}}
                                @if($query->messages && $query->messages->where('is_internal_note', false)->count() > 0)
                                    @foreach($query->messages->where('is_internal_note', false) as $message)
                                        @php
                                            $isStudent = $message->user && $message->user->email === $student->email;
                                        @endphp
                                        <div class="flex gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isStudent ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' }}">
                                                @if($isStudent)
                                                    {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                                @else
                                                    {{ strtoupper(substr($message->user->name ?? 'L', 0, 1)) }}
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-xs font-medium {{ $isStudent ? 'text-gray-700 dark:text-gray-300' : 'text-emerald-700 dark:text-emerald-400' }}">
                                                        {{ $isStudent ? 'You' : ($message->user->name ?? 'Lecturer') }}
                                                    </span>
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $message->created_at->format('M j, Y g:ia') }}</span>
                                                </div>
                                                <div class="rounded-lg px-4 py-3 ring-1 {{ $isStudent ? 'bg-gray-50 ring-gray-950/5 dark:bg-gray-800/50 dark:ring-white/5' : 'bg-emerald-50 ring-emerald-600/10 dark:bg-emerald-500/5 dark:ring-emerald-500/20' }}">
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $message->body }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif

                                {{-- Legacy Lecturer Response (fallback) --}}
                                @if($query->lecturer_response && (!$query->messages || $query->messages->where('is_internal_note', false)->count() === 0))
                                    <div class="flex gap-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            L
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Lecturer</span>
                                            </div>
                                            <div class="rounded-lg bg-emerald-50 px-4 py-3 ring-1 ring-emerald-600/10 dark:bg-emerald-500/5 dark:ring-emerald-500/20">
                                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $query->lecturer_response }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Reply Action --}}
                                @if($query->status !== 'resolved' && $query->status !== 'rejected')
                                    <div class="pt-1">
                                        @if($replyingToQueryId === $query->id)
                                            <form wire:submit="submitReply" class="flex gap-3">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                                    {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
                                                </div>
                                                <div class="flex-1 space-y-2">
                                                    <textarea wire:model="replyBody" rows="2" class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Type your reply..."></textarea>
                                                    @error('replyBody') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                                    <div class="flex items-center gap-2">
                                                        <x-filament::button type="submit" size="sm">Send Reply</x-filament::button>
                                                        <x-filament::button color="gray" size="sm" wire:click="$set('replyingToQueryId', null)">Cancel</x-filament::button>
                                                    </div>
                                                </div>
                                            </form>
                                        @else
                                            <div class="flex gap-3">
                                                <div class="w-8 shrink-0"></div>
                                                <button wire:click="startReply({{ $query->id }})" class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors">
                                                    <span class="text-xs">&#9999;&#65039;</span> Write a reply
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Resolved/Rejected indicator --}}
                                @if($query->status === 'resolved')
                                    <div class="flex gap-3 pt-1">
                                        <div class="w-8 shrink-0"></div>
                                        <div class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 ring-1 ring-emerald-600/10 dark:bg-emerald-500/5 dark:ring-emerald-500/20">
                                            <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Resolved{{ $query->resolved_at ? ' on ' . $query->resolved_at->format('M j, Y') : '' }}</span>
                                        </div>
                                    </div>
                                @elseif($query->status === 'rejected')
                                    <div class="flex gap-3 pt-1">
                                        <div class="w-8 shrink-0"></div>
                                        <div class="flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 ring-1 ring-red-600/10 dark:bg-red-500/5 dark:ring-red-500/20">
                                            <span class="text-xs text-red-600 dark:text-red-400 font-medium">This query was rejected</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty State --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 mb-3">
                        <span class="text-2xl">&#128172;</span>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">No grade queries yet</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">If you have concerns about a grade, click "New Grade Query" to submit one.</p>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-yellow-50 ring-1 ring-yellow-600/20 p-5 text-center dark:bg-yellow-500/10 dark:ring-yellow-500/30">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">No student record found for your account.</p>
        </div>
    @endif
</x-filament-panels::page>
