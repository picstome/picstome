<?php

use App\Models\ContractTemplate;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Picstome templates are global records: they have no owning team and are
     * offered to every user in their own language. Team-owned templates keep
     * their `team_id` and have no locale because their content is free-form.
     */
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->foreignIdFor(Team::class)->nullable()->change();
            $table->string('locale', 5)->nullable()->after('markdown_body');
        });
    }

    /**
     * Rolling back removes the global templates because they cannot exist
     * without a nullable `team_id`; user-owned templates are untouched.
     */
    public function down(): void
    {
        ContractTemplate::query()->whereNull('team_id')->delete();

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn('locale');
            $table->foreignIdFor(Team::class)->nullable(false)->change();
        });
    }
};
