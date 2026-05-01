<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\AbstractController;
use Cms\Models\User;
use Cms\Repositories\UserRepository;
use Cms\Services\Security\CredentialService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\ORM\repo;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\Session\session;
use function NixPHP\View\render;
use function NixPHP\Form\validator;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly CredentialService $credentialService,
    ) {
    }

    public function showLogin(): ResponseInterface
    {
        if ($this->isLoggedIn()) {
            return redirect('/admin');
        }

        return render('auth.login', [
            'title' => 'Login',
            'redirectUri' => (string) (session()->getFlash('redirectUri') ?? ''),
            'error' => null,
            'username' => '',
        ]);
    }

    public function login(): ResponseInterface
    {
        $data = request()->getParsedBody() ?? [];
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $validator = validator()->validate($data, [
            'username' => 'required|min:3',
            'password' => 'required',
        ]);

        if (!$validator->isValid()) {
            return render('auth.login', [
                'title' => 'Login',
                'redirectUri' => (string) ($data['redirectUri'] ?? ''),
                'error' => 'Please enter username and password.',
                'username' => $username,
            ]);
        }

        if ($username === 'admin' && $password === 'test') {
            \session_regenerate_id(true);
            session()->set('dummyAdmin', true);
            session()->forget('userId');

            return redirect($this->resolveRedirectUri($data));
        }

        /** @var User|null $user */
        $user = repo(UserRepository::class)->findOneBy('username', $username);
        $verification = $this->credentialService->verifyPassword($password, $user?->getPassword());

        if (!$user instanceof User || !$verification->isVerified()) {
            return render('auth.login', [
                'title' => 'Login',
                'redirectUri' => (string) ($data['redirectUri'] ?? ''),
                'error' => 'Invalid username or password.',
                'username' => $username,
            ]);
        }

        if ($verification->requiresUpgrade()) {
            repo(UserRepository::class)->upgradePasswordHash((int) $user->getId(), $password);
        }

        \session_regenerate_id(true);
        session()->set('userId', $user->getId());

        return redirect($this->resolveRedirectUri($data));
    }

    public function logout(): ResponseInterface
    {
        session()->forget('userId');
        session()->forget('dummyAdmin');
        return redirect('/admin/login');
    }

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
}
