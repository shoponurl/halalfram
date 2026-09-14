<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            // Catch weight: sold per piece, priced per lb, final price set by the actual weight
            $table->string('uom', 10)->default('lb');
            $table->unsignedInteger('price_per_lb_cents');
            $table->decimal('estimated_weight_lb', 10, 3);   // per piece
            $table->decimal('tolerance_pct', 5, 2)->nullable(); // null = config('catchweight.hold_tolerance_pct')
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
