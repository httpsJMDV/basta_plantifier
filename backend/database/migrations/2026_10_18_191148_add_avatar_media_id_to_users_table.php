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
            $column = $table->foreignId('avatar_media_id')->nullable();
            if (Schema::hasTable('media')) {
                $column->constrained('media')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (collect(Schema::getForeignKeys('users'))->contains(fn ($key) => $key['columns'] === ['avatar_media_id'])) {
                $table->dropForeign(['avatar_media_id']);
            }
            $table->dropColumn('avatar_media_id');
        });
    }
};
