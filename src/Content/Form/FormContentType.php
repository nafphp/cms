<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Form;

use Naf\CMS\Api\FormApi;
use Naf\CMS\Content\ContentTypeInterface;
use Naf\CMS\Models\Form;
use Naf\CMS\Repositories\FormRepository;

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
        if ($this->api === null) {
            $repository = new FormRepository();
            $this->api  = new FormApi($repository);
        }

        return $this->api;
    }
}
