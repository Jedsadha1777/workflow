<x-filament-panels::page>
    @if(!$this->version->canEdit())
        <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                <strong>Read Only:</strong> This version is {{ strtolower($this->version->status) }} and cannot be edited.
                @if($this->version->isPublished())
                    To make changes, clone this version to create a new draft.
                @endif
            </p>
        </div>
    @endif

    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
