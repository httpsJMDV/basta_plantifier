<?php

namespace App\Console\Commands;

use App\Services\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecordBackupStatus extends Command
{
    protected $signature = 'plantifier:backup-status {kind : source, database, or media} {--file= : Existing artifact outside the project} {--failed : Record a failed attempt}';

    protected $description = 'Record an existing local backup artifact and checksum, without claiming a verified restore';

    public function handle(): int
    {
        $kind = (string) $this->argument('kind');
        if (! in_array($kind, ['source', 'database', 'media'], true)) {
            $this->error('Choose source, database, or media. Restore verification uses plantifier:verify-restore.');

            return self::FAILURE;
        }
        $record = ['kind' => $kind, 'status' => 'failed', 'notes' => 'The local operation did not produce a verified artifact.', 'created_at' => now(), 'updated_at' => now()];
        if (! $this->option('failed')) {
            $file = realpath((string) $this->option('file'));
            $project = realpath(base_path('..'));
            if (! $file || ! is_file($file) || filesize($file) === 0 || str_starts_with(strtolower($file), strtolower($project).DIRECTORY_SEPARATOR)) {
                $this->error('Use an existing, nonempty artifact outside the project. No paths or credentials are recorded.');

                return self::FAILURE;
            }
            if ($kind === 'source') {
                if (! class_exists(\ZipArchive::class)) {
                    $this->error('Enable the PHP zip extension to inspect a source archive.');

                    return self::FAILURE;
                }
                $zip = new \ZipArchive;
                if ($zip->open($file) !== true) {
                    $this->error('The source artifact must be a readable ZIP.');

                    return self::FAILURE;
                }
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = str_replace('\\', '/', $zip->getNameIndex($index));
                    if (preg_match('~(^|/)(\.env([^/]*?)?|storage/app|\.git|node_modules|vendor)(/|$)|\.(sql|sqlite|sqlite3)(\.gz)?$~i', $name)) {
                        $zip->close();
                        $this->error('The source archive contains environment, runtime, dependency, or database files. Create a source-only archive.');

                        return self::FAILURE;
                    }
                }
                $zip->close();
            }
            $record = [...$record, 'status' => 'artifact_recorded', 'artifact_sha256' => hash_file('sha256', $file), 'artifact_bytes' => filesize($file), 'notes' => 'Artifact existence, size and SHA-256 recorded. Restore has not been verified.'];
        }
        DB::table('backup_records')->insert($record);
        app(SecurityAudit::class)->write(null, 'backup.'.$record['status'], null, ['kind' => $kind]);
        $this->info('Local operation status recorded. No backup content or server paths are exposed.');

        return self::SUCCESS;
    }
}
