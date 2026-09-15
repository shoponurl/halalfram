@php($c = \App\Support\Cents::class)
<x-filament-panels::page>
    <x-filament::section heading="Margin per animal" description="Own-farm animals are always flagged (estimated) — an estimate is never shown as real, invoiced cost.">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                    <th class="py-2">Animal</th>
                    <th class="py-2">Slaughter date</th>
                    <th class="py-2">Cost source</th>
                    <th class="py-2 text-right">Revenue</th>
                    <th class="py-2 text-right">Cost</th>
                    <th class="py-2 text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $row['animal']->tag_id }}</td>
                        <td class="py-2">{{ $row['animal']->slaughter_date->toDateString() }}</td>
                        <td class="py-2">{{ $row['animal']->cost_source->label() }}{{ $row['animal']->isCostEstimated() ? ' (estimated)' : '' }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $c::format($row['revenue_cents']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $c::format($row['cost_cents']) }}</td>
                        <td class="py-2 text-right tabular-nums font-semibold {{ $row['margin_cents'] < 0 ? 'text-danger-600' : 'text-success-600' }}">
                            {{ $c::format($row['margin_cents']) }}{{ $row['animal']->isCostEstimated() ? ' (estimated)' : '' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-500">No animal has a recorded cost yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="No cost recorded" description="Yield and wastage still show for these — just no margin, since there's nothing to compare revenue against yet.">
        @if ($uncosted->isEmpty())
            <p class="text-sm text-gray-500">Every animal has a recorded cost.</p>
        @else
            <ul class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                @foreach ($uncosted as $animal)
                    <li class="flex items-center justify-between gap-4 py-2">
                        <span class="font-medium">{{ $animal->tag_id }}</span>
                        <span class="text-gray-500">{{ $animal->slaughter_date->toDateString() }} · {{ $animal->cost_source->label() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
