<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // card | paypal | cash (guideline ch. 6, Sprint 06: Sprint 1 only had the Stripe card).
            $table->string('payment_method', 20)->default('card')->after('currency');
            // A rolling pointer to whatever id the gateway's *next* call needs — see Order::gatewayIntentId().
            // Stripe never needs this (stripe_payment_intent_id is stable end to end); PayPal's order id,
            // authorization id and capture id are three different ids for the same order's lifecycle.
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
            $table->unsignedInteger('tax_cents')->default(0)->after('delivery_fee_cents');
            $table->unsignedInteger('discount_cents')->default(0)->after('tax_cents');
            $table->unsignedInteger('store_credit_applied_cents')->default(0)->after('discount_cents');
            $table->boolean('marketing_sms_opt_in')->default(false)->after('customer_phone');
            $table->boolean('marketing_email_opt_in')->default(false)->after('marketing_sms_opt_in');
        });

        // Every other transaction type is gateway-driven; cash on pickup (S06) is the first one a human
        // actually records by hand, so it's the first one worth knowing who recorded it.
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('failure_message')->constrained('users')->nullOnDelete();
        });

        // Owner decision (guideline ch. 7, S06): store credit never expires and is never cashed out —
        // it's a liability tracked on the books until redeemed in the store. The balance is a cache
        // over the append-only ledger below, locked at redemption time (same pattern as Lot quantities,
        // Sprint 03) rather than summed from the ledger on every checkout.
        Schema::create('store_credit_accounts', function (Blueprint $table) {
            $table->string('customer_email', 190)->primary();
            $table->unsignedInteger('balance_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('store_credit_events', function (Blueprint $table) {
            $table->id();
            $table->string('customer_email', 190)->index();
            $table->string('type', 10);   // issue | redeem
            $table->integer('amount_cents');   // signed: +issue, -redeem
            $table->string('reason', 190)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Owner decision (guideline ch. 7, S06): a coupon discounts the final amount the customer
        // actually pays, not the pre-weighing estimate — applied in FinalizeOrder, not at checkout.
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10);   // percent | fixed
            $table->unsignedInteger('value');   // percent: 0-100; fixed: cents
            $table->unsignedInteger('min_order_cents')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete()->unique();
            $table->unsignedInteger('amount_cents');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('discount_cents')->constrained()->nullOnDelete();
        });

        // TCPA opt-in record (guideline ch. 7, S06): a phone's marketing consent and STOP status, kept
        // independently of any one order since the same number can appear on many.
        Schema::create('sms_consents', function (Blueprint $table) {
            $table->string('phone', 20)->primary();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('opted_out_at')->nullable();   // STOP received — blocks every future send, not just marketing
            $table->timestamps();
        });

        // Copy the customer sees can change without a deploy (guideline task: "notification template manager").
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);
            $table->string('channel', 10);   // sms | email
            $table->string('subject', 190)->nullable();   // email only
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['event', 'channel']);
        });

        // Dedupe ledger (guideline DoD: a retried job or a duplicate webhook sends a message only once).
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->string('channel', 10);
            $table->timestamp('sent_at')->useCurrent();
            $table->unique(['order_id', 'event', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });

        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('sms_consents');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('store_credit_events');
        Schema::dropIfExists('store_credit_accounts');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method', 'payment_reference', 'tax_cents', 'discount_cents',
                'store_credit_applied_cents', 'marketing_sms_opt_in', 'marketing_email_opt_in',
            ]);
        });
    }
};
