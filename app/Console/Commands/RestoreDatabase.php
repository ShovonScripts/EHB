<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class RestoreDatabase extends Command
{
    protected $signature = 'restore:database 
                            {path : Path to the backup file}
                            {--force : Force restore without confirmation}';

    protected $description = 'Restore the database from a backup file';

    public function handle(): int
    {
        $backupPath = $this->argument('path');

        if (! File::exists($backupPath)) {
            $this->error("Backup file not found: {$backupPath}");

            return Command::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('This will overwrite the current database. Are you sure?')) {
                $this->info('Restore cancelled.');

                return Command::SUCCESS;
            }
        }

        $driver = \DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return $this->restoreSqlite($backupPath);
        }

        if ($driver === 'mysql') {
            return $this->restoreMysql($backupPath);
        }

        $this->error("Unsupported database driver: {$driver}");

        return Command::FAILURE;
    }

    private function restoreSqlite(string $backupPath): int
    {
        $database = Config::get('database.connections.sqlite.database');

        if (str_ends_with($backupPath, '.sqlite')) {
            if ($database === ':memory:' || empty($database)) {
                $this->error('Cannot restore to in-memory SQLite database from file backup.');

                return Command::FAILURE;
            }

            File::copy($backupPath, $database);
        } elseif (str_ends_with($backupPath, '.sql')) {
            $pdo = \DB::connection()->getPdo();

            $sql = File::get($backupPath);

            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                $this->error("SQL restore failed: {$e->getMessage()}");
                $this->line("SQL: {$sql}");

                return Command::FAILURE;
            }
        } else {
            $this->error('Unsupported backup format. Expected .sqlite or .sql file.');

            return Command::FAILURE;
        }

        $this->info("SQLite database restored from: {$backupPath}");

        return Command::SUCCESS;
    }

    private function restoreMysql(string $backupPath): int
    {
        $host = Config::get('database.connections.mysql.host');
        $port = Config::get('database.connections.mysql.port', 3306);
        $database = Config::get('database.connections.mysql.database');
        $username = Config::get('database.connections.mysql.username');
        $password = Config::get('database.connections.mysql.password');

        if (str_ends_with($backupPath, '.sql')) {
            $command = sprintf(
                'mysql --host=%s --port=%d --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($host),
                $port,
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($backupPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $this->error("MySQL restore failed. Return code: {$returnVar}");

                return Command::FAILURE;
            }
        } else {
            $this->error('Unsupported backup format. Expected .sql file.');

            return Command::FAILURE;
        }

        $this->info("MySQL database restored from: {$backupPath}");

        return Command::SUCCESS;
    }
}
