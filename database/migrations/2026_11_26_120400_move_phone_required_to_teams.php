<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "require phone" option is shared by bookings and payment links, so it
 * lives on the team. Move the value from the booking settings first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('phone_required')->default(false)->after('bizum_instructions');
        });

        foreach (DB::table('booking_settings')->get() as $settings) {
            $team = DB::table('teams')->where('id', $settings->team_id)->first();

            if ($team === null || $team->phone_required) {
                continue;
            }

            if ($settings->phone_required) {
                DB::table('teams')->where('id', $team->id)->update(['phone_required' => true]);
            }
        }

        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn('phone_required');
        });
    }

    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->boolean('phone_required')->default(false)->after('horizon_days');
        });

        foreach (DB::table('teams')->get() as $team) {
            DB::table('booking_settings')
                ->where('team_id', $team->id)
                ->update(['phone_required' => (bool) $team->phone_required]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('phone_required');
        });
    }
};
