<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MySqlAdminConstraintsTest extends TestCase
{
    private ?string $isolatedDatabase = null;

    protected function tearDown(): void
    {
        if ($this->isolatedDatabase && preg_match('/^plantifier_admin_test_[a-f0-9]+$/', $this->isolatedDatabase)) {
            DB::disconnect('admin_mysql_test');
            DB::disconnect('admin_mysql_peer');
            DB::connection('admin_mysql_server')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
        }
        parent::tearDown();
    }

    public function test_overlapping_mysql_creation_attempts_and_protected_membership(): void
    {
        if (getenv('ADMIN_MYSQL_ACCEPTANCE') !== '1') {
            $this->markTestSkipped('Set ADMIN_MYSQL_ACCEPTANCE=1 to create and remove an isolated MySQL acceptance database.');
        }
        $server = config('database.connections.mysql');
        $server['database'] = null;
        $server['url'] = null;
        config(['database.connections.admin_mysql_server' => $server]);
        $this->isolatedDatabase = 'plantifier_admin_test_'.bin2hex(random_bytes(6));
        DB::connection('admin_mysql_server')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection = [...$server, 'database' => $this->isolatedDatabase];
        config(['database.default' => 'admin_mysql_test', 'database.connections.admin_mysql_test' => $connection, 'database.connections.admin_mysql_peer' => $connection]);
        Artisan::call('migrate', ['--database' => 'admin_mysql_test', '--force' => true, '--no-interaction' => true]);
        $a = User::factory()->create(['onboarded_at' => now(), 'password' => 'Botanical1234']);
        $b = User::factory()->create(['onboarded_at' => now()]);
        $first = DB::connection('admin_mysql_test')->getPdo();
        $peer = DB::connection('admin_mysql_peer')->getPdo();
        $peer->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $first->beginTransaction();
        $first->exec('INSERT INTO super_admin (id,user_id) VALUES (1,'.(int) $a->id.')');
        try {
            $peer->exec('INSERT INTO super_admin (id,user_id) VALUES (1,'.(int) $b->id.')');
            $this->fail('A concurrent admin insert was accepted.');
        } catch (\PDOException $error) {
            $this->assertSame(1205, (int) $error->errorInfo[1]);
        }
        $first->commit();
        foreach (['INSERT INTO super_admin (id,user_id) VALUES (1,'.(int) $b->id.')', 'INSERT INTO super_admin (id,user_id) VALUES (2,'.(int) $b->id.')', 'UPDATE super_admin SET user_id='.(int) $b->id.' WHERE id=1', 'DELETE FROM super_admin WHERE id=1', 'DELETE FROM users WHERE id='.(int) $a->id] as $sql) {
            try {
                $peer->exec($sql);
                $this->fail('A protected admin constraint was bypassed.');
            } catch (\PDOException $error) {
                $this->assertContains((int) $error->errorInfo[1], [1062, 1644, 1451]);
            }
        }
        $this->assertSame(1, DB::table('super_admin')->count());
        $a->forceFill(['totp_secret' => (new Google2FA)->generateSecretKey()])->save();
        $this->withoutVite();
        $this->actingAs($a)->withSession(['admin_mfa_at' => now()->timestamp])->postJson('/admin/users/'.$a->id, ['action' => 'suspend', 'reason' => 'Protected singleton test; must be rejected.', 'password' => 'Botanical1234'])->assertForbidden();
    }
}
