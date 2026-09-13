<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Media;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use function Naf\I18n\t;
use function Naf\json;
use function Naf\request;
use function Naf\CMS\cms;
use function Naf\CMS\user;

final class UploadMedia
{
    public function __invoke(): ResponseInterface
    {
        $files = $this->uploadedFiles();
        if ($files === []) {
            return json(['success' => false, 'reason' => t('No file was uploaded.')], 400);
        }

        $stored = [];
        $uploadedBy = user()?->get()?->getId();
        $body = request()->getParsedBody();
        $context = [
            'visibility' => is_array($body) ? (string) ($body['visibility'] ?? 'public') : 'public',
            'alt_text' => is_array($body) ? (string) ($body['alt_text'] ?? '') : '',
            'collection' => is_array($body) ? (string) ($body['collection'] ?? '') : '',
        ];

        try {
            foreach ($files as $file) {
                $stored[] = cms()->media()->storeUploadedFile($file, $uploadedBy, $context);
            }
        } catch (\Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 400);
        }

        $first = $stored[0] ?? null;

        return json([
            'success' => true,
            'media' => $first,
            'files' => $stored,
            'file' => ['url' => (string) ($first['public_url'] ?? '')],
        ], 201);
    }

    /**
     * @return array<int, UploadedFileInterface>
     */
    private function uploadedFiles(): array
    {
        $uploadedFiles = request()->getUploadedFiles();
        $candidates = [];

        foreach (['file', 'files', 'media', 'image'] as $field) {
            if (array_key_exists($field, $uploadedFiles)) {
                $candidates[] = $uploadedFiles[$field];
            }
        }

        if ($candidates === []) {
            $candidates = array_values($uploadedFiles);
        }

        $files = [];
        foreach ($candidates as $candidate) {
            $this->collectUploadedFiles($candidate, $files);
        }

        return $files;
    }

    /**
     * @param array<int, UploadedFileInterface> $files
     */
    private function collectUploadedFiles(mixed $candidate, array &$files): void
    {
        if ($candidate instanceof UploadedFileInterface) {
            $files[] = $candidate;
            return;
        }

        if (!is_array($candidate)) {
            return;
        }

        foreach ($candidate as $item) {
            $this->collectUploadedFiles($item, $files);
        }
    }
}
