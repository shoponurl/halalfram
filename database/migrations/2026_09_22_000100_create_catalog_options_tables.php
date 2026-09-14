<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->string('species', 20);
            // Species with a structured cut/offal/packing option set (guideline M03). Processed/deli items stay off.
            $table->boolean('supports_custom_cuts')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('portion_type', 20)->nullable()->after('uom');
            $table->decimal('yield_pct', 5, 2)->nullable()->after('portion_type');
        });

        // One structured choice per group (guideline: never free text). Snapshotted onto order_items at checkout.
        Schema::create('cut_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('cut_style', 20);
            $table->unsignedInteger('extra_price_cents')->default(0);
            $table->unsignedInteger('extra_lead_time_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('offal_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('extra_price_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Not species-specific: vacuum pack, portion size, etc. apply across the catalog.
        Schema::create('packing_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('surcharge_cents')->default(0);
            $table->unsignedInteger('extra_lead_time_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            // Informational: the longest per-line processing time, for staff scheduling (Sprint 04 builds on this).
            $table->unsignedTinyInteger('lead_time_days')->default(0)->after('notes');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('cut_option_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('cut_option_name', 120)->nullable()->after('cut_option_id');
            $table->unsignedInteger('cut_option_price_cents')->default(0)->after('cut_option_name');

            $table->foreignId('offal_option_id')->nullable()->after('cut_option_price_cents')->constrained()->nullOnDelete();
            $table->string('offal_option_name', 120)->nullable()->after('offal_option_id');
            $table->unsignedInteger('offal_option_price_cents')->default(0)->after('offal_option_name');

            $table->foreignId('packing_option_id')->nullable()->after('offal_option_price_cents')->constrained()->nullOnDelete();
            $table->string('packing_option_name', 120)->nullable()->after('packing_option_id');
            $table->unsignedInteger('packing_option_price_cents')->default(0)->after('packing_option_name');

            $table->unsignedTinyInteger('lead_time_days')->default(0)->after('packing_option_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cut_option_id');
            $table->dropConstrainedForeignId('offal_option_id');
            $table->dropConstrainedForeignId('packing_option_id');
            $table->dropColumn(['cut_option_name', 'cut_option_price_cents', 'offal_option_name', 'offal_option_price_cents', 'packing_option_name', 'packing_option_price_cents', 'lead_time_days']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('lead_time_days');
        });

        Schema::dropIfExists('packing_options');
        Schema::dropIfExists('offal_options');
        Schema::dropIfExists('cut_options');

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['portion_type', 'yield_pct']);
        });

        Schema::dropIfExists('categories');
    }
};
