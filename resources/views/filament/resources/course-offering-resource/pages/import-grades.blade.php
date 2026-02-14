<x-filament-panels::page>
    <div class="max-w-xl">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Upload a CSV file with columns: <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-700 dark:bg-gray-700 dark:text-gray-300">student_id</code>,
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-700 dark:bg-gray-700 dark:text-gray-300">assessment_name</code>,
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-700 dark:bg-gray-700 dark:text-gray-300">raw_score</code>.
                Students must be enrolled and assessments must exist for this offering.
            </p>

            <form wire:submit="submit">
                {{ $this->form }}

                <x-filament::button type="submit" class="mt-4">
                    Import Grades
                </x-filament::button>
            </form>
        </div>
    </div>
</x-filament-panels::page>
