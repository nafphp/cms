<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Content\Page\Block\PageBuilderBlockOptions;

final class ContactFormBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'contact-form';
    }

    public function label(): string
    {
        return 'Contact form';
    }

    public function description(): string
    {
        return 'Contact-oriented preset backed by the reusable Form content type.';
    }

    public function regions(): array
    {
        return ['main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Contact form';
    }

    public function defaults(): array
    {
        $formOptions = PageBuilderBlockOptions::formOptions();

        return [
            'title'          => 'Contact',
            'intro'          => 'Tell us what you need.',
            'formIdentifier' => (string) ($formOptions[0]['value'] ?? 'contact'),
            'submitLabel'    => 'Send message',
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
