<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

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

        if (\user()?->get()?->getId() === $userId) {
            return json(['success' => false, 'reason' => 'You cannot delete your own account.'], 400);
        }

        if (!repo(UserRepository::class)->deleteById($userId)) {
            return json(['success' => false, 'reason' => 'Could not delete user.'], 400);
        }

        return json(['success' => true]);
    }
}
