@php($c = \App\Support\Cents::class)
<x-filament-panels::page>
    <x-filament::section heading="Revenue by day" description="Last 30 days with a finalized (weighed) order.">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Day</th>
                    <th class="py-2 text-right">Orders</th>
                    <th class="py-2 text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byDay as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $row->day }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $row->orders }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $c::format((int) $row->revenue_cents) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-gray-500">No finalized orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Revenue by product" description="Top 20 products by revenue, all-time.">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Product</th>
                    <th class="py-2 text-right">Pieces sold</th>
                    <th class="py-2 text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byProduct as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $row->product_name }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $row->qty }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $c::format((int) $row->revenue_cents) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-gray-500">No finalized orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
