<?php

declare(strict_types=1);

namespace NixCMS\Commands;

use NixCMS\Models\Role;
use NixCMS\Models\User;
use NixCMS\Repositories\RoleRepository;
use NixCMS\Repositories\UserRepository;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;
use function NixPHP\ORM\repo;

class CreateUserCommand extends AbstractCommand
{
    public const string NAME = 'user:create';

    protected function configure(): void
    {
        $this
            ->setTitle('Create User Command')
            ->setDescription('Create a user with one existing role.')
            ->addArgument('username', true)
            ->addArgument('password', true)
            ->addArgument('email', true)
            ->addArgument('role', true)
            ->addOption('firstname', 'f', true)
            ->addOption('lastname', 'l', true)
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    php bin/nix user:create <username> <password> <email> <role> [-f <firstname>] [-l <lastname>]');
            return self::SUCCESS;
        }

        $username = trim((string) $input->getArgument('username'));
        $password = trim((string) $input->getArgument('password'));
        $email = strtolower(trim((string) $input->getArgument('email')));
        $roleName = trim((string) $input->getArgument('role'));
        $firstname = $this->asStringOrNull($input->getOption('firstname'));
        $lastname = $this->asStringOrNull($input->getOption('lastname'));

        if ($username === '' || strlen($username) < 3) {
            $output->writeLine('Username must contain at least 3 characters.', 'error');
            return self::ERROR;
        }

        if ($password === '' || strlen($password) < 4) {
            $output->writeLine('Password must contain at least 4 characters.', 'error');
            return self::ERROR;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeLine('Please provide a valid email address.', 'error');
            return self::ERROR;
        }

        $roleRepo = repo(RoleRepository::class);
        $normalizedRoleName = $roleRepo->normalizeName($roleName);
        if ($normalizedRoleName === null) {
            $output->writeLine('Role is required.', 'error');
            return self::ERROR;
        }

        /** @var Role|null $role */
        $role = $roleRepo->findOneBy('name', $normalizedRoleName);
        if (!$role instanceof Role) {
            $output->writeLine(sprintf('Role "%s" does not exist.', $normalizedRoleName), 'error');
            return self::ERROR;
        }

        $userRepo = repo(UserRepository::class);

        if ($userRepo->findOneBy('username', $username) instanceof User) {
            $output->writeLine(sprintf('Username "%s" already exists.', $username), 'error');
            return self::ERROR;
        }

        if ($userRepo->findOneBy('email', $email) instanceof User) {
            $output->writeLine(sprintf('Email "%s" already exists.', $email), 'error');
            return self::ERROR;
        }

        $createdUser = $userRepo->createWithRoles(
            $username,
            $password,
            $firstname,
            $lastname,
            [$normalizedRoleName],
            $email
        );

        if (!$createdUser instanceof User) {
            $output->writeLine('Could not create user.', 'error');
            return self::ERROR;
        }

        $output->writeLine('User created successfully.', 'ok');
        $output->writeLine(sprintf('  Username: %s', $username));
        $output->writeLine(sprintf('  Email:    %s', $email));
        $output->writeLine(sprintf('  Role:     %s', $normalizedRoleName));

        return self::SUCCESS;
    }

    private function asStringOrNull(string|array|bool|null $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        return $normalized !== '' ? $normalized : null;
    }
}
