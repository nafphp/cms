<?php

declare(strict_types=1);

namespace src\Handlers\Api\Media;

use Psr\Http\Message\ResponseInterface;
use function NixPHP\I18n\t;
use function NixPHP\json;
use function NixCMS\cms;

final class DeleteMedia
{
    public function __invoke(string $id): ResponseInterface
    {
        $mediaId = (int) $id;
        if ($mediaId <= 0) {
            return json(['success' => false, 'reason' => t('Invalid media id.')], 400);
        }

        if (!cms()->media()->delete($mediaId)) {
            return json(['success' => false, 'reason' => t('Media item could not be deleted.')], 400);
        }

        return json(['success' => true]);
    }
}
