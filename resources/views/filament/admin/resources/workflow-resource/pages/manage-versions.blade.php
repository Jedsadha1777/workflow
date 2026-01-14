<x-filament-panels::page>
    <div class="mb-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Department: <strong>{{ $this->record->department?->name ?? 'Not set' }}</strong>
        </p>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
