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
        // Only run if we need to update the schema to match production
        if (Schema::hasTable('payout')) {
            // Add new columns if they don't exist
            Schema::table('payout', function (Blueprint $table) {
                if (!Schema::hasColumn('payout', 'landlord_id')) {
                    $table->string('landlord_id', 20)->nullable()->after('id');
                }
                if (!Schema::hasColumn('payout', 'upload_receipt')) {
                    $table->string('upload_receipt', 100)->nullable()->after('amount');
                }
            });
            
            // Copy data from old columns to new ones if needed
            if (Schema::hasColumn('payout', 'payee_id') && Schema::hasColumn('payout', 'landlord_id')) {
                DB::statement('UPDATE payout SET landlord_id = payee_id WHERE landlord_id IS NULL OR landlord_id = ""');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payout')) {
            Schema::table('payout', function (Blueprint $table) {
                if (Schema::hasColumn('payout', 'landlord_id')) {
                    $table->dropColumn('landlord_id');
                }
                if (Schema::hasColumn('payout', 'upload_receipt')) {
                    $table->dropColumn('upload_receipt');
                }
            });
        }
    }
};
