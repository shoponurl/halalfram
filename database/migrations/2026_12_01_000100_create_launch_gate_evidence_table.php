<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guideline ch. 6 Sprint 09 / ch. 8 launch gate: "every item needs proof — nothing is DONE without it".
 * An append-only record of drill results (restore, recall, oversell, load) and signed-off manual items
 * (pentest closed, 10DLC approved, …) that `launch:check` reads as its evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_gate_evidence', function (Blueprint $table): void {
            $table->id();
            $table->string('item', 40);
            $table->boolean('passed');
            $table->text('evidence');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('environment', 20);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->index(['item', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launch_gate_evidence');
    }
};
