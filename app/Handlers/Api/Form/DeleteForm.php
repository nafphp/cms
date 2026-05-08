<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Form;

use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixCMS\cms;

final class DeleteForm
{
    public function __invoke(string $id): ResponseInterface
    {
        $formId = (int) $id;
        if ($formId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid form id.'], 400);
        }

        if (!cms()->forms()->delete($formId)) {
            return json(['success' => false, 'reason' => 'Could not delete form.'], 400);
        }

        return json(['success' => true]);
    }
}
