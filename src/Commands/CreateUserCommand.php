<?php

declare(strict_types=1);

namespace NixPHP\CMS\Commands;

use NixPHP\CMS\Enums\Role as RoleName;
use NixPHP\CMS\Models\Role;
use NixPHP\CMS\Models\User;
use NixPHP\CMS\Repositories\RoleRepository;
use NixPHP\CMS\Repositories\UserRepository;
use NixPHP\CMS\Contracts\PasswordPolicyInterface;
use NixPHP\CMS\Services\Security\PasswordPolicy;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;
use function NixPHP\ORM\repo;

class CreateUserCommand extends AbstractCommand
{
    public const string NAME = 'user:create';

    public function __construct(
        private readonly PasswordPolicyInterface $passwords = new PasswordPolicy(),
    ) {
        // AbstractCommand::__construct() runs configure(), which registers the
        // arguments and options. Skipping it leaves the command without any.
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setTitle('Create User Command')
            ->setDescription('Create the first installation administrator.')
            ->addArgument('username', true)
            ->addArgument('email', true)
            ->addArgument('role', true)
            ->addOption('firstname', 'f', true)
            ->addOption('lastname', 'l', true)
            ->addOption('password-file', 'p', true)
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    vendor/bin/nix user:create <username> <email> <role> [-p <password-file>] [-f <firstname>] [-l <lastname>]');
            $output->writeLine('  Without --password-file the password is requested twice without terminal echo.');
            return self::SUCCESS;
        }

        $username = trim((string) $input->getArgument('username'));
        $email = strtolower(trim((string) $input->getArgument('email')));
        $roleName = trim((string) $input->getArgument('role'));
        $firstname = $this->asStringOrNull($input->getOption('firstname'));
        $lastname = $this->asStringOrNull($input->getOption('lastname'));

        if ($username === '' || strlen($username) < 3) {
            $output->writeLine('Username must contain at least 3 characters.', 'error');
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

        if ($normalizedRoleName !== RoleName::ADMIN->value) {
            $output->writeLine('The first installation user must have the admin role.', 'error');
            return self::ERROR;
        }

        /** @var Role|null $role */
        $role = $roleRepo->findOneBy('name', $normalizedRoleName);
        if (!$role instanceof Role) {
            $output->writeLine(sprintf('Role "%s" does not exist.', $normalizedRoleName), 'error');
            return self::ERROR;
        }

        $userRepo = repo(UserRepository::class);

        if ($userRepo->countAll() !== 0) {
            $output->writeLine('An installation user already exists. Create further users in the protected admin UI.', 'error');
            return self::ERROR;
        }

        if ($userRepo->findOneBy('username', $username) instanceof User) {
            $output->writeLine(sprintf('Username "%s" already exists.', $username), 'error');
            return self::ERROR;
        }

        if ($userRepo->findOneBy('email', $email) instanceof User) {
            $output->writeLine(sprintf('Email "%s" already exists.', $email), 'error');
            return self::ERROR;
        }

        $password = $this->readPassword($input, $output);
        if ($password === null) {
            return self::ERROR;
        }
        if (!$this->passwords->accepts($password)) {
            $output->writeLine($this->passwords->message(), 'error');
            return self::ERROR;
        }

        $createdUser = $userRepo->createBootstrapUserWithRoles(
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

    private function readPassword(Input $input, Output $output): ?string
    {
        $passwordFile = $this->asStringOrNull($input->getOption('password-file'));
        if ($passwordFile !== null) {
            if (!is_file($passwordFile) || !is_readable($passwordFile) || filesize($passwordFile) > $this->passwords->maximumLength() + 2) {
                $output->writeLine('Password file is missing, unreadable, or too large.', 'error');
                return null;
            }
            $permissions = fileperms($passwordFile);
            if (PHP_OS_FAMILY !== 'Windows' && is_int($permissions) && ($permissions & 0077) !== 0) {
                $output->writeLine('Password file must not be readable or writable by group or other users.', 'error');
                return null;
            }
            $password = file_get_contents($passwordFile);
            return is_string($password) ? rtrim($password, "\r\n") : null;
        }

        if (!function_exists('stream_isatty') || !stream_isatty(STDIN) || !function_exists('shell_exec')) {
            $output->writeLine('A TTY with hidden input or --password-file is required.', 'error');
            return null;
        }

        $password = $this->readHiddenLine('Password: ');
        $confirmation = $this->readHiddenLine('Repeat password: ');
        if ($password === null || $confirmation === null || !hash_equals($password, $confirmation)) {
            $output->writeLine('Password confirmation does not match.', 'error');
            return null;
        }

        return $password;
    }

    private function readHiddenLine(string $prompt): ?string
    {
        fwrite(STDOUT, $prompt);
        @shell_exec('stty -echo');
        try {
            $value = fgets(STDIN);
        } finally {
            @shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }

        return is_string($value) ? rtrim($value, "\r\n") : null;
    }
}
