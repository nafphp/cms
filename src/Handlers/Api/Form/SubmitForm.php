<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Form;

use Naf\CMS\Services\Form\FormSubmissionActionRunner;
use Naf\CMS\Services\Form\FormSubmissionGuard;
use Naf\CMS\Services\Form\FormSubmissionValidator;
use Naf\CMS\Services\Form\FormTextTranslator;
use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\cms;
use function Naf\I18n\t;
use function Naf\json;
use function Naf\request;

final class SubmitForm
{
    public function __invoke(string $identifier): ResponseInterface
    {
        $rawBody   = (string) request()->getBody();
        $payload   = json_decode($rawBody, true);
        $payload   = is_array($payload) ? $payload : [];
        $ipAddress = (string) (request()->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $guard     = new FormSubmissionGuard();
        $limit     = $guard->consume($identifier, $ipAddress);
        if (!$limit->allowed) {
            return json(['success' => false, 'reason' => 'Too many form submissions. Please try again later.'], 429)
                ->withHeader('Retry-After', (string) max(1, $limit->retryAfter));
        }

        $guardErrors = $guard->validatePayload($rawBody, $payload);
        if ($guardErrors !== []) {
            return json(['success' => false, 'errors' => $guardErrors], 413);
        }

        $form = cms()->forms()->findByIdentifier($identifier, true);
        if (!is_array($form)) {
            return json(['success' => false, 'reason' => 'Form not found.'], 404);
        }

        if ($guard->looksLikeSpam($payload)) {
            return json(['success' => true, 'message' => t('Thank you.')]);
        }

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
            'ip'        => $ipAddress,
            'userAgent' => mb_substr((string) (request()->getServerParams()['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ];

        $actionResult = (new FormSubmissionActionRunner())->run($form, $values, $meta);
        if ($actionResult['success'] !== true) {
            return json([
                'success' => false,
                'reason'  => implode(' ', $actionResult['errors']) ?: 'Form action failed.',
            ], 502);
        }

        $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];

        return json([
            'success' => true,
            'message' => (new FormTextTranslator())->setting($settings, 'successMessage', t('Thank you.')),
        ]);
    }
}
