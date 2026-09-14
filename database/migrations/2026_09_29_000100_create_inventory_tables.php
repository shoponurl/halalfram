<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guideline M02's "minimum animal register" — the traceability source a lot points back to.
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->string('tag_id', 40)->unique();
            $table->string('species', 20);
            $table->date('slaughter_date');
            $table->decimal('live_weight_lb', 10, 3)->nullable();
            $table->decimal('dressed_weight_lb', 10, 3)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number', 20)->nullable()->unique();   // LOT-000001, set right after insert
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('storage_location', 20);
            $table->date('pack_date');
            $table->date('use_by_date');                              // FEFO ordering key
            $table->string('status', 20)->default('active');
            // Cached running totals — the source of truth is stock_movements; updated in the same
            // locked transaction as each movement (rule 06).
            $table->decimal('on_hand_weight_lb', 10, 3)->default(0);
            $table->decimal('reserved_weight_lb', 10, 3)->default(0);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'status', 'use_by_date']);   // FEFO lookup
        });

        // Rule 04: every stock change is an event, never an overwrite.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('weight_lb', 10, 3);
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['lot_id', 'type']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('lot_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            // Raw weight (post-trim-loss) reserved/consumed against the lot — snapshot, guideline S03:
            // stock tracks raw material, not just the finished weight the customer receives.
            $table->decimal('reserved_raw_weight_lb', 10, 3)->nullable()->after('lot_id');
            $table->decimal('consumed_raw_weight_lb', 10, 3)->nullable()->after('reserved_raw_weight_lb');
        });

        Schema::table('cut_options', function (Blueprint $table) {
            // Null = no modeled trim loss beyond the base product (100% yield). Owner decision S03.
            $table->decimal('raw_yield_pct', 5, 2)->nullable()->after('extra_lead_time_days');
        });
    }

    public function down(): void
    {
        Schema::table('cut_options', function (Blueprint $table) {
            $table->dropColumn('raw_yield_pct');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lot_id');
            $table->dropColumn(['reserved_raw_weight_lb', 'consumed_raw_weight_lb']);
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('animals');
    }
};
