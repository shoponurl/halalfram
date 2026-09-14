<x-filament-panels::page>
    <form wire:submit="search" class="flex flex-wrap items-end gap-3">
        <label class="flex-1 min-w-[240px]">
            <span class="text-sm font-medium text-gray-950 dark:text-white">Lot number or order number</span>
            <input wire:model="query" type="text" placeholder="LOT-000001 or HB-000001"
                   class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
        </label>
        <x-filament::button type="submit">Search</x-filament::button>
    </form>

    @if ($searched)
        @if ($lot)
            <x-filament::section heading="Lot {{ $lot->lot_number }}">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-gray-500">Product</dt><dd class="font-medium">{{ $lot->product->name }}</dd></div>
                    <div><dt class="text-gray-500">Animal</dt><dd class="font-medium">{{ $lot->animal?->tag_id ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Storage</dt><dd class="font-medium">{{ $lot->storage_location->label() }}</dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ $lot->status->label() }}</dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Orders that used this lot" description="Piece → lot → order.">
                @if ($affectedItems->isEmpty())
                    <p class="text-sm text-gray-500">No order has used this lot yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                        @foreach ($affectedItems as $item)
                            <li class="flex items-center justify-between gap-4 py-2">
                                <span>
                                    <span class="font-medium">{{ $item->order->number }}</span>
                                    — {{ $item->order->customer_name }} ({{ $item->order->customer_phone }})
                                </span>
                                <span class="text-gray-500">{{ $item->quantity }} × {{ $item->product_name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @elseif ($order)
            <x-filament::section heading="Order {{ $order->number }}" description="Order → lot → animal.">
                <ul class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                    @foreach ($order->items as $item)
                        <li class="flex items-center justify-between gap-4 py-2">
                            <span>{{ $item->quantity }} × {{ $item->product_name }}</span>
                            <span class="text-gray-500">
                                @if ($item->lot)
                                    Lot {{ $item->lot->lot_number }} · Animal {{ $item->lot->animal?->tag_id ?? '—' }}
                                @else
                                    Not tracked in inventory
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-500">No lot or order found for "{{ $query }}".</p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
