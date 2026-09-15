<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LaunchGateItem;
use App\Models\LaunchGateEvidence;
use App\Models\Lot;
use App\Models\OrderItem;
use Illuminate\Console\Command;

/**
 * Guideline ch. 8: a recall drill on production data finishes within 30 seconds. Runs the same lookup
 * as the Recall report page (lot → every order, customer and animal it touched) and times it.
 */
class RecallDrill extends Command
{
    protected $signature = 'ops:recall-drill {lot? : lot number; defaults to the most recently received lot that has orders}';

    protected $description = 'Times a full lot recall lookup and records it as launch gate evidence';

    public function handle(): int
    {
        $started = hrtime(true);

        $lotNumber = $this->argument('lot');
        $lot = is_string($lotNumber)
            ? Lot::query()->with(['product', 'animal'])->where('lot_number', $lotNumber)->first()
            : Lot::query()->with(['product', 'animal'])->whereHas('movements', fn ($q) => $q->whereNotNull('order_item_id'))->latest('id')->first();

        if ($lot === null) {
            $this->error(is_string($lotNumber) ? "No lot {$lotNumber}." : 'No lot with orders yet — receive stock and place an order first.');

            return self::FAILURE;
        }

        $items = OrderItem::query()->with('order')->where('lot_id', $lot->id)->get();
        $orders = $items->pluck('order')->unique('id');
        $customers = $orders->pluck('customer_phone')->unique()->count();

        $ms = intdiv(hrtime(true) - $started, 1_000_000);
        $limitMs = (int) config('launch.recall_drill_max_seconds') * 1000;
        $passed = $ms <= $limitMs;

        $evidence = "Lot {$lot->lot_number} ({$lot->product->name}".($lot->animal ? ", animal {$lot->animal->tag_id}" : '').") → {$orders->count()} order(s), {$customers} customer(s) to contact, found in {$ms} ms";
        LaunchGateEvidence::record(LaunchGateItem::RecallDrill, $passed, $evidence, $ms);

        $this->table(['Order', 'Status', 'Item', 'Customer phone'], $items->map(fn (OrderItem $i) => [$i->order->number, $i->order->status->value, $i->product_name, $i->order->customer_phone])->all());
        $passed ? $this->info("PASS — {$evidence}") : $this->error("FAIL (limit {$limitMs} ms) — {$evidence}");

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
