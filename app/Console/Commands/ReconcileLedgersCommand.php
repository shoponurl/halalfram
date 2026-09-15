<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Launch\ReconcileLedgers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Guideline ch. 8: reconciliation matches on production data. Scheduled daily; a mismatch alerts a person. */
class ReconcileLedgersCommand extends Command
{
    protected $signature = 'ops:reconcile';

    protected $description = 'Re-derives every cached money/stock/credit/weight balance from its ledger and reports mismatches';

    public function handle(ReconcileLedgers $reconcile): int
    {
        $started = hrtime(true);
        $mismatches = $reconcile->handle();
        $ms = intdiv(hrtime(true) - $started, 1_000_000);

        if ($mismatches === []) {
            $this->info("Every ledger reconciles ({$ms} ms).");

            return self::SUCCESS;
        }

        foreach ($mismatches as $line) {
            $this->line("<fg=red>✗</> {$line}");
        }
        Log::critical('Ledger reconciliation found mismatches', ['count' => count($mismatches), 'first' => array_slice($mismatches, 0, 5)]);

        return self::FAILURE;
    }
}
