<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

final class UserPayloadValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    public static function validate(array $data, bool $creating): array
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

        if ($creating && mb_strlen(trim($password)) < 8) {
            $errors['password'][] = 'Please enter a password with at least 8 characters.';
        }

        if (!$creating && trim($password) !== '' && mb_strlen(trim($password)) < 8) {
            $errors['password'][] = 'Please enter a password with at least 8 characters.';
        }

        if (trim($password) !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if (!is_array($roles) || $roles === []) {
            $errors['roles'][] = 'Please assign at least one role.';
        }

        return $errors;
    }
}
