<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the invoicing tables: issuer settings, invoices and the
     * append-only audit trail.
     */
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();

            // Issuer fiscal data.
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('ES');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Tax and currency defaults applied to new invoices.
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone')->default('Europe/Madrid');
            $table->decimal('vat_percent', 5, 2)->default(21);
            $table->decimal('irpf_percent', 5, 2)->default(15);
            $table->boolean('apply_irpf')->default(false);
            $table->decimal('simplified_invoice_limit', 12, 2)->default(3000);

            // Numbering: one sequence per series and exercise.
            $table->string('series', 20)->default('F');
            $table->string('rectification_series', 20)->default('R');
            $table->json('invoice_sequence_state')->nullable();

            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('setup_completed_at')->nullable();

            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // full | simplified
            $table->string('invoice_type')->default('full');

            // Numbering, frozen when the invoice is issued.
            $table->string('series', 20)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();

            $table->string('currency', 3);
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->date('exchange_rate_date')->nullable();

            // draft | issued | rectified
            $table->string('status')->default('draft');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('vat_amount_eur', 12, 2)->nullable();
            $table->string('vat_exemption_reason')->nullable();
            $table->decimal('irpf_percent', 5, 2)->default(0);
            $table->decimal('irpf_amount', 12, 2)->default(0);
            $table->decimal('irpf_amount_eur', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);

            // Trazabilidad.
            $table->char('fingerprint', 64)->nullable();
            $table->char('previous_fingerprint', 64)->nullable();
            $table->string('public_token', 40)->nullable()->unique();
            $table->timestamp('public_token_revoked_at')->nullable();

            // Rectificación.
            $table->foreignId('rectifies_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('rectification_type')->nullable();
            $table->text('rectification_reason')->nullable();
            $table->decimal('rectified_amount', 12, 2)->nullable();

            // PDF almacenado.
            $table->string('pdf_path')->nullable();
            $table->unsignedBigInteger('pdf_size')->nullable();
            $table->char('pdf_hash', 64)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Snapshot: line_items, issuer, customer, terms, notes, locale.
            $table->json('invoice_data')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'series', 'year', 'number']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'issue_date']);
            $table->index(['team_id', 'issued_at']);
            $table->index('rectifies_invoice_id');
        });

        Schema::create('invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->index(['invoice_id', 'created_at']);
            $table->index(['team_id', 'event', 'created_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('tax_id')->nullable()->after('legal_name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();

            $table->unique(['team_id', 'tax_id']);
        });
    }

    /**
     * Reverse the invoicing schema.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'tax_id']);
            $table->dropColumn([
                'legal_name',
                'tax_id',
                'address',
                'city',
                'state',
                'postal_code',
                'country',
            ]);
        });

        Schema::dropIfExists('invoice_events');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_settings');
    }
};
