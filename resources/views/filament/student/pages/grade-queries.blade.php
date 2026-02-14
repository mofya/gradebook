<x-filament-panels::page>
    @if($student)
        <div class="space-y-6">
            <div class="flex justify-end">
                <x-filament::button wire:click="toggleCreateForm" icon="heroicon-o-plus">
                    New Grade Query
                </x-filament::button>
            </div>

            @if($showCreateForm)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Submit a Grade Query</h3>
                    <form wire:submit="submitQuery" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Course Enrollment</label>
                            <select wire:model.live="selectedEnrollmentId" class="w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                <option value="">Select a course...</option>
                                @foreach($enrollments as $enrollment)
                                    <option value="{{ $enrollment->id }}">{{ $enrollment->courseOffering->course->code }} - {{ $enrollment->courseOffering->course->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($selectedEnrollmentId)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assessment (optional)</label>
                                <select wire:model="selectedAssessmentId" class="w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    <option value="">General query (no specific assessment)</option>
                                    @foreach($this->assessmentsForEnrollment as $assessment)
                                        <option value="{{ $assessment->id }}">{{ $assessment->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                            <input type="text" wire:model="querySubject" class="w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Brief description of your query">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                            <textarea wire:model="queryBody" rows="4" class="w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Describe your grade query in detail..."></textarea>
                        </div>

                        <div class="flex gap-2">
                            <x-filament::button type="submit">Submit Query</x-filament::button>
                            <x-filament::button color="gray" wire:click="toggleCreateForm">Cancel</x-filament::button>
                        </div>
                    </form>
                </div>
            @endif

            @if($queries->isNotEmpty())
                @foreach($queries as $query)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-white/90">
                                    {{ $query->subject }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $query->enrollment->courseOffering->course->code ?? 'Unknown Course' }}
                                    @if($query->assessment)
                                        — {{ $query->assessment->name }}
                                    @endif
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    Submitted {{ $query->created_at->diffForHumans() }}
                                    @if($query->priority !== 'normal')
                                        | Priority:
                                        <span class="font-medium
                                            {{ $query->priority === 'high' ? 'text-yellow-600 dark:text-yellow-400' : '' }}
                                            {{ $query->priority === 'urgent' ? 'text-red-600 dark:text-red-400' : '' }}
                                        ">{{ ucfirst($query->priority) }}</span>
                                    @endif
                                </p>
                            </div>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $query->status === 'open' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : '' }}
                                {{ $query->status === 'under_review' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400' : '' }}
                                {{ $query->status === 'resolved' ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : '' }}
                                {{ $query->status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400' : '' }}
                            ">
                                {{ str_replace('_', ' ', ucfirst($query->status)) }}
                            </span>
                        </div>

                        <div class="mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900">
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $query->student_message }}</p>
                        </div>

                        @if($query->messages && $query->messages->count() > 0)
                            <div class="mt-3 space-y-2">
                                @foreach($query->messages->where('is_internal_note', false) as $message)
                                    <div class="rounded-lg border p-3 {{ $message->user_id === $student->id ? 'border-gray-100 bg-gray-50 dark:border-gray-600 dark:bg-gray-900' : 'border-blue-100 bg-blue-50 dark:border-blue-800 dark:bg-blue-500/10' }}">
                                        <p class="text-xs font-medium {{ $message->user_id === $student->id ? 'text-gray-600 dark:text-gray-400' : 'text-blue-600 dark:text-blue-400' }} mb-1">
                                            {{ $message->user->name ?? 'Unknown' }} — {{ $message->created_at->diffForHumans() }}
                                        </p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $message->body }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($query->lecturer_response && (!$query->messages || $query->messages->count() === 0))
                            <div class="mt-3 rounded-lg border border-blue-100 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-500/10">
                                <p class="text-xs font-medium text-blue-600 dark:text-blue-400 mb-1">Lecturer Response</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $query->lecturer_response }}</p>
                            </div>
                        @endif

                        @if($query->status !== 'resolved' && $query->status !== 'rejected')
                            <div class="mt-3">
                                @if($replyingToQueryId === $query->id)
                                    <form wire:submit="submitReply" class="space-y-2">
                                        <textarea wire:model="replyBody" rows="2" class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Type your reply..."></textarea>
                                        <div class="flex gap-2">
                                            <x-filament::button type="submit" size="sm">Send Reply</x-filament::button>
                                            <x-filament::button color="gray" size="sm" wire:click="$set('replyingToQueryId', null)">Cancel</x-filament::button>
                                        </div>
                                    </form>
                                @else
                                    <button wire:click="startReply({{ $query->id }})" class="text-sm text-primary-600 hover:underline">Reply</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="rounded-2xl border border-gray-200 bg-white p-5 text-center dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No grade queries submitted yet. Click "New Grade Query" to start one.</p>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 text-center dark:border-yellow-700 dark:bg-yellow-900/30">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">No student record found for your account.</p>
        </div>
    @endif
</x-filament-panels::page>
