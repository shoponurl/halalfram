<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Species;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property Species $species
 * @property bool $supports_custom_cuts
 * @property int $sort_order
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, CutOption> $cutOptions
 * @property-read Collection<int, OffalOption> $offalOptions
 */
class Category extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'species',
        'supports_custom_cuts',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'species' => Species::class,
            'supports_custom_cuts' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<CutOption, $this> */
    public function cutOptions(): HasMany
    {
        return $this->hasMany(CutOption::class)->where('is_active', true)->orderBy('sort_order');
    }

    /** @return HasMany<OffalOption, $this> */
    public function offalOptions(): HasMany
    {
        return $this->hasMany(OffalOption::class)->where('is_active', true)->orderBy('sort_order');
    }
}
