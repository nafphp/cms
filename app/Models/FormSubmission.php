<?php

declare(strict_types=1);

namespace NixCMS\Models;

use NixPHP\ORM\Model\AbstractModel;

class FormSubmission extends AbstractModel
{
    protected ?int $form_id = null;
    protected ?string $payload_json = null;
    protected ?string $meta_json = null;
    protected ?string $created_at = null;

    public function getFormId(): ?int
    {
        return $this->form_id;
    }

    public function getPayloadJson(): ?string
    {
        return $this->payload_json;
    }

    public function getMetaJson(): ?string
    {
        return $this->meta_json;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }
}
