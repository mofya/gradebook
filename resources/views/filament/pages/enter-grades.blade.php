<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        @if($grades)
            <table class="min-w-full mt-4">
                <thead>
                <tr>
                    <th class="px-6 py-3">Student</th>
                    <th class="px-6 py-3">Grade</th>
                </tr>
                </thead>
                <tbody>
                @foreach($grades as $student_id => $data)
                    <tr>
                        <td class="px-6 py-4">{{ $data['student_name'] }}</td>
                        <td class="px-6 py-4">
                            <input type="number" step="0.01" wire:model="grades.{{ $student_id }}.grade" class="w-full">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <x-filament::button type="submit" class="mt-4">
                Save Grades
            </x-filament::button>
        @endif
    </form>
</x-filament-panels::page>
