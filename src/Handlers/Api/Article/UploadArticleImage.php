<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Article;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

use function Naf\CMS\cms;
use function Naf\CMS\user;
use function Naf\json;
use function Naf\request;

final class UploadArticleImage
{
    public function __invoke(): ResponseInterface
    {
        $request = request();
        $file    = $request instanceof ServerRequestInterface ? ($request->getUploadedFiles()['image'] ?? null) : null;
        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return json(['success' => 0, 'message' => 'Upload failed.'], 400);
        }

        try {
            $media = cms()->media()->storeUploadedFile($file, user()?->get()?->getId(), [
                'collection' => 'articles',
            ]);
        } catch (Throwable $exception) {
            return json(['success' => 0, 'message' => $exception->getMessage()], 400);
        }

        return json([
            'success' => 1,
            'media'   => $media,
            'file'    => ['url' => (string) ($media['public_url'] ?? '')],
        ]);
    }
}
