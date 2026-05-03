<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Form;

use NixCMS\Handlers\Support\ReadsJsonBody;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class StoreForm
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $errors = $this->validate($data);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        try {
            $id = \cms()->forms()->save([
                'id' => (int) ($data['id'] ?? 0),
                'identifier' => (string) ($data['identifier'] ?? ''),
                'title' => (string) ($data['title'] ?? ''),
                'status' => (string) ($data['status'] ?? 'draft'),
                'fields' => is_array($data['fields'] ?? null) ? $data['fields'] : [],
                'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            ]);

            return json(['success' => true, 'id' => $id]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'][] = 'Please enter a form title.';
        }

        if (trim((string) ($data['identifier'] ?? '')) === '') {
            $errors['identifier'][] = 'Please enter a stable identifier.';
        }

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        if ($fields === []) {
            $errors['fields'][] = 'Add at least one field.';
        }

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            if (trim((string) ($field['label'] ?? '')) === '') {
                $errors['fields'][] = sprintf('Field %d needs a label.', $index + 1);
            }

            $limits = ['min' => 0, 'max' => 0];
            foreach (is_array($field['validators'] ?? null) ? $field['validators'] : [] as $validator) {
                if (!is_array($validator)) {
                    continue;
                }
                $rule = (string) ($validator['rule'] ?? '');
                if ($rule === 'min' || $rule === 'max') {
                    $limits[$rule] = max(0, (int) ($validator['param'] ?? 0));
                }
            }
            if ($limits['min'] > 0 && $limits['max'] > 0 && $limits['min'] > $limits['max']) {
                $errors['fields'][] = sprintf('Field %d minimum length cannot be greater than maximum length.', $index + 1);
            }
        }

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $actions = is_array($settings['actions'] ?? null) ? $settings['actions'] : [];
        $hasEnabledAction = false;
        foreach ($actions as $index => $action) {
            if (!is_array($action) || ($action['enabled'] ?? true) === false) {
                continue;
            }

            $type = strtolower(trim((string) ($action['type'] ?? '')));
            if ($type === 'store') {
                $hasEnabledAction = true;
                continue;
            }

            if ($type === 'webhook') {
                $hasEnabledAction = true;
                $url = trim((string) ($action['url'] ?? ''));
                if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('/^https?:\/\//i', $url)) {
                    $errors['actions'][] = sprintf('Webhook action %d needs a valid http or https URL.', $index + 1);
                }
            }
        }

        if ($actions !== [] && !$hasEnabledAction) {
            $errors['actions'][] = 'Enable at least one submission action.';
        }

        return $errors;
    }
}
