<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guideline ch. 6, Sprint 08: nationwide cold-chain shipping — gated on the S07 USDA determination. */
return new class extends Migration
{
    public function up(): void
    {
        // Owner decision (guideline ch. 7, S08): packing rules (box/dry-ice by weight band and
        // temperature) live in the database, not code, so they can change without a deploy.
        Schema::create('packing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('temperature', 20);
            $table->decimal('min_weight_lb', 6, 2);
            $table->decimal('max_weight_lb', 6, 2);
            $table->decimal('box_length_in', 5, 2);
            $table->decimal('box_width_in', 5, 2);
            $table->decimal('box_height_in', 5, 2);
            $table->decimal('tare_weight_lb', 6, 2);   // empty box + insulation + dry ice/gel packs
            $table->decimal('dry_ice_lb', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Federal holidays and other no-ship dates (guideline ch. 6, S08: "weekend and holiday
        // blackout") on top of the standing Monday-Thursday rule (config('catchweight.ship_weekdays')).
        Schema::create('ship_blackout_dates', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 190);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            // Owner decision (guideline ch. 7, S08): frozen by default; only a product that specifically
            // needs it ships chilled instead.
            $table->boolean('requires_chilled_shipping')->default(false)->after('yield_pct');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('shipping_rate_cents')->default(0)->after('delivery_fee_cents');
            $table->unsignedInteger('shipping_actual_rate_cents')->nullable()->after('shipping_rate_cents');
            $table->string('shipping_carrier', 40)->nullable()->after('shipping_actual_rate_cents');
            $table->string('shipping_service', 60)->nullable()->after('shipping_carrier');
            $table->string('package_temperature', 20)->nullable()->after('shipping_service');
            $table->foreignId('packing_rule_id')->nullable()->constrained()->nullOnDelete()->after('package_temperature');
            $table->date('scheduled_ship_date')->nullable()->after('packing_rule_id');
            $table->string('easypost_shipment_id', 60)->nullable()->after('scheduled_ship_date');
            $table->string('tracking_number', 60)->nullable()->after('easypost_shipment_id');
            $table->string('tracking_url', 255)->nullable()->after('tracking_number');
            $table->string('shipping_label_url', 255)->nullable()->after('tracking_url');
            $table->timestamp('shipped_at')->nullable()->after('shipping_label_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('packing_rule_id');
            $table->dropColumn([
                'shipping_rate_cents',
                'shipping_actual_rate_cents',
                'shipping_carrier',
                'shipping_service',
                'package_temperature',
                'scheduled_ship_date',
                'easypost_shipment_id',
                'tracking_number',
                'tracking_url',
                'shipping_label_url',
                'shipped_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('requires_chilled_shipping');
        });

        Schema::dropIfExists('ship_blackout_dates');
        Schema::dropIfExists('packing_rules');
    }
};
