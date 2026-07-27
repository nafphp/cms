<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Form;

use NixPHP\Form\Core\Validator;

final class FormSubmissionValidator
{
    private static bool $registered = false;

    /**
     * @param array<string, mixed> $payload
     * @param array<int, mixed> $fields
     * @return array<string, array<int, string>>
     */
    public function validate(array $payload, array $fields): array
    {
        self::registerCoreRules();

        $rules = [];
        $messages = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $fieldRules = $this->rulesForField($field);
            if ($fieldRules === []) {
                continue;
            }

            $rules[$name] = implode('|', $fieldRules);
            $messages[$name] = [
                'required' => 'This field is required.',
                'email' => 'Please enter a valid email address.',
                'min' => 'Please enter at least %s characters.',
                'max' => 'Please enter no more than %s characters.',
                'in' => 'Please choose a valid option.',
            ];
        }

        if ($rules === []) {
            return [];
        }

        $validator = new Validator();
        $validator->validate($payload, $rules, $messages);

        return $validator->getErrorMessages();
    }

    public static function registerCoreRules(): void
    {
        if (self::$registered) {
            return;
        }

        Validator::register('required', static function (mixed $value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return trim((string) $value) !== '';
        }, 'This field is required.');

        Validator::register('email', static fn(mixed $value): bool => trim((string) $value) === ''
            || filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false, 'Invalid email.');

        Validator::register('min', static fn(mixed $value, mixed $param): bool => trim((string) $value) === ''
            || mb_strlen((string) $value) >= max(0, (int) $param), 'Min %d chars.');

        Validator::register('max', static fn(mixed $value, mixed $param): bool => trim((string) $value) === ''
            || mb_strlen((string) $value) <= max(0, (int) $param), 'Max %d chars.');

        Validator::register('in', static function (mixed $value, mixed $param): bool {
            if (trim((string) $value) === '') {
                return true;
            }

            $allowed = array_map('trim', explode(',', (string) $param));
            return in_array((string) $value, $allowed, true);
        }, 'Invalid option.');

        self::$registered = true;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int, string>
     */
    private function rulesForField(array $field): array
    {
        $type = strtolower(trim((string) ($field['type'] ?? 'text')));

        $rules = $this->validatorChain($field);

        if ($type === 'select') {
            $options = array_values(array_filter(array_map(
                static fn(mixed $option): string => trim((string) $option),
                is_array($field['options'] ?? null) ? $field['options'] : []
            )));
            if ($options !== []) {
                $rules[] = 'in:' . implode(',', $options);
            }
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int, string>
     */
    private function validatorChain(array $field): array
    {
        $rules = [];
        $validators = is_array($field['validators'] ?? null) ? $field['validators'] : [];

        if ($validators !== []) {
            foreach ($validators as $validator) {
                if (!is_array($validator)) {
                    continue;
                }

                $rule = strtolower(trim((string) ($validator['rule'] ?? '')));
                if (!in_array($rule, ['required', 'email', 'min', 'max'], true)) {
                    continue;
                }

                $param = trim((string) ($validator['param'] ?? ''));
                if (in_array($rule, ['min', 'max'], true)) {
                    $length = max(0, (int) $param);
                    if ($length <= 0) {
                        continue;
                    }
                    $rules[] = $rule . ':' . $length;
                    continue;
                }

                $rules[] = $rule;
            }

            return array_values(array_unique($rules));
        }

        $type = strtolower(trim((string) ($field['type'] ?? 'text')));
        $validation = strtolower(trim((string) ($field['validation'] ?? '')));

        if ((bool) ($field['required'] ?? false)) {
            $rules[] = 'required';
        }
        if ($type === 'email' || $validation === 'email') {
            $rules[] = 'email';
        }
        if ((int) ($field['minLength'] ?? 0) > 0) {
            $rules[] = 'min:' . max(0, (int) $field['minLength']);
        }
        if ((int) ($field['maxLength'] ?? 0) > 0) {
            $rules[] = 'max:' . max(0, (int) $field['maxLength']);
        }

        return $rules;
    }
}
