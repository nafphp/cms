<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Form;

use NixCMS\Handlers\Support\ReadsJsonBody;
use Psr\Http\Message\ResponseInterface;
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
        $errors = $this->validate($payload, $fields);
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

        \cms()->forms()->storeSubmission((int) ($form['id'] ?? 0), $values, [
            'ip' => request()->getServerParams()['REMOTE_ADDR'] ?? '',
            'userAgent' => request()->getServerParams()['HTTP_USER_AGENT'] ?? '',
        ]);

        return json([
            'success' => true,
            'message' => (string) ($form['settings']['successMessage'] ?? 'Thank you.'),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, mixed> $fields
     * @return array<string, array<int, string>>
     */
    private function validate(array $payload, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !($field['required'] ?? false)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name !== '' && trim((string) ($payload[$name] ?? '')) === '') {
                $errors[$name][] = 'This field is required.';
            }
        }

        return $errors;
    }
}
