<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Form;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Services\Form\FormSubmissionActionRunner;
use NixCMS\Services\Form\FormTextTranslator;
use NixCMS\Services\Form\FormSubmissionValidator;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\I18n\t;
use function NixPHP\json;
use function NixPHP\request;

final class SubmitForm
{
    use ReadsJsonBody;

    public function __invoke(string $identifier): ResponseInterface
    {
        $form = \cms()->forms()->findByIdentifier($identifier, true);
        if (!is_array($form)) {
            return json(['success' => false, 'reason' => 'Form not found.'], 404);
        }

        $payload = $this->jsonBody();
        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        $errors = (new FormSubmissionValidator())->validate($payload, $fields);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $values = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name !== '') {
                $values[$name] = $payload[$name] ?? null;
            }
        }

        $meta = [
            'ip' => request()->getServerParams()['REMOTE_ADDR'] ?? '',
            'userAgent' => request()->getServerParams()['HTTP_USER_AGENT'] ?? '',
        ];

        $actionResult = (new FormSubmissionActionRunner())->run($form, $values, $meta);
        if ($actionResult['success'] !== true) {
            return json([
                'success' => false,
                'reason' => implode(' ', $actionResult['errors']) ?: 'Form action failed.',
            ], 502);
        }

        $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];

        return json([
            'success' => true,
            'message' => (new FormTextTranslator())->setting($settings, 'successMessage', t('Thank you.')),
        ]);
    }

}
