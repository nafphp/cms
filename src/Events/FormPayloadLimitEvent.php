<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

use Naf\CMS\Services\Form\FormSubmissionGuard;
use Psr\Http\Message\ServerRequestInterface;
use function Naf\abort;

final class FormPayloadLimitEvent
{
    public function handle(ServerRequestInterface $request): void
    {
        $path = $request->getUri()->getPath();
        if ($request->getMethod() !== 'POST' || !str_starts_with($path, '/api/forms/')) {
            return;
        }

        $contentLength = (int) $request->getHeaderLine('Content-Length');
        if ($contentLength > FormSubmissionGuard::MAXIMUM_BODY_BYTES) {
            abort(413, 'Form payload is too large.');
        }
    }
}
