<?php

declare(strict_types=1);

namespace NixCMS\Commands;

use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;
use PDO;
use PDOException;
use function NixPHP\config;

class CreateDatabaseCommand extends AbstractCommand
{
    public const string NAME = 'db:create';

    protected function configure(): void
    {
        $this
            ->setTitle('Create Database')
            ->setDescription('Create the configured MySQL database if it does not exist.')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    php bin/nix db:create');
            $output->writeLine('  Reads driver/host/port/username/password/database from app config (.env).');
            return self::SUCCESS;
        }

        $dbConfig = (array) config('database', []);
        $driver = (string) ($dbConfig['driver'] ?? 'mysql');

        if ($driver === 'sqlite') {
            $output->writeLine('SQLite databases are created on first connect; nothing to do.', 'ok');
            return self::SUCCESS;
        }

        $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
        $port = (string) ($dbConfig['port'] ?? '3306');
        $username = (string) ($dbConfig['username'] ?? '');
        $password = (string) ($dbConfig['password'] ?? '');
        $database = trim((string) ($dbConfig['database'] ?? ''));
        $charset = (string) ($dbConfig['charset'] ?? 'utf8mb4');
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

        $existsStmt = $pdo->prepare(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1'
        );
        $existsStmt->execute(['name' => $database]);
        $alreadyExists = $existsStmt->fetchColumn() !== false;

        $sql = sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            $database,
            $charset,
            $collation
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
}
