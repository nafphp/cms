<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

/** @api */
interface SettingsServiceInterface
{
    /** @return array<int, array<string, mixed>> */
    public function sections(?string $group = null): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, errors: array<string, list<string>>}
     */
    public function save(array $payload): array;
}
