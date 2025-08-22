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
                $table->enum('landlord_status', ['active', 'inactive', 'pending', 'suspended'])
                      ->default('active')
                      ->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_tbl') && Schema::hasColumn('user_tbl', 'landlord_status')) {
            Schema::table('user_tbl', function (Blueprint $table) {
                $table->dropColumn('landlord_status');
            });
        }
    }
};
