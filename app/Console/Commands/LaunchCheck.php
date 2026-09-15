<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Launch\EvaluateLaunchGate;
use Illuminate\Console\Command;

/** Guideline ch. 8: the launch gate, item by item, DONE / NOT DONE with the proof behind each. */
class LaunchCheck extends Command
{
    protected $signature = 'launch:check';

    protected $description = 'Evaluates the launch gate (guideline ch. 8) — exits non-zero while anything is NOT DONE';

    public function handle(EvaluateLaunchGate $gate): int
    {
        $results = $gate->handle();

        $this->table(
            ['', 'Item', 'Kind', 'Evidence'],
            array_map(fn (array $r): array => [
                $r['done'] ? '<fg=green>DONE</>' : '<fg=red>NOT DONE</>',
                $r['item']->label(),
                $r['item']->kind(),
                $r['evidence'],
            ], $results),
        );

        $open = count(array_filter($results, fn (array $r): bool => ! $r['done']));
        if ($open > 0) {
            $this->error("{$open} of ".count($results).' items are NOT DONE. The question isn\'t "can we launch anyway" — it\'s "what is the smallest launch that doesn\'t need this yet".');

            return self::FAILURE;
        }

        $this->info('Every launch gate item is DONE, with evidence.');

        return self::SUCCESS;
    }
}
