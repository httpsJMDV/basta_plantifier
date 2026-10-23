<?php

namespace App\Console\Commands;

use App\Services\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyRestore extends Command
{
    protected $signature = 'plantifier:verify-restore {connection : Configured isolated database connection} {--manifest= : Expected counts and media SHA-256 manifest} {--media-root= : Isolated restored media directory}';

    protected $description = 'Read-only checks of an isolated restore; never restores over or modifies the working database';

    public function handle(): int
    {
        $name = (string) $this->argument('connection');
        $config = config('database.connections.'.$name);
        $liveDatabase = DB::connection()->getDatabaseName();
        if (! $config || $name === config('database.default') || ($config['url'] ?? null) || strcasecmp((string) ($config['database'] ?? ''), (string) $liveDatabase) === 0 || ! preg_match('/^plantifier_(restore|admin_test)_/i', basename((string) ($config['database'] ?? '')))) {
            $this->error('Use a separate configured connection to a plantifier_restore_* database. The working database is refused.');

            return self::FAILURE;
        }
        $manifestPath = realpath((string) $this->option('manifest'));
        $root = realpath((string) $this->option('media-root'));
        if (! $manifestPath || ! $root || strtolower($root) === strtolower(realpath(base_path('..'))) || str_starts_with(strtolower($root), strtolower(realpath(base_path('..'))).DIRECTORY_SEPARATOR) || ! is_dir($root) || ! is_file($manifestPath) || filesize($manifestPath) > 2097152) {
            $this->error('Supply a readable manifest (up to 2 MB) and an isolated media directory.');

            return self::FAILURE;
        }
        $tables = ['users', 'conversations', 'chat_messages', 'plant_records', 'media'];
        try {
            $manifest = json_decode(file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
            $db = DB::connection($name);
            foreach ($tables as $table) {
                if (! isset($manifest['counts'][$table]) || ! is_int($manifest['counts'][$table]) || $manifest['counts'][$table] < 0 || $db->table($table)->count() !== $manifest['counts'][$table]) {
                    throw new \RuntimeException;
                }
            }
            foreach (['conversations', 'plant_records', 'media'] as $table) {
                if ($db->table($table.' as owned')->leftJoin('users', 'users.id', '=', 'owned.user_id')->whereNull('users.id')->exists()) {
                    throw new \RuntimeException;
                }
            }
            if ($db->table('chat_messages as m')->leftJoin('conversations as c', 'c.id', '=', 'm.conversation_id')->whereNull('c.id')->exists()) {
                throw new \RuntimeException;
            }
            if ($db->table('chat_messages as m')->join('conversations as c', 'c.id', '=', 'm.conversation_id')->leftJoin('media', 'media.id', '=', 'm.media_id')->whereNotNull('m.media_id')->where(fn ($q) => $q->whereNull('media.id')->orWhereColumn('media.user_id', '!=', 'c.user_id'))->exists()) {
                throw new \RuntimeException;
            }
            if ($db->table('plant_records as p')->leftJoin('chat_messages as m', 'm.id', '=', 'p.chat_message_id')->leftJoin('conversations as c', 'c.id', '=', 'm.conversation_id')->whereNotNull('p.chat_message_id')->where(fn ($q) => $q->whereNull('m.id')->orWhereColumn('c.user_id', '!=', 'p.user_id'))->exists()) {
                throw new \RuntimeException;
            }
            if ($db->getSchemaBuilder()->hasTable('chat_message_media') && $db->table('chat_message_media as a')->leftJoin('chat_messages as m', 'm.id', '=', 'a.chat_message_id')->leftJoin('conversations as c', 'c.id', '=', 'm.conversation_id')->leftJoin('media', 'media.id', '=', 'a.media_id')->where(fn ($q) => $q->whereNull('m.id')->orWhereNull('c.id')->orWhereNull('media.id')->orWhereColumn('media.user_id', '!=', 'c.user_id'))->exists()) {
                throw new \RuntimeException;
            }
            if ($db->getSchemaBuilder()->hasColumn('plant_records', 'media_id') && $db->table('plant_records as p')->leftJoin('media', 'media.id', '=', 'p.media_id')->whereNotNull('p.media_id')->where(fn ($q) => $q->whereNull('media.id')->orWhereColumn('media.user_id', '!=', 'p.user_id'))->exists()) {
                throw new \RuntimeException;
            }
            if ($db->getSchemaBuilder()->hasTable('note_media') && $db->table('note_media as a')->leftJoin('notes as n', 'n.id', '=', 'a.note_id')->leftJoin('media', 'media.id', '=', 'a.media_id')->where(fn ($q) => $q->whereNull('n.id')->orWhereNull('media.id')->orWhereColumn('media.user_id', '!=', 'n.user_id'))->exists()) {
                throw new \RuntimeException;
            }
            foreach ($db->table('media')->orderBy('id')->cursor() as $media) {
                $path = realpath($root.DIRECTORY_SEPARATOR.$media->path);
                $expected = $manifest['media_sha256'][(string) $media->id] ?? null;
                if (! $path || ! str_starts_with(strtolower($path), strtolower($root).DIRECTORY_SEPARATOR) || ! is_file($path) || ! is_string($expected) || ! hash_equals($expected, hash_file('sha256', $path))) {
                    throw new \RuntimeException;
                }
            }
            DB::table('backup_records')->insert(['kind' => 'restore', 'status' => 'verified', 'artifact_sha256' => hash_file('sha256', $manifestPath), 'artifact_bytes' => filesize($manifestPath), 'notes' => 'Isolated database counts, account/chat/plant ownership and media hashes checked against the supplied manifest.', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            app(SecurityAudit::class)->write(null, 'backup.restore_verified', null, ['kind' => 'restore']);
            $this->info('Isolated restore checks passed. Working records were not changed.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            DB::table('backup_records')->insert(['kind' => 'restore', 'status' => 'failed', 'notes' => 'Isolated restore checks failed. Counts, ownership, schema or media did not match the supplied manifest.', 'created_at' => now(), 'updated_at' => now()]);
            app(SecurityAudit::class)->write(null, 'backup.restore_failed', null, ['kind' => 'restore']);
            $this->error('Restore verification failed. Review the isolated database and media; no credentials or private paths were logged.');

            return self::FAILURE;
        }
    }
}
