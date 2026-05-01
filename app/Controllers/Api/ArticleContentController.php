<?php

declare(strict_types=1);

namespace Cms\Controllers\Api;

use Cms\Enums\Language;
use Cms\Enums\Permission;
use Cms\Exceptions\AuthException;
use Cms\Services\Content\ArticleFormService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use function NixPHP\json;
use function NixPHP\request;

final class ArticleContentController
{
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function nextVersion(string $articleId, string $language): ResponseInterface
    {
        $this->requirePermission(Permission::ARTICLES_CREATE);

        return json([
            'success' => true,
            'version' => (new ArticleFormService())->nextVersion((int) $articleId, $language),
        ]);
    }

    public function store(): ResponseInterface
    {
        $data = $this->jsonBody();
        $this->requirePermission(
            (int) ($data['article_id'] ?? 0) > 0 ? Permission::ARTICLES_EDIT : Permission::ARTICLES_CREATE
        );

        $errors = $this->validate($data);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        try {
            $articleId = (int) ($data['article_id'] ?? 0);
            $authorId = \user()?->get()?->getId();

            $result = $articleId > 0
                ? \cms()->articles()->draft($articleId, $data, $authorId)
                : \cms()->articles()->create($data, $authorId);

            return json([
                'success' => true,
                'articleId' => $result['articleId'],
                'articleVersionId' => $result['articleVersionId'],
            ]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }

    public function uploadImage(): ResponseInterface
    {
        if (\user()?->can(Permission::MEDIA_UPLOAD) !== true && \user()?->can(Permission::ARTICLES_EDIT) !== true) {
            throw new AuthException();
        }

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

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $body = (string) request()->getBody();
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    private function validate(array $data): array
    {
        $errors = [];
        if (mb_strlen(trim((string) ($data['title'] ?? ''))) < 10) {
            $errors['title'][] = 'Please enter a title with at least 10 characters.';
        }
        if (mb_strlen(trim((string) ($data['slug'] ?? ''))) < 5) {
            $errors['slug'][] = 'Please enter a slug with at least 5 characters.';
        }
        $teaser = trim((string) ($data['teaser'] ?? ''));
        if ($teaser !== '' && mb_strlen($teaser) < 60) {
            $errors['teaser'][] = 'Please enter a teaser with at least 60 characters.';
        }
        if (Language::tryFrom(strtolower(trim((string) ($data['language'] ?? '')))) === null) {
            $errors['language'][] = 'Please choose a valid language.';
        }
        if (!isset($data['content']) || !is_array($data['content'])) {
            $errors['content'][] = 'Please add article content.';
        }

        return $errors;
    }

    private function requirePermission(Permission $permission): void
    {
        if (\user()?->can($permission) !== true) {
            throw new AuthException();
        }
    }
}
