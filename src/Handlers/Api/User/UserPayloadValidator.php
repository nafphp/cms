<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\User;

use NixPHP\CMS\Contracts\PasswordPolicyInterface;
use NixPHP\CMS\Services\Security\PasswordPolicy;

final class UserPayloadValidator
{
    public function __construct(
        private readonly PasswordPolicyInterface $passwords = new PasswordPolicy(),
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    public function validate(array $data, bool $creating, bool $requireRoles = true): array
    {
        $errors = [];
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');
        $roles = $data['roles'] ?? [];

        if (mb_strlen($username) < 3) {
            $errors['username'][] = 'Please enter a username with at least 3 characters.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        if ($creating && !$this->passwords->accepts($password)) {
            $errors['password'][] = $this->passwords->message();
        }

        if (!$creating && $password !== '' && !$this->passwords->accepts($password)) {
            $errors['password'][] = $this->passwords->message();
        }

        if (trim($password) !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if ($requireRoles && (!is_array($roles) || $roles === [])) {
            $errors['roles'][] = 'Please assign at least one role.';
        }

        return $errors;
    }
}
