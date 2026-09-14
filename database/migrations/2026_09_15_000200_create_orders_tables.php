<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                     // stable base for idempotency keys
            $table->string('number', 20)->nullable()->unique(); // HB-000001, set right after insert
            $table->char('public_token_hash', 64);              // sha256 of the customer's private link token
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->index();

            $table->string('customer_name', 120);
            $table->string('customer_email', 190);
            $table->string('customer_phone', 40);
            $table->string('fulfilment', 20)->default('pickup');
            $table->string('notes', 500)->nullable();

            // Two-amount model (all integer cents) + the policy snapshot the order was placed under
            $table->unsignedInteger('estimated_cents');
            $table->unsignedInteger('hold_cents');
            $table->unsignedInteger('final_cents')->nullable();
            $table->unsignedInteger('captured_cents')->default(0);
            $table->unsignedInteger('extra_charged_cents')->default(0);
            $table->unsignedInteger('balance_due_cents')->default(0);
            $table->unsignedInteger('balance_paid_cents')->default(0);
            $table->unsignedInteger('written_off_cents')->default(0);
            $table->char('currency', 3)->default('usd');
            $table->decimal('hold_tolerance_pct', 5, 2);
            $table->decimal('overage_autocharge_pct', 5, 2);
            $table->decimal('underweight_review_pct', 5, 2);

            // Stripe references (card data never touches this server)
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('balance_payment_url', 1000)->nullable();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('weights_locked_at')->nullable();
            $table->foreignId('underweight_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('underweight_approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 160);                 // snapshot
            $table->unsignedInteger('quantity');                 // pieces
            $table->unsignedInteger('price_per_lb_cents');       // snapshot
            $table->decimal('estimated_weight_lb', 10, 3);       // quantity × per-piece estimate
            $table->unsignedInteger('estimated_cents');
            $table->decimal('actual_weight_lb', 10, 3)->nullable(); // cache of the latest weight event
            $table->unsignedInteger('final_cents')->nullable();
            $table->timestamps();
        });

        // Rule 04: weight changes are events, never updates. Rows are append-only (enforced in the model).
        Schema::create('weight_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_lb', 10, 3);
            $table->string('source', 20);                 // manual | scale
            $table->string('scale_id', 60)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Every money movement, with the idempotency key that was sent to Stripe
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);                   // authorization | capture | cancel | extra_charge | balance_link | balance_paid | write_off
            $table->string('status', 20);                 // succeeded | failed | pending
            $table->unsignedInteger('amount_cents');
            $table->string('stripe_object_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'type']);
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->string('id', 100)->primary();         // evt_… — processing each event exactly once
            $table->string('type', 100);
            $table->timestamp('processed_at')->useCurrent();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 20)->nullable()->unique();
            $table->timestamp('issued_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('weight_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
