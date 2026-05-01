<?php

declare(strict_types=1);

use NixCMS\Settings\SettingField;
use NixCMS\Settings\SettingSection;
use function NixPHP\I18n\t;

$languageOptions = [
    'en' => t('en'),
    'de' => t('de'),
    'fr' => t('fr'),
    'es' => t('es'),
    'it' => t('it'),
];

return [
    SettingSection::create('application', t('Application'))
        ->setDescription(t('Basic application identity.'))
        ->setGroup('general')
        ->setOrder(10)
        ->addFields([
            SettingField::text('app.name', t('Application name'))
                ->setDescription(t('Shown in the browser title and admin logo.'))
                ->setDefaultValue('NixCMS'),
            SettingField::text('app.short_name', t('Application short name'))
                ->setDescription(t('Shown as compact logo text when space is tight.'))
                ->setDefaultValue('NX'),
        ]),
    SettingSection::create('languages', t('Languages'))
        ->setDescription(t('Language defaults and enabled CMS languages.'))
        ->setGroup('languages')
        ->setOrder(10)
        ->addFields([
            SettingField::select('language', t('Default language'), $languageOptions)
                ->setDescription(t('Used when no request language is selected.'))
                ->setDefaultValue('en'),
            SettingField::select('fallback_language', t('Fallback language'), $languageOptions)
                ->setDescription(t('Used when a translation file is missing.'))
                ->setDefaultValue('en'),
            SettingField::multiselect('cms.languages', t('Enabled languages'), $languageOptions)
                ->setDescription(t('Select the languages available in the CMS.'))
                ->setDefaultValue(['en', 'de']),
        ]),
];
