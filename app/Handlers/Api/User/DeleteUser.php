<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\UserRepository;
use NixCMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixCMS\user;

final class DeleteUser
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $userId = (int) ($data['userId'] ?? 0);
        if ($userId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid user id.'], 400);
        }

        if (user()?->get()?->getId() === $userId) {
            return json(['success' => false, 'reason' => 'You cannot delete your own account.'], 400);
        }

        $repository = repo(UserRepository::class);
        $old = $repository->findOneForForm($userId);
        if (!$repository->deleteById($userId)) {
            return json(['success' => false, 'reason' => 'Could not delete user.'], 400);
        }

        AuditEntry::create('user.deleted', 'user', (string) $userId, $old)->record();

        return json(['success' => true]);
    }
}
