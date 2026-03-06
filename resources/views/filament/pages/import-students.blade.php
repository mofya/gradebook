<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::button color="gray" wire:click="downloadTemplate" icon="heroicon-o-arrow-down-tray" size="sm">
            Download Template
        </x-filament::button>
    </div>

    <form wire:submit="submit">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Import Students
        </x-filament::button>
    </form>
</x-filament-panels::page>
