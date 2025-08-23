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
        Schema::table('user_tbl', function (Blueprint $table) {
            $table->string('landlord_bank')->nullable()->after('landlord_status');
            $table->string('landlord_acc_name')->nullable()->after('landlord_bank');
            $table->string('landlord_acc_no')->nullable()->after('landlord_acc_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tbl', function (Blueprint $table) {
            $table->dropColumn(['landlord_bank', 'landlord_acc_name', 'landlord_acc_no']);
        });
    }
};
