<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Document Info --}}
        <div>
            {{ $this->documentInfolist }}
        </div>

        {{-- Approval Setup Form --}}
        <x-filament::section>
            <x-slot name="heading">
                Setup Approval Workflow
            </x-slot>
            
            <x-slot name="description">
                Define the approval steps and where signatures/dates should be stamped on the document.
            </x-slot>

            <form wire:submit="save">
                {{ $this->form }}
            </form>
        </x-filament::section>

        {{-- Helper Info --}}
        <x-filament::section>
            <x-slot name="heading">
                How to use
            </x-slot>
            
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <ul>
                    <li><strong>Approver:</strong> Select the person who will approve at this step</li>
                    <li><strong>Signature Cell:</strong> Specify where the approver's signature should appear (e.g., Sheet1:A5)</li>
                    <li><strong>Approved Date Cell:</strong> Specify where the approval date should appear (e.g., Sheet1:B5)</li>
                    <li><strong>Order:</strong> Drag and drop to reorder approval steps</li>
                </ul>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-4">
                    After saving, you can submit the document for approval from the documents list.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>