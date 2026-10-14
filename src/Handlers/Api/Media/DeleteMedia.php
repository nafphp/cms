<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Media;

use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\cms;
use function Naf\I18n\t;
use function Naf\json;

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
