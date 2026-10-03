<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class VaultBackup extends Command
{
    protected $signature = 'vault:backup {--keep=14 : Number of backups to retain}';

    protected $description = 'Dump the database to a timestamped .sql.gz outside the web root.';

    public function handle(): int
    {
        $connection = Config::get('database.default');
        $driver = Config::get("database.connections.{$connection}.driver");

        $backupDir = storage_path('backups');
        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Y-m-d_His');

        if ($driver === 'mysql') {
            $host = Config::get("database.connections.{$connection}.host");
            $database = Config::get("database.connections.{$connection}.database");
            $username = Config::get("database.connections.{$connection}.username");
            $password = Config::get("database.connections.{$connection}.password");
            $port = Config::get("database.connections.{$connection}.port", 3306);

            $sqlFile = "{$backupDir}/vault_{$timestamp}.sql";
            $gzFile = "{$sqlFile}.gz";

            $passwordArg = $password !== null && $password !== ''
                ? '--password='.escapeshellarg($password)
                : '';

            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s %s %s > %s',
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($username),
                $passwordArg,
                escapeshellarg($database),
                escapeshellarg($sqlFile)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0 || ! File::exists($sqlFile)) {
                $this->error('mysqldump failed. Ensure mysqldump is on PATH and credentials are correct.');

                return self::FAILURE;
            }

            exec('gzip '.escapeshellarg($sqlFile));
            $this->info("Backup written to {$gzFile}");
        } elseif ($driver === 'sqlite') {
            $databasePath = Config::get("database.connections.{$connection}.database");
            $gzFile = "{$backupDir}/vault_{$timestamp}.sqlite.gz";
            exec('gzip -c '.escapeshellarg($databasePath).' > '.escapeshellarg($gzFile));
            $this->info("Backup written to {$gzFile}");
        } else {
            $this->error("Unsupported database driver: {$driver}");

            return self::FAILURE;
        }

        $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $dir, int $keep): void
    {
        $files = collect(File::glob($dir.'/vault_*'))
            ->sortByDesc(fn ($path) => filemtime($path))
            ->values();

        foreach ($files->slice($keep) as $old) {
            File::delete($old);
        }
    }
}
