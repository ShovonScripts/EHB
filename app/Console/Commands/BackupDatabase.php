<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database 
                            {--path= : Custom backup path}
                            {--disk= : Storage disk to use (default: local)}';

    protected $description = 'Backup the database to a SQL dump or file copy';

    public function handle(): int
    {
        $driver = \DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return $this->backupSqlite();
        }

        if ($driver === 'mysql') {
            return $this->backupMysql();
        }

        $this->error("Unsupported database driver: {$driver}");

        return Command::FAILURE;
    }

    private function backupSqlite(): int
    {
        $database = Config::get('database.connections.sqlite.database');

        if ($database === ':memory:' || empty($database)) {
            $this->warn('In-memory SQLite database detected; using SQL export.');

            return $this->exportSql();
        }

        if (! File::exists($database)) {
            $this->error("SQLite database not found: {$database}");

            return Command::FAILURE;
        }

        $backupDir = $this->option('path') ?? storage_path('app/backups');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'database-'.date('Y-m-d-His').'-'.Str::random(8).'.sqlite';
        $backupPath = $backupDir.'/'.$filename;

        File::copy($database, $backupPath);

        $this->info("SQLite database backed up to: {$backupPath}");

        return Command::SUCCESS;
    }

    private function exportSql(): int
    {
        $backupDir = $this->option('path') ?? storage_path('app/backups');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'database-'.date('Y-m-d-His').'-'.Str::random(8).'.sql';
        $backupPath = $backupDir.'/'.$filename;

        $pdo = \DB::connection()->getPdo();
        $tables = $pdo->query('SELECT name FROM sqlite_master WHERE type="table" AND name NOT LIKE "sqlite_%"')->fetchAll(\PDO::FETCH_COLUMN);

        $sql = "-- SQLite database backup\n";
        $sql .= '-- Generated: '.date('Y-m-d H:i:s')."\n\n";

        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS {$table};\n";

            $createTable = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            $sql .= $createTable.";\n\n";

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $columns = array_map(fn ($col) => $col, array_keys($row));
                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote($value);
                }, array_values($row));

                $sql .= "INSERT INTO {$table} (".implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n";
            }

            $sql .= "\n";
        }

        File::put($backupPath, $sql);

        $this->info("SQL database backed up to: {$backupPath}");

        return Command::SUCCESS;
    }

    private function backupMysql(): int
    {
        $host = Config::get('database.connections.mysql.host');
        $port = Config::get('database.connections.mysql.port', 3306);
        $database = Config::get('database.connections.mysql.database');
        $username = Config::get('database.connections.mysql.username');
        $password = Config::get('database.connections.mysql.password');

        $backupDir = $this->option('path') ?? storage_path('app/backups');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'database-'.date('Y-m-d-His').'-'.Str::random(8).'.sql';
        $backupPath = $backupDir.'/'.$filename;

        $mysqldump = shell_exec('where mysqldump 2>&1');

        if (! empty($mysqldump) && preg_match('/mysqldump\.(exe|bat|cmd)$/i', $mysqldump)) {
            $command = sprintf(
                'mysqldump --host=%s --port=%d --user=%s --password=%s %s > %s 2>&1',
                escapeshellarg($host),
                $port,
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($backupPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $this->error("MySQL backup failed. Return code: {$returnVar}");

                return Command::FAILURE;
            }
        } else {
            $this->warn('mysqldump not found; using PHP-based export.');

            $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

            $sql = "-- Database backup\n";
            $sql .= '-- Generated: '.date('Y-m-d H:i:s')."\n\n";

            foreach ($tables as $table) {
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                $sql .= $createTable['Create Table'].";\n\n";

                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $columns = array_map(fn ($col) => "`{$col}`", array_keys($row));
                    $values = array_map(function ($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }

                        return $pdo->quote($value);
                    }, array_values($row));

                    $sql .= "INSERT INTO `{$table}` (".implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n";
                }

                $sql .= "\n";
            }

            File::put($backupPath, $sql);
        }

        $this->info("MySQL database backed up to: {$backupPath}");

        return Command::SUCCESS;
    }
}
