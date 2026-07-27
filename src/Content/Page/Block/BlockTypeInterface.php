<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block;

interface BlockTypeInterface
{
    public function id(): string;

    public function label(): string;

    public function description(): string;

    /**
     * @return array<int, string>
     */
    public function regions(): array;

    public function previewTitle(): string;

    public function unique(): bool;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array;

    /**
     * @return array<int, string>
     */
    public function contentTypes(): array;

    /**
     * @return array<int, string>
     */
    public function templateKinds(): array;

    /**
     * @return array<string, mixed>
     */
    public function toBuilderArray(): array;
}
