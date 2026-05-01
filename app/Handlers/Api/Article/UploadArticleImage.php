<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use function NixPHP\json;
use function NixPHP\request;

final class UploadArticleImage
{
    private const int MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    private const array ALLOWED_MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __invoke(): ResponseInterface
    {
        $file = request()->getUploadedFiles()['image'] ?? null;
        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return json(['success' => 0, 'message' => 'Upload failed.'], 400);
        }

        $size = $file->getSize();
        if ($size === null || $size <= 0 || $size > self::MAX_IMAGE_SIZE) {
            return json(['success' => 0, 'message' => 'File is larger than 5 MB.'], 400);
        }

        $mediaType = $file->getClientMediaType() ?? '';
        if (!in_array($mediaType, self::ALLOWED_MEDIA_TYPES, true)) {
            return json(['success' => 0, 'message' => 'Invalid file type.'], 400);
        }

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return json(['success' => 0, 'message' => 'Invalid file extension.'], 400);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetDir = BASE_PATH . '/public/images/article';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->moveTo($targetDir . '/' . $filename);

        return json([
            'success' => 1,
            'file' => ['url' => '/images/article/' . $filename],
        ]);
    }
}
