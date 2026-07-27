<?php

declare(strict_types=1);

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Page\Form\StoreForm;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Services\Settings\TranslationFileService;
use function NixPHP\I18n\t;
use function NixPHP\redirect;
use function NixPHP\route;
use function NixPHP\View\render;
use function NixPHP\CMS\cms;

$translationSuggestions = static function (): array {
    $language = cms()->settings()->currentLanguage();
    $entries = (new TranslationFileService())->entries($language);

    return array_map(
        static fn(string $key, string $value): array => ['key' => $key, 'value' => $value],
        array_keys($entries),
        array_values($entries),
    );
};

return [
    Page::create('admin.forms')
        ->setPath('/admin/forms')
        ->setTitle('Formulare')
        ->setLayout('admin')
        ->requirePermission(Permission::FORMS_VIEW)
        ->setAdminNavigation('Formulare', parent: 'content', icon: 'form', order: 20, key: 'forms')
        ->setContent(static fn() => render('admin.forms.index', [
            'title' => t('Formulare'),
            'pageTitle' => t('Formulare'),
            'pageSubtitle' => t('Formulare erstellen, bearbeiten, veröffentlichen und Einsendungen prüfen.'),
            'forms' => cms()->forms()->summaries(),
        ])),

    Page::create('admin.forms.create')
        ->setPath('/admin/forms/create')
        ->setTitle('Create form')
        ->setLayout('admin')
        ->requirePermission(Permission::FORMS_CREATE)
        ->setDataHandler(StoreForm::class, 'POST', Permission::FORMS_CREATE)
        ->hideFromAdminNavigation('forms')
        ->setContent(static fn() => render('admin.forms.builder', [
            'title' => t('Create form'),
            'pageTitle' => t('Create form'),
            'pageSubtitle' => t('Define fields, validation basics, and submission behavior.'),
            'form' => null,
            'mode' => 'create',
            'saveUrl' => route('admin.forms.create'),
            'overviewUrl' => route('admin.forms'),
            'translationSuggestions' => $translationSuggestions(),
        ])),

    Page::create('admin.forms.edit')
        ->setPath('/admin/forms/{formId}/edit')
        ->setTitle('Edit form')
        ->setLayout('admin')
        ->requirePermission(Permission::FORMS_EDIT)
        ->setDataHandler(StoreForm::class, 'POST', Permission::FORMS_EDIT)
        ->hideFromAdminNavigation('forms')
        ->setContent(static function (string $formId) use ($translationSuggestions) {
            $form = cms()->forms()->find((int) $formId);
            if (!is_array($form)) {
                return redirect(route('admin.forms'));
            }

            return render('admin.forms.builder', [
                'title' => t('Edit form'),
                'pageTitle' => t('Edit form'),
                'pageSubtitle' => t('Update fields, status, and submission behavior.'),
                'form' => $form,
                'mode' => 'edit',
                'saveUrl' => route('admin.forms.edit', ['formId' => (int) $formId]),
                'overviewUrl' => route('admin.forms'),
                'translationSuggestions' => $translationSuggestions(),
            ]);
        }),

    Page::create('admin.forms.submissions')
        ->setPath('/admin/forms/{formId}/submissions')
        ->setTitle('Form submissions')
        ->setLayout('admin')
        ->requirePermission(Permission::FORMS_SUBMISSIONS_VIEW)
        ->hideFromAdminNavigation('forms')
        ->setContent(static function (string $formId) {
            $form = cms()->forms()->find((int) $formId);
            if (!is_array($form)) {
                return redirect(route('admin.forms'));
            }

            return render('admin.forms.submissions', [
                'title' => t('Form submissions'),
                'pageTitle' => t('Form submissions'),
                'pageSubtitle' => (string) ($form['title'] ?? ''),
                'form' => $form,
                'submissions' => cms()->forms()->submissions((int) $formId),
            ]);
        }),
];
