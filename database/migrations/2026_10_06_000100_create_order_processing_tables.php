<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Capacity is measured in butcher-minutes (owner decision S04), one row per production date.
        Schema::create('production_days', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('capacity_minutes')->nullable();   // null = config('catchweight.daily_capacity_minutes')
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            // The production day this order is queued for (owner decision S04: capacity + cutoff).
            $table->date('scheduled_date')->nullable()->after('lead_time_days');
        });

        Schema::table('cut_options', function (Blueprint $table) {
            // Null = config('catchweight.default_processing_minutes') per piece. Owner decision S04.
            $table->unsignedInteger('estimated_minutes')->nullable()->after('raw_yield_pct');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Snapshot, same reasoning as the cut/offal/packing price snapshots: a later change to the
            // cut option's estimated_minutes must never rewrite a day that's already scheduled.
            $table->unsignedInteger('estimated_minutes')->nullable()->after('lead_time_days');
        });

        // Rule 04: every QC decision is an event. A failed check never overwrites a previous one —
        // the order goes back for re-cutting and re-weighing, then gets a fresh check.
        Schema::create('qc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->boolean('passed');
            $table->json('checklist');                 // {"item label": true|false, ...}
            $table->decimal('temperature_f', 5, 1)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_checks');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('estimated_minutes');
        });

        Schema::table('cut_options', function (Blueprint $table) {
            $table->dropColumn('estimated_minutes');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('scheduled_date');
        });

        Schema::dropIfExists('production_days');
    }
};
