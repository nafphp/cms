<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixCMS\Repositories\FormRepository;
use Tests\Support\DatabaseTestCase;

final class FormRepositoryTest extends DatabaseTestCase
{
    private FormRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            <<<'SQL'
                CREATE TABLE forms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    identifier TEXT NOT NULL UNIQUE,
                    title TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'draft',
                    fields_json TEXT NOT NULL,
                    settings_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL
                )
            SQL,
            <<<'SQL'
                CREATE TABLE form_submissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    form_id INTEGER NOT NULL,
                    payload_json TEXT NOT NULL,
                    meta_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL,
        );

        $this->repository = new FormRepository($this->pdo);
    }

    public function testSaveKeepsTranslationKeysBesideFallbackText(): void
    {
        $id = $this->repository->save([
            'identifier' => 'contact',
            'title' => 'Contact form',
            'status' => 'published',
            'settings' => [
                'titleKey' => 'Forms Contact Title',
                'submitLabel' => 'Send message',
                'submitLabelKey' => 'Forms Contact Submit',
                'successMessage' => 'Thank you.',
                'successMessageKey' => 'Forms Contact Success',
            ],
            'fields' => [
                [
                    'label' => 'Email address',
                    'labelKey' => 'Forms Contact Email Label',
                    'name' => '',
                    'type' => 'email',
                    'placeholder' => 'jane@example.com',
                    'placeholderKey' => 'Forms Contact Email Placeholder',
                    'validators' => [
                        ['rule' => 'required'],
                        ['rule' => 'email'],
                    ],
                ],
            ],
        ]);

        $form = $this->repository->find($id);

        self::assertNotNull($form);
        self::assertSame('forms.contact.title', $form['settings']['titleKey']);
        self::assertSame('forms.contact.submit', $form['settings']['submitLabelKey']);
        self::assertSame('forms.contact.success', $form['settings']['successMessageKey']);
        self::assertSame('email-address', $form['fields'][0]['name']);
        self::assertSame('forms.contact.email.label', $form['fields'][0]['labelKey']);
        self::assertSame('forms.contact.email.placeholder', $form['fields'][0]['placeholderKey']);
    }
}
