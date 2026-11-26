<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bizum is one team-level configuration shared by bookings and payment links.
 *
 * The booking settings owned the first copy of the phone and instructions.
 * Move any configured value to the team and remove the duplicate columns; the
 * booking pages now read the team fields through the same settings form.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('booking_settings')->get() as $settings) {
            $team = DB::table('teams')->where('id', $settings->team_id)->first();

            if ($team === null) {
                continue;
            }

            if (! $team->bizum_enabled && blank($team->bizum_phone) && blank($team->bizum_instructions)) {
                DB::table('teams')->where('id', $team->id)->update([
                    'bizum_enabled' => (bool) $settings->bizum_enabled,
                    'bizum_phone' => $settings->bizum_phone,
                    'bizum_instructions' => $settings->bizum_instructions,
                ]);
            }
        }

        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn(['bizum_enabled', 'bizum_phone', 'bizum_instructions']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->boolean('bizum_enabled')->default(false)->after('card_enabled');
            $table->string('bizum_phone', 32)->nullable()->after('bizum_enabled');
            $table->text('bizum_instructions')->nullable()->after('bizum_phone');
        });

        foreach (DB::table('teams')->get() as $team) {
            DB::table('booking_settings')
                ->where('team_id', $team->id)
                ->update([
                    'bizum_enabled' => (bool) $team->bizum_enabled,
                    'bizum_phone' => $team->bizum_phone,
                    'bizum_instructions' => $team->bizum_instructions,
                ]);
        }
    }
};
