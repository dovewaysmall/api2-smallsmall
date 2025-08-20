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
                if (Schema::hasColumn('repairs', 'description_of_the repair')) {
                    $table->renameColumn('description_of_the repair', 'description_of_repair');
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
                if (Schema::hasColumn('repairs', 'description_of_repair')) {
                    $table->renameColumn('description_of_repair', 'description_of_the repair');
                }
            });
        }
    }
};
