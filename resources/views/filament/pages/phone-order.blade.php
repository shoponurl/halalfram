@php($c = \App\Support\Cents::class)
<x-filament-panels::page>
    <x-filament::section heading="Find a returning customer">
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex-1 min-w-[220px]">
                <span class="text-sm font-medium text-gray-950 dark:text-white">Phone number</span>
                <input wire:model="lookupPhone" type="text" class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
            </label>
            <x-filament::button wire:click="lookupCustomer" color="gray">Look up</x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section heading="Customer">
        <div class="grid gap-4 sm:grid-cols-3">
            <label>
                <span class="text-sm font-medium text-gray-950 dark:text-white">Name</span>
                <input wire:model="customerName" type="text" required class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
            </label>
            <label>
                <span class="text-sm font-medium text-gray-950 dark:text-white">Email</span>
                <input wire:model="customerEmail" type="email" required class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
            </label>
            <label>
                <span class="text-sm font-medium text-gray-950 dark:text-white">Phone</span>
                <input wire:model="customerPhone" type="text" required class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
            </label>
        </div>
    </x-filament::section>

    <x-filament::section heading="Items" description="Pickup only for phone/counter orders — delivery needs the web checkout for the zip/slot picker.">
        <div class="space-y-3">
            @foreach ($lines as $index => $line)
                <div class="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 p-3 sm:grid-cols-6 dark:border-white/10">
                    <select wire:model="lines.{{ $index }}.product_id" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 sm:col-span-2">
                        <option value="">Select product…</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    <input wire:model="lines.{{ $index }}.quantity" type="number" min="1" placeholder="Qty" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700" />
                    <select wire:model="lines.{{ $index }}.cut_option_id" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700">
                        <option value="">No cut option</option>
                        @foreach ($cutOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model="lines.{{ $index }}.offal_option_id" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700">
                        <option value="">No offal option</option>
                        @foreach ($offalOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <select wire:model="lines.{{ $index }}.packing_option_id" class="flex-1 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700">
                            <option value="">No packing option</option>
                            @foreach ($packingOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="removeLine({{ $index }})" class="text-sm text-danger-600 hover:underline">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3 flex items-center gap-3">
            <x-filament::button wire:click="addLine" color="gray" size="sm">Add line</x-filament::button>
            <x-filament::button wire:click="requote" size="sm">Recalculate</x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section heading="Payment">
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="radio" wire:model="paymentMethod" value="cash" /> Cash at pickup</label>
            <label class="flex items-center gap-2"><input type="radio" wire:model="paymentMethod" value="card" /> Card (payment link sent)</label>
            <label class="flex items-center gap-2"><input type="radio" wire:model="paymentMethod" value="paypal" /> PayPal (payment link sent)</label>
        </div>
        <label class="mt-4 block">
            <span class="text-sm font-medium text-gray-950 dark:text-white">Notes for the butcher (optional)</span>
            <textarea wire:model="notes" rows="2" class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700"></textarea>
        </label>
    </x-filament::section>

    @if ($quoteError)
        <x-filament::section>
            <p class="text-sm font-semibold text-danger-600">{{ $quoteError }}</p>
        </x-filament::section>
    @elseif ($quote)
        <x-filament::section heading="Estimate">
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt>Estimated total</dt><dd class="tabular-nums">{{ $c::format($quote['estimated_cents']) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>Hold to place</dt><dd class="tabular-nums">{{ $c::format($quote['hold_cents']) }}</dd></div>
            </dl>
        </x-filament::section>
    @endif

    <x-filament::button wire:click="submit" size="lg">Place order</x-filament::button>
</x-filament-panels::page>
