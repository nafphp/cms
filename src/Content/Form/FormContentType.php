<?php

declare(strict_types=1);

namespace src\Content\Form;

use src\Api\FormApi;
use src\Content\ContentTypeInterface;
use src\Models\Form;
use src\Repositories\FormRepository;

final class FormContentType implements ContentTypeInterface
{
    private ?FormApi $api = null;

    public function id(): string
    {
        return 'form';
    }

    public function label(): string
    {
        return 'Form';
    }

    public function modelClass(): string
    {
        return Form::class;
    }

    public function api(): FormApi
    {
        return $this->api ??= new FormApi(new FormRepository());
    }
}
