<?php

declare(strict_types=1);

use src\Settings\SettingField;
use src\Settings\SettingSection;
use src\Enums\Permission;
use function NixCMS\cms;

$languageOptions = array_combine(
    cms()->languages()->available(),
    cms()->languages()->available(),
) ?: [];

return [
    SettingSection::create('application', 'Application')
        ->setDescription('Basic application identity.')
        ->setGroup('general')
        ->setOrder(10)
        ->addFields([
            SettingField::text('app.name', 'Application name')
                ->setDescription('Shown in the browser title and admin logo.')
                ->setDefaultValue('NixCMS'),
            SettingField::text('app.short_name', 'Application short name')
                ->setDescription('Shown as compact logo text when space is tight.')
                ->setDefaultValue('NX'),
            SettingField::text('branding.logoText', 'Logo text')
                ->setDescription('Shown in public page headers when a page does not override it.')
                ->setDefaultValue(''),
            SettingField::text('branding.logoImage', 'Logo image')
                ->setDescription('Optional public URL for a brand mark.')
                ->setDefaultValue(''),
        ]),
    SettingSection::create('languages', 'Languages')
        ->setDescription('Language defaults and enabled CMS languages.')
        ->setGroup('languages')
        ->setOrder(10)
        ->addFields([
            SettingField::select('language', 'Default language', $languageOptions)
                ->setDescription('Used when no request language is selected.')
                ->setDefaultValue('en'),
            SettingField::select('fallback_language', 'Fallback language', $languageOptions)
                ->setDescription('Used when a translation file is missing.')
                ->setDefaultValue('en'),
            SettingField::multiselect('cms.languages', 'Enabled languages', $languageOptions)
                ->setDescription('Select the languages available in the CMS.')
                ->setDefaultValue(['en', 'de']),
        ]),
    SettingSection::create('local_ai', 'Local AI')
        ->setDescription('Configure the browser-local Ollama assistant used by CMS helpers.')
        ->setGroup('ai')
        ->setIcon('ai')
        ->setOrder(10)
        ->setPermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setView('admin.settings.partials.local-ai')
        ->addFields([
            SettingField::select('ai.enabled', 'AI agent', [
                '0' => 'Disabled',
                '1' => 'Enabled',
            ])
                ->setDescription('Shows the floating Local AI chat in the admin UI. Chat history stays in this browser when disabled.')
                ->setDefaultValue('0'),
            SettingField::select('ai.type', 'AI type', [
                'local' => 'Local Ollama',
                'remote' => 'Remote provider',
            ])
                ->setDescription('Local uses Ollama directly from the admin browser. Remote is reserved for a later provider integration.')
                ->setDefaultValue('local'),
            SettingField::text('ai.url', 'Ollama URL')
                ->setDescription('Base URL reachable from your browser. Server-side embeddings allow only localhost, 127.0.0.1, or ::1 on configured Ollama ports (11434 by default).')
                ->setDefaultValue('http://localhost:11434'),
            SettingField::text('ai.model', 'Default model')
                ->setDescription('Default Ollama model for CMS assistant tasks.')
                ->setDefaultValue('llama3.1:8b'),
            SettingField::text('ai.embedding_model', 'Embedding model')
                ->setDescription('Optional Ollama embedding model for semantic tool routing, for example nomic-embed-text. Leave empty to use keyword routing.')
                ->setDefaultValue(''),
            SettingField::textarea('ai.instructions', 'Additional agent instructions')
                ->setDescription('Extra instructions the CMS assistant should follow in addition to the project AGENTS.md guidance.')
                ->setDefaultValue(''),
        ]),
];
