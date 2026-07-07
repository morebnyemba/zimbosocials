<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Nightly database backup for shared hosting: dumps to
 * storage/app/backups/db-YYYY-MM-DD-HHMMSS.sql.gz and prunes old dumps.
 * Every wallet balance in the business lives in this database — a corrupted
 * table or a bad deploy without a dump is unrecoverable.
 *
 * MySQL uses mysqldump (password passed via MYSQL_PWD, not argv); sqlite
 * simply copies the database file.
 *
 * Usage:   php artisan db:backup [--keep=14]
 * Schedule: daily (see routes/console.php)
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=14 : Number of most recent backups to keep}';

    protected $description = 'Dump the database to storage/app/backups and prune old dumps';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0700);

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d-His');

        try {
            $path = match ($config['driver'] ?? null) {
                'mysql', 'mariadb' => $this->dumpMysql($config, $dir, $timestamp),
                'sqlite' => $this->copySqlite($config, $dir, $timestamp),
                default => throw new \RuntimeException("Unsupported driver: {$config['driver']}"),
            };
        } catch (\Throwable $e) {
            Log::error('Database backup failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
            $this->error("Backup failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $size = round(File::size($path) / 1024, 1);
        $this->info("Backup written: {$path} ({$size} KB)");

        $this->prune($dir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $config */
    private function dumpMysql(array $config, string $dir, string $timestamp): string
    {
        $path = "{$dir}/db-{$timestamp}.sql.gz";

        $command = [
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            $config['database'],
        ];

        $process = new Process($command, null, ['MYSQL_PWD' => (string) $config['password']]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump failed');
        }

        File::put($path, gzencode($process->getOutput(), 9));

        return $path;
    }

    /** @param array<string, mixed> $config */
    private function copySqlite(array $config, string $dir, string $timestamp): string
    {
        $source = (string) $config['database'];
        if (! File::exists($source)) {
            throw new \RuntimeException("SQLite database not found at {$source}");
        }

        $path = "{$dir}/db-{$timestamp}.sqlite.gz";
        File::put($path, gzencode((string) File::get($source), 9));

        return $path;
    }

    private function prune(string $dir, int $keep): void
    {
        $backups = collect(File::files($dir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'db-'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $backups->slice(max(1, $keep))->each(function ($file): void {
            File::delete($file->getPathname());
            $this->line("Pruned old backup: {$file->getFilename()}");
        });
    }
}
