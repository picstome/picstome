<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('attr_source', 100)->nullable()->after('referral_code');
            $table->string('attr_medium', 100)->nullable()->after('attr_source');
            $table->string('attr_campaign', 100)->nullable()->after('attr_medium');
            $table->string('attr_content', 100)->nullable()->after('attr_campaign');
            $table->string('attr_landing', 2048)->nullable()->after('attr_content');
            $table->string('attr_referrer', 2048)->nullable()->after('attr_landing');
            $table->timestamp('attr_at')->nullable()->after('attr_referrer');
            $table->json('attr_json')->nullable()->after('attr_at');

            $table->index(['attr_source', 'attr_medium']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['attr_source', 'attr_medium']);
            $table->dropColumn([
                'attr_source',
                'attr_medium',
                'attr_campaign',
                'attr_content',
                'attr_landing',
                'attr_referrer',
                'attr_at',
                'attr_json',
            ]);
        });
    }
};
