<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Media;

use NixPHP\CMS\Enums\Permission;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\I18n\t;
use function NixPHP\response;
use function NixPHP\CMS\cms;
use function NixPHP\CMS\user;

final class ServeMedia
{
    public function __invoke(string $publicId, string $filename): ResponseInterface
    {
        $media = cms()->media()->findByPublicId($publicId);
        if (!is_array($media)) {
            return response(t('Media not found.'), 404);
        }

        $storedName = (string) ($media['stored_name'] ?? '');
        if ($storedName === '' || rawurldecode($filename) !== $storedName) {
            return response(t('Media not found.'), 404);
        }

        if (!$this->canAccess($media)) {
            return response(t('Forbidden.'), 403);
        }

        $path = cms()->media()->pathFor($media);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return response(t('Media file not found.'), 404);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return response(t('Media file not readable.'), 404);
        }

        return response(Stream::create($handle), 200, [
            'Content-Type' => (string) ($media['mime_type'] ?? 'application/octet-stream'),
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'inline; filename="' . $this->headerFilename($storedName) . '"',
            'Cache-Control' => $this->cacheControl($media),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param array<string, mixed> $media
     */
    private function canAccess(array $media): bool
    {
        $visibility = strtolower((string) ($media['visibility'] ?? 'public'));
        if ($visibility === 'public') {
            return true;
        }

        if ($visibility === 'registered' || $visibility === 'admin') {
            return user()?->isLoggedIn() === true;
        }

        if ($visibility === 'private') {
            $helper = user();
            if ($helper?->isLoggedIn() !== true) {
                return false;
            }

            return $this->canAccessPrivate(
                $media,
                $helper->get()?->getId(),
                $helper->can(Permission::MEDIA_VIEW) === true,
            );
        }

        return false;
    }

    /**
     * @param array<string, mixed> $media
     */
    private function canAccessPrivate(array $media, ?int $currentUserId, bool $canViewMedia): bool
    {
        $uploadedBy = (int) ($media['uploaded_by'] ?? 0);

        return $canViewMedia
            || ($currentUserId !== null && $currentUserId > 0 && $uploadedBy === $currentUserId);
    }

    /**
     * @param array<string, mixed> $media
     */
    private function cacheControl(array $media): string
    {
        return strtolower((string) ($media['visibility'] ?? 'public')) === 'public'
            ? 'public, max-age=31536000, immutable'
            : 'private, no-store';
    }

    private function headerFilename(string $filename): string
    {
        return str_replace(['\\', '"', "\r", "\n"], '-', $filename);
    }
}
