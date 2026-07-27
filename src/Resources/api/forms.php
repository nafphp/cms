<?php

declare(strict_types=1);

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Api\Form\DeleteForm;
use NixPHP\CMS\Handlers\Api\Form\SubmitForm;

return [
    ApiEndpoint::post('api.admin.forms.delete', '/api/admin/forms/{id}/delete')
        ->requirePermission(Permission::FORMS_DELETE)
        ->setHandler(DeleteForm::class),

    ApiEndpoint::post('api.forms.submit', '/api/forms/{identifier}/submit')
        ->setHandler(SubmitForm::class),
];
