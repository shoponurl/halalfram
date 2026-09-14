<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A structured offal choice for a category (guideline M03) — e.g. "Keep all", "Discard all", "Separate pack".
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property int $extra_price_cents
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Category $category
 */
class OffalOption extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'extra_price_cents',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'extra_price_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<OffalOption>  $query
     * @return Builder<OffalOption>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
