<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeightCast;
use App\Enums\AnimalCostSource;
use App\Enums\Species;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The minimum traceability register (guideline M02): one animal, one tag, one slaughter date —
 * the source every lot (and from there, every order) can be traced back to for a recall.
 *
 * @property int $id
 * @property string $tag_id
 * @property Species $species
 * @property Carbon $slaughter_date
 * @property Weight|null $live_weight_lb
 * @property Weight|null $dressed_weight_lb
 * @property AnimalCostSource $cost_source
 * @property int|null $cost_cents
 * @property string|null $notes
 * @property int $recorded_by
 * @property-read Collection<int, Lot> $lots
 */
class Animal extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tag_id',
        'species',
        'slaughter_date',
        'live_weight_lb',
        'dressed_weight_lb',
        'cost_source',
        'cost_cents',
        'notes',
        'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'species' => Species::class,
            'slaughter_date' => 'date',
            'live_weight_lb' => WeightCast::class,
            'dressed_weight_lb' => WeightCast::class,
            'cost_source' => AnimalCostSource::class,
            'cost_cents' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'tag_id';
    }

    /** @return HasMany<Lot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Dressing/processing loss: live weight not accounted for by any lot (guideline S03 reconciliation). */
    public function dressingLoss(): ?Weight
    {
        if ($this->live_weight_lb === null || $this->dressed_weight_lb === null) {
            return null;
        }

        return $this->live_weight_lb->minus($this->dressed_weight_lb);
    }

    /**
     * Owner decision (guideline ch. 7, S07): own-farm animals have no purchase invoice, so their cost
     * is always a manual estimate — every report must flag it as such, never blend it in as real cost.
     */
    public function isCostEstimated(): bool
    {
        return $this->cost_source === AnimalCostSource::OwnFarm;
    }
}
