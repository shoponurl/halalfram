<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LaunchGateItem;
use App\Models\AuditLog;
use App\Models\LaunchGateEvidence;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Signs off a manual launch-gate item (a pentest report, the 10DLC approval, a staff dry run) with a
 * pointer to the proof. Automated checks and drills can't be attested — they have to actually pass.
 */
class LaunchAttest extends Command
{
    protected $signature = 'launch:attest
        {item : e.g. pentest_closed — see `php artisan launch:check`}
        {--evidence= : where the proof is, e.g. "Acme Security report 2027-01-20, retest letter 2027-02-03"}
        {--by= : email of the staff member signing off}
        {--failed : record that the item was checked and is NOT done}';

    protected $description = 'Records a signed-off manual launch gate item with its evidence';

    public function handle(): int
    {
        $item = LaunchGateItem::tryFrom((string) $this->argument('item'));
        if ($item === null) {
            $this->error('Unknown item. Manual items: '.implode(', ', array_map(fn (LaunchGateItem $i) => $i->value, array_filter(LaunchGateItem::cases(), fn (LaunchGateItem $i) => $i->kind() === 'manual'))));

            return self::INVALID;
        }
        if ($item->kind() !== 'manual') {
            $this->error("\"{$item->value}\" is {$item->kind()} — it has to actually pass, it can't be signed off.");

            return self::INVALID;
        }

        $evidence = trim((string) $this->option('evidence'));
        if (mb_strlen($evidence) < 10) {
            $this->error('--evidence is required: say where the proof lives (a report, a ticket, a screenshot path).');

            return self::INVALID;
        }

        $by = null;
        if ($this->option('by') !== null) {
            $by = User::query()->where('email', (string) $this->option('by'))->where('is_active', true)->first();
            if ($by === null) {
                $this->error('No active staff account with that email.');

                return self::INVALID;
            }
        }

        $passed = ! (bool) $this->option('failed');
        $row = LaunchGateEvidence::record($item, $passed, $evidence, by: $by);
        AuditLog::record('launch.attested', ($passed ? 'Signed off' : 'Recorded as not done').": {$item->label()}", $row, ['evidence' => $evidence], $by);

        $this->info(($passed ? 'Signed off: ' : 'Recorded as NOT DONE: ').$item->label());

        return self::SUCCESS;
    }
}
