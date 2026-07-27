<?php

declare(strict_types=1);

namespace src\Handlers\Api\Article;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use function NixPHP\json;
use function NixPHP\request;
use function NixCMS\cms;
use function NixCMS\user;

final class UploadArticleImage
{
    public function __invoke(): ResponseInterface
    {
        $file = request()->getUploadedFiles()['image'] ?? null;
        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return json(['success' => 0, 'message' => 'Upload failed.'], 400);
        }

        try {
            $media = cms()->media()->storeUploadedFile($file, user()?->get()?->getId(), [
                'collection' => 'articles',
            ]);
        } catch (\Throwable $exception) {
            return json(['success' => 0, 'message' => $exception->getMessage()], 400);
        }

        return json([
            'success' => 1,
            'media' => $media,
            'file' => ['url' => (string) ($media['public_url'] ?? '')],
        ]);
    }
}
