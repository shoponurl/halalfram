<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

/** One competing checkout for `ops:oversell-drill`. Prints a single JSON line; not meant to be run by hand. */
class OversellDrillWorker extends Command
{
    protected $signature = 'ops:oversell-drill-worker {product} {worker} {startAt}';

    protected $description = 'Internal: one concurrent checkout for the oversell drill';

    protected $hidden = true;

    public function handle(QuoteCart $quote, PlaceOrder $placeOrder): int
    {
        if (app()->isProduction()) {
            return self::FAILURE;
        }

        $lines = [(int) $this->argument('product') => 1];
        $worker = (int) $this->argument('worker');

        // Wait for the shared start time so every worker hits checkout in the same instant
        $waitMs = (int) $this->argument('startAt') - (int) (microtime(true) * 1000);
        if ($waitMs > 0) {
            usleep($waitMs * 1000);
        }

        try {
            ['order' => $order] = $placeOrder->handle(
                lines: $lines,
                customer: ['customer_name' => "Oversell drill {$worker}", 'customer_email' => "drill-{$worker}@example.invalid", 'customer_phone' => '2155550100'],
                expectedHoldCents: $quote->handle($lines)['hold_cents'],
                paymentMethod: 'cash',
            );
            $this->line((string) json_encode(['outcome' => 'placed', 'order' => $order->number]));
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            $this->line((string) json_encode(['outcome' => str_contains($message, 'enough stock') ? 'out_of_stock' : 'rejected', 'error' => $message]));
        } catch (Throwable $e) {
            $this->line((string) json_encode(['outcome' => 'error', 'error' => $e::class.': '.$e->getMessage()]));
        }

        return self::SUCCESS;
    }
}
