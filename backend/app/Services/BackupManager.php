<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupManager
{
    // Same allowlist as scripts/Backup-Source.ps1: no .env, private media, dumps, dependencies or caches.
    private const SOURCE = ['backend/app', 'backend/bootstrap/app.php', 'backend/bootstrap/providers.php', 'backend/config', 'backend/database/setup-mysql.sql', 'backend/database/migrations', 'backend/database/factories', 'backend/database/seeders', 'backend/resources/views', 'backend/routes', 'backend/tests', 'backend/public/index.php', 'backend/public/.htaccess', 'backend/public/favicon.svg', 'backend/artisan', 'backend/composer.json', 'backend/composer.lock', 'backend/phpunit.xml', 'backend/.env.example', 'frontend/src', 'frontend/tests', 'frontend/package.json', 'frontend/package-lock.json', 'frontend/tsconfig.json', 'frontend/vite.config.js', 'frontend/playwright.config.ts', 'scripts', '.gitignore', 'README.md', 'IMPLEMENTATION.md', 'PlantifierAI_Project_Blueprint.md'];

    // Runtime tables that are rebuilt on their own and never belong in a data backup.
    private const SKIPPED_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const RESTORE_CONNECTION = 'plantifier_restore_check';

    public function run(string $kind): array
    {
        @set_time_limit(300);
        try {
            return match ($kind) {
                'source' => $this->source(), 'database' => $this->database(), 'media' => $this->media(), 'restore' => $this->restore(),
            };
        } catch (\Throwable $error) {
            report($error);
            $notes = $kind === 'restore' ? 'The restore check could not finish. The working database was not changed.' : 'The backup could not be created. No partial file was kept.';
            DB::table('backup_records')->insert(['kind' => $kind, 'status' => 'failed', 'notes' => $notes, 'created_at' => now(), 'updated_at' => now()]);

            return ['status' => 'failed', 'message' => $notes];
        }
    }

    private function directory(): string
    {
        File::ensureDirectoryExists(config('botanical.backup_path'));

        return realpath(config('botanical.backup_path'));
    }

    private function zip(string $kind, callable $fill): string
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.'plantifier-'.$kind.'-'.now()->format('Ymd-His').'.zip';
        $zip = new \ZipArchive;
        throw_unless($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL) === true, \RuntimeException::class, 'Archive could not be created.');
        $temporary = [];
        try {
            $fill($zip, $temporary);
            throw_unless($zip->close(), \RuntimeException::class, 'Archive could not be written.');
        } catch (\Throwable $error) {
            @$zip->close();
            File::delete($path);
            throw $error;
        } finally {
            File::delete($temporary);
        }

        return $path;
    }

    private function saved(string $kind, string $path, string $notes): array
    {
        DB::table('backup_records')->insert(['kind' => $kind, 'status' => 'artifact_recorded', 'artifact_name' => basename($path), 'artifact_sha256' => hash_file('sha256', $path), 'artifact_bytes' => filesize($path), 'notes' => $notes, 'created_at' => now(), 'updated_at' => now()]);

        return ['status' => 'saved', 'message' => ucfirst($kind === 'source' ? 'source code' : $kind).' backup saved as '.basename($path).'.'];
    }

    private function source(): array
    {
        $root = realpath(base_path('..'));
        $path = $this->zip('source', function (\ZipArchive $zip) use ($root) {
            foreach (self::SOURCE as $item) {
                $target = $root.DIRECTORY_SEPARATOR.$item;
                $files = is_dir($target) ? array_map(fn ($file) => $file->getPathname(), File::allFiles($target, true)) : (is_file($target) ? [$target] : []);
                foreach ($files as $file) {
                    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
                    throw_if(preg_match('~(^|/)(\.env|auth\.json)$|\.sqlite~i', $relative), \RuntimeException::class, 'Unexpected private file in source allowlist.');
                    $zip->addFile($file, $relative);
                }
            }
        });

        return $this->saved('source', $path, 'Source-only archive. Environment files, private media and database data are excluded.');
    }

    private function database(): array
    {
        $schema = DB::getDriverName() === 'sqlite' ? null : DB::connection()->getDatabaseName();
        $tables = array_values(array_diff(Schema::getTableListing($schema, false), self::SKIPPED_TABLES));
        $counts = [];
        $path = $this->zip('database', function (\ZipArchive $zip, array &$temporary) use ($tables, &$counts) {
            foreach ($tables as $table) {
                $file = $temporary[] = tempnam(sys_get_temp_dir(), 'plt');
                $handle = fopen($file, 'wb');
                $counts[$table] = 0;
                $generated = array_flip($this->generatedColumns(DB::connection(), $table));
                foreach (DB::table($table)->cursor() as $row) {
                    fwrite($handle, json_encode(array_diff_key((array) $row, $generated), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                    $counts[$table]++;
                }
                fclose($handle);
                $zip->addFile($file, 'tables/'.$table.'.jsonl');
            }
            $zip->addFromString('manifest.json', json_encode(['format' => 1, 'created_at' => now()->toIso8601String(), 'counts' => $counts], JSON_PRETTY_PRINT));
        });

        return $this->saved('database', $path, count($tables).' tables and '.array_sum($counts).' rows saved.');
    }

    private function media(): array
    {
        $disk = Storage::disk('local');
        $hashes = [];
        $missing = 0;
        $path = $this->zip('media', function (\ZipArchive $zip) use ($disk, &$hashes, &$missing) {
            foreach ($disk->allFiles() as $file) {
                $zip->addFile($disk->path($file), 'files/'.$file);
            }
            foreach (DB::table('media')->orderBy('id')->cursor() as $media) {
                if ($disk->exists($media->path)) {
                    $hashes[$media->id] = hash_file('sha256', $disk->path($media->path));
                } else {
                    $missing++;
                }
            }
            $zip->addFromString('manifest.json', json_encode(['format' => 1, 'created_at' => now()->toIso8601String(), 'media_sha256' => (object) $hashes], JSON_PRETTY_PRINT));
        });

        return $this->saved('media', $path, count($hashes).' media files saved'.($missing ? ', '.$missing.' recorded files were already missing.' : '.'));
    }

    // Rebuilds the latest database and media backups in a throwaway SQLite database and checks them;
    // the working database is only read.
    private function restore(): array
    {
        $backups = collect(['database', 'media'])->mapWithKeys(fn ($kind) => [$kind => DB::table('backup_records')->where('kind', $kind)->where('status', 'artifact_recorded')->whereNotNull('artifact_name')->latest('id')->first()]);
        if ($backups->contains(null)) {
            return ['status' => 'failed', 'message' => 'Create a database backup and a media backup first, then run the restore check.'];
        }
        foreach ($backups as $record) {
            $file = $this->directory().DIRECTORY_SEPARATOR.$record->artifact_name;
            if (! is_file($file) || ! hash_equals($record->artifact_sha256, hash_file('sha256', $file))) {
                return $this->restoreFailed('The '.$record->kind.' backup file is missing or changed since it was saved.');
            }
        }
        $work = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plantifier_restore_'.Str::lower(Str::random(10));
        File::ensureDirectoryExists($work);
        $database = $work.DIRECTORY_SEPARATOR.'plantifier_restore_'.now()->format('Ymd_His').'.sqlite';
        touch($database);
        config(['database.connections.'.self::RESTORE_CONNECTION => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false]]);
        try {
            Artisan::call('migrate', ['--database' => self::RESTORE_CONNECTION, '--force' => true]);
            $restore = DB::connection(self::RESTORE_CONNECTION);
            $restore->getSchemaBuilder()->disableForeignKeyConstraints();
            $zip = new \ZipArchive;
            throw_unless($zip->open($this->directory().DIRECTORY_SEPARATOR.$backups['database']->artifact_name) === true, \RuntimeException::class);
            $counts = json_decode($zip->getFromName('manifest.json'), true, 32, JSON_THROW_ON_ERROR)['counts'];
            foreach ($counts as $table => $expected) {
                if (! $restore->getSchemaBuilder()->hasTable($table)) {
                    $zip->close();

                    return $this->restoreFailed('The database backup has a table ('.$table.') that the current app does not create.');
                }
                $restore->table($table)->delete();
                $stream = $zip->getStream('tables/'.$table.'.jsonl');
                $batch = [];
                // Older backups may still hold computed columns, which the database fills in itself.
                $generated = array_flip($this->generatedColumns($restore, $table));
                while (($line = fgets($stream)) !== false) {
                    $batch[] = array_diff_key(json_decode($line, true, 64, JSON_THROW_ON_ERROR), $generated);
                    if (count($batch) * count($batch[0]) >= 900) {
                        $restore->table($table)->insert($batch);
                        $batch = [];
                    }
                }
                fclose($stream);
                $batch && $restore->table($table)->insert($batch);
                if ($restore->table($table)->count() !== $expected) {
                    $zip->close();

                    return $this->restoreFailed('Row counts in the rebuilt '.$table.' table did not match the backup.');
                }
            }
            $zip->close();
            $media = new \ZipArchive;
            throw_unless($media->open($this->directory().DIRECTORY_SEPARATOR.$backups['media']->artifact_name) === true, \RuntimeException::class);
            $hashes = json_decode($media->getFromName('manifest.json'), true, 32, JSON_THROW_ON_ERROR)['media_sha256'];
            $media->extractTo($work.DIRECTORY_SEPARATOR.'media');
            $media->close();
            File::ensureDirectoryExists($work.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'files');
            $manifest = $work.DIRECTORY_SEPARATOR.'manifest.json';
            file_put_contents($manifest, json_encode(['counts' => array_intersect_key($counts, array_flip(['users', 'conversations', 'chat_messages', 'plant_records', 'media'])), 'media_sha256' => (object) $hashes]));
            $passed = Artisan::call('plantifier:verify-restore', ['connection' => self::RESTORE_CONNECTION, '--manifest' => $manifest, '--media-root' => $work.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'files']) === 0;

            return $passed
                ? ['status' => 'verified', 'message' => 'Restore check passed. The latest backups were rebuilt in a separate temporary database and matched.']
                : ['status' => 'failed', 'message' => 'Restore check failed. Counts, ownership or media files did not match the backups.'];
        } finally {
            DB::purge(self::RESTORE_CONNECTION);
            File::deleteDirectory($work);
        }
    }

    /** Virtual/stored generated columns (such as relationships.friendship_pair) cannot be inserted. */
    private function generatedColumns(Connection $connection, string $table): array
    {
        return collect($connection->getSchemaBuilder()->getColumns($table))->filter(fn ($column) => ! empty($column['generation']))->pluck('name')->all();
    }

    private function restoreFailed(string $notes): array
    {
        DB::table('backup_records')->insert(['kind' => 'restore', 'status' => 'failed', 'notes' => $notes, 'created_at' => now(), 'updated_at' => now()]);

        return ['status' => 'failed', 'message' => $notes];
    }
}
