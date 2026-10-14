<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Page\Auth;

use Naf\Auth\Credentials\PasswordCredentials;
use Naf\CMS\Contracts\RateLimiterInterface;
use Naf\CMS\Services\Security\RateLimitDecision;
use Psr\Http\Message\ResponseInterface;

use function Naf\Auth\auth;
use function Naf\Form\validator;
use function Naf\redirect;
use function Naf\request;
use function Naf\Session\session;
use function Naf\View\render;

final class LoginUser
{
    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
    ) {
    }

    public function __invoke(): ResponseInterface
    {
        $data      = request()->getParsedBody() ?? [];
        $username  = trim((string) ($data['username'] ?? ''));
        $password  = (string) ($data['password'] ?? '');
        $ipAddress = (string) (request()->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        $validator = validator()->validate($data, [
            'username' => 'required|min:3',
            'password' => 'required',
        ]);

        if (!$validator->isValid()) {
            return $this->loginView($data, 'Please enter username and password.', $username);
        }

        $limit = $this->rateLimiter->inspect($username, $ipAddress);
        if (!$limit->allowed) {
            return $this->limitedLoginView($data, $username, $limit);
        }

        if (!auth()->authenticate(new PasswordCredentials($username, $password), 'database')) {
            $limit = $this->rateLimiter->recordFailure($username, $ipAddress);
            if (!$limit->allowed) {
                return $this->limitedLoginView($data, $username, $limit);
            }

            return $this->loginView($data, 'Invalid username or password.', $username);
        }

        $this->rateLimiter->clearSuccessfulPair($username, $ipAddress);

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
            'title'       => 'Login',
            'redirectUri' => (string) ($payload['redirectUri'] ?? ''),
            'error'       => $error,
            'username'    => $username,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function limitedLoginView(array $payload, string $username, RateLimitDecision $limit): ResponseInterface
    {
        return $this->loginView($payload, 'Invalid username or password.', $username)
            ->withStatus(429)
            ->withHeader('Retry-After', (string) max(1, $limit->retryAfter));
    }
}
