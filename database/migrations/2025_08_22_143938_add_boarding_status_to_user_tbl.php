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
        if (Schema::hasTable('user_tbl')) {
            Schema::table('user_tbl', function (Blueprint $table) {
                $table->enum('boarding_status', ['not yet boarded', 'onboarded', 'offboarded'])
                      ->default('not yet boarded')
                      ->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_tbl') && Schema::hasColumn('user_tbl', 'boarding_status')) {
            Schema::table('user_tbl', function (Blueprint $table) {
                $table->dropColumn('boarding_status');
            });
        }
    }
};
