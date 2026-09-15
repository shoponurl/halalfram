<x-filament-panels::page>
    <x-filament::section description="Actual finished-vs-raw yield per product/cut, against the modeled yield% used to size holds. For a cut option, raw weight consumed is derived from its own yield% formula, so it will always match — this mainly flags products with no cut option (where raw = finished) against a modeled portion yield_pct, e.g. a Half/Quarter share.">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Product</th>
                    <th class="py-2">Cut option</th>
                    <th class="py-2 text-right">Orders</th>
                    <th class="py-2 text-right">Actual yield</th>
                    <th class="py-2 text-right">Modeled yield</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $row['product'] }}</td>
                        <td class="py-2">{{ $row['cut_option'] ?? '—' }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $row['actual_pct'] !== null ? "{$row['actual_pct']}%" : '—' }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $row['modeled_pct'] !== null ? "{$row['modeled_pct']}%" : 'not modeled' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">No weighed, stock-tracked orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
