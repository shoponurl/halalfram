<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CutStyle;
use App\Support\Weight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A structured cut choice offered for a category (guideline M03) — never free text.
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property CutStyle $cut_style
 * @property int $extra_price_cents
 * @property int $extra_lead_time_days
 * @property string|null $raw_yield_pct
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Category $category
 */
class CutOption extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'cut_style',
        'extra_price_cents',
        'extra_lead_time_days',
        'raw_yield_pct',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cut_style' => CutStyle::class,
            'extra_price_cents' => 'integer',
            'extra_lead_time_days' => 'integer',
            'raw_yield_pct' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Raw material needed to produce a given finished weight (owner decision, guideline S03). */
    public function rawWeightFor(Weight $finishedWeight): Weight
    {
        return $this->raw_yield_pct === null ? $finishedWeight : $finishedWeight->dividedByPercent($this->raw_yield_pct);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<CutOption>  $query
     * @return Builder<CutOption>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
