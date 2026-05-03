<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Media;

use NixCMS\Handlers\Support\ReadsJsonBody;
use Psr\Http\Message\ResponseInterface;
use function cms;
use function NixPHP\I18n\t;
use function NixPHP\json;

final class UpdateMedia
{
    use ReadsJsonBody;

    public function __invoke(string $id): ResponseInterface
    {
        $mediaId = (int) $id;
        if ($mediaId <= 0) {
            return json(['success' => false, 'reason' => t('Invalid media id.')], 400);
        }

        $media = cms()->media()->update($mediaId, $this->jsonBody());
        if (!is_array($media)) {
            return json(['success' => false, 'reason' => t('Media item could not be updated.')], 400);
        }

        return json(['success' => true, 'media' => $media]);
    }
}
