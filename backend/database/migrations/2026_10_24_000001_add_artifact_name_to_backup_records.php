<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_records', function (Blueprint $t) {
            $t->string('artifact_name')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('backup_records', function (Blueprint $t) {
            $t->dropColumn('artifact_name');
        });
    }
};
