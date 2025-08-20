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
        if (Schema::hasTable('repairs')) {
            Schema::table('repairs', function (Blueprint $table) {
                // Add missing columns to match production
                if (!Schema::hasColumn('repairs', 'who_is_handling_the_repair')) {
                    $table->string('who_is_handling_the_repair', 100)->nullable();
                }
                if (!Schema::hasColumn('repairs', 'description_of_the_repair')) {
                    $table->text('description_of_the_repair');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('repairs')) {
            Schema::table('repairs', function (Blueprint $table) {
                if (Schema::hasColumn('repairs', 'who_is_handling_the_repair')) {
                    $table->dropColumn('who_is_handling_the_repair');
                }
                if (Schema::hasColumn('repairs', 'description_of_the_repair')) {
                    $table->dropColumn('description_of_the_repair');
                }
            });
        }
    }
};
