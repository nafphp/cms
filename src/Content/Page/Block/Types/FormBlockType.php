<?php

declare(strict_types=1);

namespace src\Content\Page\Block\Types;

use src\Content\Page\Block\AbstractBlockType;
use src\Content\Page\Block\PageBuilderBlockOptions;

final class FormBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'form';
    }

    public function label(): string
    {
        return 'Form';
    }

    public function description(): string
    {
        return 'Render a reusable form created under Content > Forms.';
    }

    public function regions(): array
    {
        return ['main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Form';
    }

    public function defaults(): array
    {
        $formOptions = PageBuilderBlockOptions::formOptions();

        return [
            'title' => 'Contact',
            'intro' => 'Send us a message.',
            'formIdentifier' => (string) ($formOptions[0]['value'] ?? 'contact'),
            'submitLabel' => 'Send',
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'intro', 'label' => 'Intro', 'type' => 'textarea'],
            ['name' => 'formIdentifier', 'label' => 'Form', 'type' => 'select', 'options' => PageBuilderBlockOptions::formOptions()],
            ['name' => 'submitLabel', 'label' => 'Submit label', 'type' => 'text'],
            $this->surfaceField(),
        ];
    }
}
