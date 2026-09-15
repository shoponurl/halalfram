<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Inventory\ReleaseStock;
use App\Actions\Launch\ReconcileLedgers;
use App\Enums\LaunchGateItem;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StorageLocation;
use App\Models\LaunchGateEvidence;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Weight;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Guideline ch. 8: "load test passes — no oversell under concurrency". Stocks a throwaway product with
 * exactly N orders' worth, then fires more simultaneous checkouts than that from separate PHP
 * processes (real concurrent DB connections, not one test transaction). Passes only if exactly N
 * succeed, every other one is refused cleanly as out of stock, and the lot's ledger still reconciles.
 */
class OversellDrill extends Command
{
    protected $signature = 'ops:oversell-drill {--workers=25 : simultaneous checkouts} {--stock-orders=10 : how many orders the lot can fill}';

    protected $description = 'Races concurrent checkouts against limited stock and records whether anything oversold (never on production)';

    public function handle(ReceiveStock $receiveStock, ReleaseStock $releaseStock, ReconcileLedgers $reconcile): int
    {
        if (app()->isProduction()) {
            $this->error('This drill creates and cancels real orders — run it on staging, never production.');

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $stockOrders = max(1, (int) $this->option('stock-orders'));

        $staff = User::query()->where('is_active', true)->role([Role::Owner->value, Role::Butcher->value])->first();
        if ($staff === null) {
            $this->error('Needs an active Owner or Butcher account to receive the drill stock.');

            return self::FAILURE;
        }

        $product = new Product;
        $product->name = 'Oversell drill '.now()->format('Y-m-d H:i:s');
        $product->slug = 'oversell-drill-'.Str::lower(Str::random(10));
        $product->price_per_lb_cents = 100;
        $product->estimated_weight_lb = Weight::pounds('1.000');
        $product->is_active = true;
        $product->save();

        $lot = $receiveStock->handle($product, null, StorageLocation::cases()[0], Carbon::today(), Carbon::today()->addDays(5), Weight::pounds($stockOrders.'.000'), $staff);

        $this->info("Racing {$workers} simultaneous checkouts for {$stockOrders} orders' worth of stock…");
        $started = hrtime(true);
        $startAt = (int) (microtime(true) * 1000) + 3000;   // give every process time to boot, then release them together

        $results = Process::pool(function (Pool $pool) use ($workers, $product, $startAt): void {
            for ($i = 1; $i <= $workers; $i++) {
                $pool->as("w{$i}")->path(base_path())->timeout(120)
                    ->command([PHP_BINARY, 'artisan', 'ops:oversell-drill-worker', (string) $product->id, (string) $i, (string) $startAt]);
            }
        })->start()->wait();

        $ms = intdiv(hrtime(true) - $started, 1_000_000);

        $placed = 0;
        $refused = 0;
        $errors = [];
        foreach ($results->collect() as $name => $result) {
            $payload = json_decode(trim($result->output()), true);
            match (true) {
                is_array($payload) && ($payload['outcome'] ?? null) === 'placed' => $placed++,
                is_array($payload) && ($payload['outcome'] ?? null) === 'out_of_stock' => $refused++,
                default => $errors[] = "{$name}: ".mb_strimwidth(trim(is_array($payload) ? (string) ($payload['error'] ?? '') : $result->output().' '.$result->errorOutput()), 0, 300, '…'),
            };
        }

        $lot = Lot::query()->findOrFail($lot->id);
        $lotMismatches = array_values(array_filter($reconcile->handle(), fn (string $line): bool => str_contains($line, "Lot {$lot->lot_number}:")));
        $expectedPlaced = min($workers, $stockOrders);
        $passed = $placed === $expectedPlaced && $errors === [] && $lot->reserved_weight_lb->compareTo($lot->on_hand_weight_lb) <= 0 && $lotMismatches === [];

        $evidence = sprintf(
            '%d concurrent checkouts for %d units of stock: %d placed, %d refused as out of stock, %d unexpected error(s); lot %s reserved %s of %s; ledger %s; %d ms',
            $workers, $stockOrders, $placed, $refused, count($errors), $lot->lot_number, $lot->reserved_weight_lb, $lot->on_hand_weight_lb,
            $lotMismatches === [] ? 'reconciles' : 'MISMATCH', $ms,
        );
        LaunchGateEvidence::record(LaunchGateItem::OversellDrill, $passed, $evidence, $ms);

        foreach ($errors as $error) {
            $this->line("<fg=red>✗</> {$error}");
        }

        // Clean up: cancel the drill orders (releasing their stock through the normal ledger) and retire the product.
        Order::query()->whereHas('items', fn ($q) => $q->where('product_id', $product->id))->get()->each(function (Order $order) use ($releaseStock): void {
            $releaseStock->handle($order);
            $order->status = OrderStatus::Cancelled;
            $order->save();
        });
        $product->is_active = false;
        $product->save();

        $passed ? $this->info("PASS — {$evidence}") : $this->error("FAIL — {$evidence}");

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
