<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guideline ch. 6, Sprint 07: admin, reports & compliance. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table): void {
            // Owner decision (guideline ch. 7, S07): own-farm animals have no purchase invoice, so
            // their cost is always a manual estimate, flagged as such wherever margin is reported.
            $table->string('cost_source', 20)->default('purchased')->after('slaughter_date');
            $table->unsignedInteger('cost_cents')->nullable()->after('cost_source');
        });

        Schema::table('orders', function (Blueprint $table): void {
            // USDA/PA determination (guideline ch. 7, S07, recorded 2026-09-15): USDA-inspected
            // facility. Consent is captured and versioned the same way a legal notice's text changes.
            $table->timestamp('regulatory_consent_at')->nullable()->after('marketing_email_opt_in');
            $table->string('regulatory_consent_version', 20)->nullable()->after('regulatory_consent_at');
        });

        // Append-only staff-action log (guideline ch. 6, Sprint 00: "RBAC with 6 roles, audit log") —
        // distinct from the domain ledgers (stock_movements, weight_events, ...) each sprint already
        // keeps; this one is specifically "who changed what admin/staff-facing thing, and when."
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('auditable_type', 120)->nullable();
            $table->string('auditable_id', 191)->nullable();   // usually numeric, but a few models (e.g. StoreCreditAccount) key on email
            $table->string('description', 190);
            // Named "meta", not "changes" -- Eloquent's HasAttributes trait already declares a real
            // protected $changes property for its own dirty-tracking, and direct property assignment
            // resolves to that before ever reaching __set()/setAttribute(), silently no-op'ing the column.
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });

        // CCPA request queue (guideline ch. 7, S07): a customer's own request, reviewed and actioned
        // by staff — see App\Actions\Compliance\AnonymizeCustomerData.
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('customer_email', 190);
            $table->string('customer_name', 120)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('audit_logs');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['regulatory_consent_at', 'regulatory_consent_version']);
        });

        Schema::table('animals', function (Blueprint $table): void {
            $table->dropColumn(['cost_source', 'cost_cents']);
        });
    }
};
