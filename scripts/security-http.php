<?php

declare(strict_types=1);

use Naf\CMS\Repositories\UserRepository;

use function Naf\ORM\repo;

define('BASE_PATH', $argv[1]);
require BASE_PATH . '/vendor/autoload.php';
Naf\app();
$password    = 'CMS disposable original password!';
$replacement = 'CMS disposable replacement password!';
$user        = repo(UserRepository::class)->createBootstrapUserWithRoles('security-admin', $password, null, null, ['admin'], 'security@example.test');
if ($user === null) {
    throw new RuntimeException('Could not create the disposable security-test administrator.');
}
$base = $argv[2];

function client(): CurlHandle
{
    $handle = curl_init();
    curl_setopt($handle, CURLOPT_COOKIEFILE, '');

    return $handle;
}

function request(CurlHandle $handle, string $url, ?array $data = null, ?string $csrf = null): array
{
    $headers = [];
    curl_setopt_array($handle, [
        CURLOPT_URL            => $url,
        CURLOPT_CUSTOMREQUEST  => $data === null ? 'GET' : 'POST',
        CURLOPT_POSTFIELDS     => $data === null ? null : ($csrf === null ? http_build_query($data) : json_encode($data, JSON_THROW_ON_ERROR)),
        CURLOPT_HTTPHEADER     => $csrf === null ? [] : ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value]                   = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            return strlen($line);
        },
    ]);
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException('Security HTTP fixture failed: ' . curl_error($handle));
    }

    return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $headers, $body];
}

function login(CurlHandle $client, string $base, string $password, string $target = '/admin'): array
{
    $form = request($client, $base . '/admin/login');
    if (preg_match('/name="_csrf" value="([^"]+)"/', $form[2], $token) !== 1) {
        throw new RuntimeException('The real login form supplied no CSRF token.');
    }
    $answer = request($client, $base . '/admin/login', ['_csrf' => $token[1], 'username' => 'security-admin', 'password' => $password, 'redirectUri' => $target]);

    return [$answer, $token[1]];
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

$operator         = client();
$copiedSession    = client();
[$answer, $token] = login($operator, $base, $password, '/\\external.example.test/path');
check($answer[0] === 302 && $answer[1]['location'] === '/admin', 'login rejects an external backslash redirect');
[$answer] = login($copiedSession, $base, $password);
check($answer[0] === 302 && request($copiedSession, $base . '/admin')[0] === 200, 'a second authenticated browser session works before reset');
$users = request($operator, $base . '/api/admin/users');
check($users[0] === 200 && in_array('security-admin', array_column(json_decode($users[2], true, flags: JSON_THROW_ON_ERROR), 'username'), true), 'the authenticated user-list API works on SQLite');
$payload = ['user_id' => $user->getId(), 'password' => $replacement, 'password_confirmation' => $replacement];
check(request($operator, $base . '/api/admin/users/password', $payload, 'invalid')[0] === 400, 'password reset retains CSRF protection');
$reset = request($operator, $base . '/api/admin/users/password', $payload, $token);
check($reset[0] === 200 && (json_decode($reset[2], true)['success'] ?? false) === true, 'the real authorized password-reset endpoint succeeds');
$expired = request($copiedSession, $base . '/admin');
check($expired[0] === 302 && $expired[1]['location'] === '/admin/login', 'a pre-reset browser session loses protected access');
[$answer] = login(client(), $base, $password);
check($answer[0] === 200 && str_contains($answer[2], 'Invalid username or password.'), 'the old password no longer signs in');
[$answer] = login(client(), $base, $replacement);
check($answer[0] === 302 && $answer[1]['location'] === '/admin', 'fresh authentication with the replacement password works');
