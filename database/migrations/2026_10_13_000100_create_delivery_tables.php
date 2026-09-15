<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owner decision (guideline ch. 7, S05): the service area is an explicit zip list, not a
        // radius or a drawn zone — accurate and explainable when checkout has to say no.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('flat_fee_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_zips', function (Blueprint $table) {
            $table->string('zip_code', 10)->primary();
            $table->foreignId('delivery_zone_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        // Owner decision (guideline ch. 7, S05): the route/window length is what bounds cold-chain
        // safety (config('catchweight.cold_chain_max_hours')), so a slot's window is capped at that.
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity')->default(1);   // deliveries a driver can make in this window
            $table->timestamps();
            $table->index(['date', 'start_time']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfilment_status', 30)->default('awaiting_fulfilment')->after('fulfilment');
            $table->foreignId('delivery_zone_id')->nullable()->after('fulfilment_status')->constrained()->nullOnDelete();
            $table->foreignId('delivery_slot_id')->nullable()->after('delivery_zone_id')->constrained()->nullOnDelete();
            $table->string('delivery_address_line1', 190)->nullable()->after('delivery_slot_id');
            $table->string('delivery_address_line2', 190)->nullable()->after('delivery_address_line1');
            $table->string('delivery_city', 80)->nullable()->after('delivery_address_line2');
            $table->string('delivery_state', 2)->nullable()->after('delivery_city');
            $table->string('delivery_zip', 10)->nullable()->after('delivery_state');
            $table->unsignedInteger('delivery_fee_cents')->default(0)->after('delivery_zip');
            $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('delivery_fee_cents');
            // Plain, not hashed: shown to the customer on their own order page for the driver to read
            // back at handoff (guideline S05 proof of delivery) — a short-lived confirmation code, not
            // a bearer credential like the order access token, so there's nothing to protect by hashing it.
            $table->string('delivery_otp', 6)->nullable()->after('delivery_attempts');
            $table->timestamp('ready_notified_at')->nullable()->after('delivery_otp');
            $table->timestamp('pickup_reminder_sent_at')->nullable()->after('ready_notified_at');
            $table->unsignedInteger('refunded_cents')->default(0)->after('written_off_cents');
            $table->foreignId('driver_id')->nullable()->after('finalized_by')->constrained('users')->nullOnDelete();
        });

        // Rule 04: every fulfilment event is append-only — a failed delivery attempt is a fact that
        // happened, never edited away, even after a re-attempt succeeds.
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('note', 500)->nullable();
            $table->string('proof_photo_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropConstrainedForeignId('delivery_slot_id');
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn([
                'fulfilment_status', 'delivery_address_line1', 'delivery_address_line2', 'delivery_city',
                'delivery_state', 'delivery_zip', 'delivery_fee_cents', 'delivery_attempts',
                'delivery_otp', 'ready_notified_at', 'pickup_reminder_sent_at', 'refunded_cents',
            ]);
        });

        Schema::dropIfExists('delivery_slots');
        Schema::dropIfExists('service_zips');
        Schema::dropIfExists('delivery_zones');
    }
};
