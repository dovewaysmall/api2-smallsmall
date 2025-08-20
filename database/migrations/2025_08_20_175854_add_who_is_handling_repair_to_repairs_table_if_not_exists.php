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
                if (!Schema::hasColumn('repairs', 'who_is_handling_repair')) {
                    $table->string('who_is_handling_repair', 100)->nullable();
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
                if (Schema::hasColumn('repairs', 'who_is_handling_repair')) {
                    $table->dropColumn('who_is_handling_repair');
                }
            });
        }
    }
};
