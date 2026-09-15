<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\QcCheck;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision (guideline ch. 7, S04): the card is never charged straight off the scale. Every item
 * must be weighed, then a QC check (checklist + temperature) gates whether the order moves on to
 * FinalizeOrder or goes back for re-cutting — a failed check never touches the card.
 */
final class RecordQcCheck extends Action
{
    /**
     * @param  array<string, bool>  $checklist
     */
    public function handle(Order $order, bool $passed, array $checklist, User $inspector, ?string $temperatureF = null, ?string $note = null): QcCheck
    {
        return $this->transaction(function () use ($order, $passed, $checklist, $inspector, $temperatureF, $note): QcCheck {
            /** @var Order $locked */
            $locked = Order::query()->with('items')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->acceptsQcCheck()) {
                throw ValidationException::withMessages(['qc' => "Order {$locked->number} isn’t ready for a QC check ({$locked->status->label()})."]);
            }
            if (! $locked->allItemsWeighed()) {
                throw ValidationException::withMessages(['qc' => 'Weigh every item before recording a QC check.']);
            }

            $check = new QcCheck;
            $check->fill(['passed' => $passed, 'checklist' => $checklist, 'temperature_f' => $temperatureF, 'note' => $note]);
            $check->order_id = $locked->id;
            $check->checked_by = $inspector->id;
            $check->save();

            $locked->status = $passed ? OrderStatus::QcPassed : OrderStatus::QcFailed;
            $locked->save();
            $order->setRawAttributes($locked->getAttributes(), true);

            return $check;
        });
    }
}
