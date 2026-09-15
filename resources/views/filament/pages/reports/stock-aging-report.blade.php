<x-filament-panels::page>
    <x-filament::section heading="Active lots" description="Soonest use-by date first — a FEFO worklist.">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Lot</th>
                    <th class="py-2">Product</th>
                    <th class="py-2">Storage</th>
                    <th class="py-2 text-right">Days in storage</th>
                    <th class="py-2 text-right">Use-by</th>
                    <th class="py-2 text-right">On hand (lb)</th>
                    <th class="py-2 text-right">Available (lb)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lots as $lot)
                    @php($daysUntilUseBy = now()->startOfDay()->diffInDays($lot->use_by_date, false))
                    <tr class="border-b border-gray-100 dark:border-white/5 {{ $daysUntilUseBy < 0 ? 'bg-danger-50 dark:bg-danger-950/30' : ($daysUntilUseBy <= 2 ? 'bg-warning-50 dark:bg-warning-950/30' : '') }}">
                        <td class="py-2">{{ $lot->lot_number }}</td>
                        <td class="py-2">{{ $lot->product->name }}</td>
                        <td class="py-2">{{ $lot->storage_location->label() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $lot->pack_date->diffInDays(now()) }}</td>
                        <td class="py-2 text-right tabular-nums">
                            {{ $lot->use_by_date->toDateString() }}
                            @if ($daysUntilUseBy < 0) <span class="font-semibold text-danger-600">expired</span>
                            @elseif ($daysUntilUseBy <= 2) <span class="font-semibold text-warning-600">{{ $daysUntilUseBy }}d left</span>
                            @endif
                        </td>
                        <td class="py-2 text-right tabular-nums">{{ $lot->on_hand_weight_lb->toDecimal() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $lot->availableWeight()->toDecimal() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-gray-500">No active lots on hand.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
