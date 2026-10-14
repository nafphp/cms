<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Form;

use Naf\CMS\Services\Form\FormSubmissionValidator;
use Tests\Support\NafTestCase;

final class FormSubmissionValidatorTest extends NafTestCase
{
    public function testValidatesRequiredEmailAndLengthRules(): void
    {
        $errors = (new FormSubmissionValidator())->validate([
            'email'   => 'not-an-email',
            'message' => 'hi',
        ], [
            [
                'name'       => 'email',
                'type'       => 'email',
                'validators' => [
                    ['rule' => 'required'],
                    ['rule' => 'email'],
                ],
            ],
            [
                'name'       => 'message',
                'type'       => 'textarea',
                'validators' => [
                    ['rule' => 'required'],
                    ['rule' => 'min', 'param' => 5],
                    ['rule' => 'max', 'param' => 20],
                ],
            ],
        ]);

        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('message', $errors);
    }

    public function testOptionalEmptyEmailDoesNotFail(): void
    {
        $errors = (new FormSubmissionValidator())->validate([
            'email' => '',
        ], [
            [
                'name'     => 'email',
                'type'     => 'email',
                'required' => false,
            ],
        ]);

        self::assertSame([], $errors);
    }

    public function testSelectValueMustMatchConfiguredOptions(): void
    {
        $errors = (new FormSubmissionValidator())->validate([
            'topic' => 'billing',
        ], [
            [
                'name'    => 'topic',
                'type'    => 'select',
                'options' => ['support', 'sales'],
            ],
        ]);

        self::assertArrayHasKey('topic', $errors);
    }
}
