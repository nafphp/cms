<?php

declare(strict_types=1);

namespace NixCMS\View\Admin\Form;

use function NixPHP\Form\is_post;
use function NixPHP\Form\memory;
use function NixPHP\param;
use function NixPHP\View\s;

function attributes(array $attributes): string
{
    $html = '';
    foreach ($attributes as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        $html .= ' ' . s((string) $name);
        if ($value !== true) {
            $html .= '="' . s((string) $value) . '"';
        }
    }

    return $html;
}

function classes(string ...$classes): string
{
    return trim(implode(' ', array_filter(array_map('trim', $classes))));
}

function field_id(string $name, ?string $id = null): string
{
    if ($id !== null && trim($id) !== '') {
        return trim($id);
    }

    $base = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name), '-');
    if ($base === '') {
        $base = 'field';
    }

    static $counts = [];
    $counts[$base] = ($counts[$base] ?? 0) + 1;

    return $counts[$base] === 1 ? $base : $base . '-' . $counts[$base];
}

function remembered(string $name, mixed $fallback = null, bool $useMemory = true): string
{
    if ($useMemory && $name !== '') {
        $remembered = memory($name);
        if ($remembered !== null) {
            return (string) $remembered;
        }
    }

    return (string) ($fallback ?? '');
}

function remembered_values(string $name, mixed $fallback = [], bool $useMemory = true): array
{
    $values = $fallback;

    if ($useMemory && $name !== '') {
        $normalizedName = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
        $remembered = param()->get($normalizedName);
        if ($remembered !== null) {
            $values = $remembered;
        }
    }

    if (!is_array($values)) {
        $values = $values === null || $values === '' ? [] : [$values];
    }

    return array_values(array_map(static fn(mixed $value): string => (string) $value, $values));
}

function checked(string $name, mixed $value, bool $default = false, bool $useMemory = true): bool
{
    if ($useMemory && $name !== '') {
        $remembered = memory($name);
        if ($remembered !== null || is_post()) {
            return (string) $remembered === (string) $value;
        }
    }

    return $default;
}
