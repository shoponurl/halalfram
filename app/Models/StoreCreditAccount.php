<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Owner decision (guideline ch. 7, S06): store credit is store-use-only and never expires — it's a
 * liability tracked here as a cache over the append-only App\Models\StoreCreditEvent ledger (the same
 * balance-as-cache-over-ledger pattern as Sprint 03's Lot quantities), locked with lockForUpdate() at
 * issue/redeem time so two concurrent orders can never both spend the same balance.
 *
 * @property string $customer_email
 * @property int $balance_cents
 * @property-read Collection<int, StoreCreditEvent> $events
 */
class StoreCreditAccount extends Model
{
    protected $primaryKey = 'customer_email';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['customer_email', 'balance_cents'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['balance_cents' => 'integer'];
    }

    /** @return HasMany<StoreCreditEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(StoreCreditEvent::class, 'customer_email', 'customer_email')->orderByDesc('id');
    }
}
