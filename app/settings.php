<?php

declare(strict_types=1);

use NixCMS\Settings\SettingField;
use NixCMS\Settings\SettingSection;

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
];
