<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings, the products that can be booked and the rules that define when
 * a slot exists.
 *
 * Times that the photographer enters are local to their timezone; the
 * `bookings` and `calendar_blocks` ranges are stored in UTC so overlap checks
 * and the iCal feed never have to think about daylight saving time.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('timezone', 64)->default('Europe/Madrid');

            // How the schedule is cut into candidate start times, and the gap
            // kept free between two sessions.
            $table->unsignedSmallInteger('slot_step_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_minutes')->default(15);

            // Guards: how soon a client may book and how far ahead.
            $table->unsignedSmallInteger('min_notice_hours')->default(24);
            $table->unsignedSmallInteger('horizon_days')->default(90);

            // Null means unlimited.
            $table->unsignedSmallInteger('max_sessions_per_day')->nullable();

            // Team-wide deposit fallback; a product may override it.
            $table->string('default_deposit_type', 16)->default('none');
            $table->unsignedInteger('default_deposit_value')->default(0);

            $table->text('cancellation_policy')->nullable();

            // Manual Bizum: the photographer publishes a phone and confirms
            // each payment by hand, so no payment provider is involved.
            $table->boolean('bizum_enabled')->default(false);
            $table->string('bizum_phone', 32)->nullable();
            $table->text('bizum_instructions')->nullable();

            $table->timestamps();
        });

        Schema::create('availability_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // ISO-8601 weekday: 1 (Monday) to 7 (Sunday), as Carbon exposes.
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();

            $table->index(['team_id', 'weekday']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');

            // Amount in minor units, like payments and invoices.
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('EUR');

            // Null falls back to the team default deposit.
            $table->string('deposit_type', 16)->nullable();
            $table->unsignedInteger('deposit_value')->nullable();

            // Null falls back to the team buffer.
            $table->unsignedSmallInteger('buffer_minutes')->nullable();

            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // none | sunset | sunrise. Only affects the recommended slot.
            $table->string('light_mode', 16)->default('none');

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // The product can change or be deleted later, so the booking
            // freezes the name, duration and amounts it was created with.
            $table->string('product_name');
            $table->unsignedSmallInteger('duration_minutes');

            // pending_payment | pending_bizum | confirmed | expired |
            // cancelled | completed | no_show
            $table->string('status', 32)->default('pending_payment');

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64);

            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('deposit_amount')->default(0);
            $table->unsignedInteger('balance_amount')->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 50)->nullable();
            $table->text('notes')->nullable();

            // public | manual
            $table->string('source', 16)->default('public');
            // card | bizum | cash | transfer
            $table->string('payment_method', 16)->nullable();

            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // The policy text the client accepted is frozen with the booking:
            // the photographer may rewrite it later without changing past
            // agreements.
            $table->text('policy_snapshot')->nullable();
            $table->timestamp('policy_accepted_at')->nullable();

            // When the remaining balance should be collected.
            $table->timestamp('balance_due_at')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'starts_at']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'hold_expires_at']);
        });

        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'starts_at', 'ends_at']);
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // confirmation | reminder_48h | reminder_24h | balance_due
            $table->string('type', 32);
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(['booking_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_reminders');
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('calendar_blocks');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('products');
        Schema::dropIfExists('availability_windows');
        Schema::dropIfExists('booking_settings');
    }
};
