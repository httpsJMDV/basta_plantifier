<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackupStatusTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plantifier_admin_backup_test_'.bin2hex(random_bytes(6));
        mkdir($this->temporary);
    }

    protected function tearDown(): void
    {
        DB::disconnect('restore_test');
        if (str_starts_with($this->temporary, sys_get_temp_dir().DIRECTORY_SEPARATOR.'plantifier_admin_backup_test_')) {
            File::deleteDirectory($this->temporary);
        }parent::tearDown();
    }

    public function test_artifact_records_never_claim_a_successful_restore(): void
    {
        $file = $this->temporary.'/backup.sql';
        file_put_contents($file, '-- isolated synthetic database backup');
        $this->artisan('plantifier:backup-status', ['kind' => 'database', '--file' => $file])->assertSuccessful();
        $row = DB::table('backup_records')->first();
        $this->assertSame('artifact_recorded', $row->status);
        $this->assertNull($row->verified_at);
        $this->assertSame(hash_file('sha256', $file), $row->artifact_sha256);
        $this->assertStringNotContainsString($file, json_encode($row));
        $this->artisan('plantifier:backup-status', ['kind' => 'media', '--failed' => true])->assertSuccessful();
        $this->assertDatabaseHas('backup_records', ['kind' => 'media', 'status' => 'failed']);
        $this->artisan('plantifier:backup-status', ['kind' => 'restore', '--file' => $file])->assertFailed();
        $this->artisan('plantifier:backup-status', ['kind' => 'database', '--file' => base_path('artisan')])->assertFailed();
    }

    private function restoredFixture(): string
    {
        $file = $this->temporary.'/plantifier_restore_qa.sqlite';
        touch($file);
        config(['database.connections.restore_test' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true]]);
        $schema = Schema::connection('restore_test');
        $schema->create('users', fn (Blueprint $t) => $t->id());
        $schema->create('conversations', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
        });
        $schema->create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->integer('conversation_id');
            $t->integer('media_id')->nullable();
        });
        $schema->create('plant_records', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('chat_message_id')->nullable();
        });
        $schema->create('media', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->string('path');
        });
        $db = DB::connection('restore_test');
        $db->table('users')->insert(['id' => 1]);
        $db->table('conversations')->insert(['id' => 1, 'user_id' => 1]);
        $db->table('chat_messages')->insert(['id' => 1, 'conversation_id' => 1, 'media_id' => 1]);
        $db->table('plant_records')->insert(['id' => 1, 'user_id' => 1, 'chat_message_id' => 1]);
        $db->table('media')->insert(['id' => 1, 'user_id' => 1, 'path' => 'image.jpg']);
        file_put_contents($this->temporary.'/image.jpg', 'QA restored media');
        $manifest = $this->temporary.'/manifest.json';
        file_put_contents($manifest, json_encode(['counts' => array_fill_keys(['users', 'conversations', 'chat_messages', 'plant_records', 'media'], 1), 'media_sha256' => ['1' => hash_file('sha256', $this->temporary.'/image.jpg')]]));

        return $manifest;
    }

    public function test_restore_verification_requires_isolation_counts_ownership_and_media_hashes(): void
    {
        $manifest = $this->restoredFixture();
        $args = ['connection' => 'restore_test', '--manifest' => $manifest, '--media-root' => $this->temporary];
        $this->artisan('plantifier:verify-restore', ['connection' => 'sqlite', '--manifest' => $manifest, '--media-root' => $this->temporary])->assertFailed();
        $this->assertDatabaseCount('backup_records', 0);
        $this->artisan('plantifier:verify-restore', $args)->assertSuccessful();
        $this->assertDatabaseHas('backup_records', ['kind' => 'restore', 'status' => 'verified']);
        $this->assertNotNull(DB::table('backup_records')->first()->verified_at);
        DB::connection('restore_test')->table('plant_records')->where('id', 1)->update(['user_id' => 999]);
        $this->artisan('plantifier:verify-restore', $args)->assertFailed();
        $this->assertDatabaseHas('backup_records', ['kind' => 'restore', 'status' => 'failed']);
        DB::connection('restore_test')->table('plant_records')->where('id', 1)->update(['user_id' => 1]);
        file_put_contents($this->temporary.'/image.jpg', 'altered');
        $this->artisan('plantifier:verify-restore', $args)->assertFailed();
    }
}
