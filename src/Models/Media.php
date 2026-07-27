<?php

declare(strict_types=1);

namespace src\Models;

use NixPHP\ORM\Model\AbstractModel;

class Media extends AbstractModel
{
    public string $table = 'media';

    protected ?string $disk = 'public';
    protected ?string $public_id = null;
    protected ?string $visibility = 'public';
    protected ?string $original_name = null;
    protected ?string $stored_name = null;
    protected ?string $storage_path = null;
    protected ?string $file_path = null;
    protected ?string $public_url = null;
    protected ?string $mime_type = null;
    protected ?string $extension = null;
    protected ?int $size_bytes = null;
    protected ?int $width = null;
    protected ?int $height = null;
    protected ?string $alt_text = null;
    protected ?int $uploaded_by = null;
    protected ?string $metadata_json = null;
    protected ?string $created_at = null;
    protected ?string $updated_at = null;

    public function getTableName(bool $singular = false): string
    {
        return 'media';
    }

    public function getPublicUrl(): ?string
    {
        return $this->public_url;
    }

    public function getMimeType(): ?string
    {
        return $this->mime_type;
    }

    public function getAltText(): ?string
    {
        return $this->alt_text;
    }
}
