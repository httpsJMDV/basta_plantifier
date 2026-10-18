<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('last_totp_counter')->nullable());
        DB::statement('CREATE TRIGGER super_admin_protected BEFORE DELETE ON super_admin '.(DB::getDriverName() === 'sqlite' ? "BEGIN SELECT RAISE(ABORT, 'Super Admin is protected; use CLI recovery'); END" : "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Super Admin is protected; use CLI recovery'"));
        $expression = DB::getDriverName() === 'sqlite'
            ? "CASE WHEN kind = 'friend' THEN MIN(user_id,target_id) || ':' || MAX(user_id,target_id) ELSE NULL END"
            : "CASE WHEN kind = 'friend' THEN CONCAT(LEAST(user_id,target_id), ':', GREATEST(user_id,target_id)) ELSE NULL END";
        Schema::table('relationships', fn (Blueprint $t) => $t->string('friendship_pair')->nullable()->virtualAs($expression)->unique());
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS super_admin_protected');
        Schema::table('relationships', function (Blueprint $t) {
            $t->dropUnique(['friendship_pair']);
            $t->dropColumn('friendship_pair');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_totp_counter'));
    }
};
