<x-filament-panels::page>
    <x-filament::section heading="Recorded wastage" description="Spoilage or loss not tied to any order — total {{ $totalWastageWeightLb }} lb across the movements below.">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Date</th>
                    <th class="py-2">Lot</th>
                    <th class="py-2">Product</th>
                    <th class="py-2 text-right">Weight (lb)</th>
                    <th class="py-2">Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $movement->created_at->toDateString() }}</td>
                        <td class="py-2">{{ $movement->lot->lot_number ?? '—' }}</td>
                        <td class="py-2">{{ $movement->lot->product->name ?? '—' }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $movement->weight_lb->toDecimal() }}</td>
                        <td class="py-2">{{ $movement->note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">No wastage recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Dressing loss per animal" description="Live weight not accounted for by any lot (guideline S03 reconciliation).">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Animal</th>
                    <th class="py-2">Slaughter date</th>
                    <th class="py-2 text-right">Live (lb)</th>
                    <th class="py-2 text-right">Dressed (lb)</th>
                    <th class="py-2 text-right">Loss (lb)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($animals as $animal)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $animal->tag_id }}</td>
                        <td class="py-2">{{ $animal->slaughter_date->toDateString() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $animal->live_weight_lb->toDecimal() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $animal->dressed_weight_lb->toDecimal() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $animal->dressingLoss()->toDecimal() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">No animals with both live and dressed weight recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
