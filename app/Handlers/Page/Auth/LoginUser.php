<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Auth;

use NixCMS\Models\User;
use NixCMS\Repositories\UserRepository;
use NixCMS\Services\Security\CredentialService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\Form\validator;
use function NixPHP\ORM\repo;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\Session\session;
use function NixPHP\View\render;

final class LoginUser
{
    public function __construct(
        private readonly CredentialService $credentialService,
    ) {
    }

    public function __invoke(): ResponseInterface
    {
        $data = request()->getParsedBody() ?? [];
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $validator = validator()->validate($data, [
            'username' => 'required|min:3',
            'password' => 'required',
        ]);

        if (!$validator->isValid()) {
            return $this->loginView($data, 'Please enter username and password.', $username);
        }

        /** @var User|null $user */
        $user = repo(UserRepository::class)->findOneBy('username', $username);
        $verification = $this->credentialService->verifyPassword($password, $user?->getPassword());

        if (!$user instanceof User || !$verification->isVerified()) {
            return $this->loginView($data, 'Invalid username or password.', $username);
        }

        if ($verification->requiresUpgrade()) {
            repo(UserRepository::class)->upgradePasswordHash((int) $user->getId(), $password);
        }

        session_regenerate_id(true);
        session()->set('userId', $user->getId());

        return redirect($this->resolveRedirectUri($data));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRedirectUri(array $payload): string
    {
        $candidate = trim((string) ($payload['redirectUri'] ?? session()->getFlash('redirectUri') ?? ''));
        if ($candidate === '') {
            return '/admin';
        }

        $parsed = parse_url($candidate);
        if ($parsed === false) {
            return '/admin';
        }

        $path = $parsed['path'] ?? '/admin';
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/admin';
        }

        if (isset($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function loginView(array $payload, string $error, string $username): ResponseInterface
    {
        return render('auth.login', [
            'title' => 'Login',
            'redirectUri' => (string) ($payload['redirectUri'] ?? ''),
            'error' => $error,
            'username' => $username,
        ]);
    }
}
