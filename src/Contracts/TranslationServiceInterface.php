<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

/** @api */
interface TranslationServiceInterface
{
    /** @return array<int, array{code:string,label:string,active:bool}> */
    public function languages(string $activeLanguage = ''): array;

    /** @return array<string, string> */
    public function entries(string $language): array;

    public function hasLanguage(string $language): bool;

    /**
     * @param array<string, mixed> $entries
     * @return array{success: bool, errors: array<string, list<string>>, changed: int}
     */
    public function save(string $language, array $entries): array;

    /**
     * @param array<string, mixed> $entries
     * @param array<int, string> $remove
     * @return array{success: bool, errors: array<string, list<string>>, changed: int}
     */
    public function merge(string $language, array $entries, array $remove = []): array;
}
