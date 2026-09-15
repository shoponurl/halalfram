<x-filament-panels::page>
    <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-950 dark:text-white">
            Day
            <input wire:model.live="date" type="date"
                   class="mt-1.5 block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
        </label>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
