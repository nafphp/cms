<?php

declare(strict_types=1);

namespace Naf\CMS\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use PDO;
use PDOException;

use function Naf\config;

class CreateDatabaseCommand extends AbstractCommand
{
    public const string NAME = 'db:create';

    protected function configure(): void
    {
        $this
            ->setTitle('Create Database')
            ->setDescription('Create the configured MySQL database if it does not exist.')
            ->addOption('force')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/naf db:create');
            $output->writeLine('    vendor/bin/naf db:create --force');
            $output->writeLine('  Reads driver/host/port/username/password/database from app config (.env).');
            $output->writeLine('  --force drops the configured database before recreating it.');

            return self::SUCCESS;
        }

        $dbConfig = (array) config('database', []);
        $driver   = (string) ($dbConfig['driver'] ?? 'mysql');
        $force    = $input->getOption('force') === true;

        if ($driver === 'sqlite') {
            if ($force) {
                return $this->resetSqliteDatabase((string) ($dbConfig['database'] ?? ''), $output);
            }

            $output->writeLine('SQLite databases are created on first connect; nothing to do.', 'ok');

            return self::SUCCESS;
        }

        $host      = (string) ($dbConfig['host'] ?? '127.0.0.1');
        $port      = (string) ($dbConfig['port'] ?? '3306');
        $username  = (string) ($dbConfig['username'] ?? '');
        $password  = (string) ($dbConfig['password'] ?? '');
        $database  = trim((string) ($dbConfig['database'] ?? ''));
        $charset   = (string) ($dbConfig['charset'] ?? 'utf8mb4');
        $collation = (string) ($dbConfig['collation'] ?? 'utf8mb4_unicode_ci');

        if ($database === '') {
            $output->writeLine('DB_DATABASE is empty. Set it in your .env first.', 'error');

            return self::ERROR;
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            $output->writeLine(sprintf('Refusing to create database with unsafe name "%s".', $database), 'error');

            return self::ERROR;
        }

        $dsn = sprintf('%s:host=%s;port=%s;charset=%s', $driver, $host, $port, $charset);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $output->writeLine(sprintf('Could not connect to %s: %s', $dsn, $e->getMessage()), 'error');

            return self::ERROR;
        }

        $alreadyExists = $this->databaseExists($pdo, $database);

        if ($force && $alreadyExists) {
            try {
                $pdo->exec(sprintf('DROP DATABASE `%s`', $database));
            } catch (PDOException $e) {
                $output->writeLine(sprintf('Could not drop database "%s": %s', $database, $e->getMessage()), 'error');

                return self::ERROR;
            }

            $alreadyExists = false;
            $output->writeLine(sprintf('Database "%s" dropped.', $database), 'warning');
        }

        $sql = sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            $database,
            $charset,
            $collation,
        );

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $output->writeLine(sprintf('Could not create database "%s": %s', $database, $e->getMessage()), 'error');

            return self::ERROR;
        }

        if ($alreadyExists) {
            $output->writeLine(sprintf('Database "%s" already exists.', $database), 'ok');
        } else {
            $output->writeLine(sprintf('Database "%s" created (%s / %s).', $database, $charset, $collation), 'ok');
        }

        return self::SUCCESS;
    }

    private function databaseExists(PDO $pdo, string $database): bool
    {
        $existsStmt = $pdo->prepare(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1',
        );
        $existsStmt->execute(['name' => $database]);

        return $existsStmt->fetchColumn() !== false;
    }

    private function resetSqliteDatabase(string $database, Output $output): int
    {
        $database = trim($database);
        if ($database === '' || $database === ':memory:') {
            $output->writeLine('SQLite database is in-memory or empty; nothing to drop.', 'ok');

            return self::SUCCESS;
        }

        if (is_file($database) && !unlink($database)) {
            $output->writeLine(sprintf('Could not delete SQLite database file "%s".', $database), 'error');

            return self::ERROR;
        }

        $output->writeLine(sprintf('SQLite database "%s" removed.', $database), 'warning');

        return self::SUCCESS;
    }
}
