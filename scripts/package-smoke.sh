#!/usr/bin/env bash

set -euo pipefail

PACKAGE_ROOT="${1:-$(pwd)}"
FIXTURE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/nafcms-host.XXXXXX")"
PORT="${NAFCMS_SMOKE_PORT:-18089}"
PHP_RUN=(php -d error_reporting=8191)
SERVER_PID=""

cleanup() {
    if [[ -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" >/dev/null 2>&1 || true
        wait "$SERVER_PID" >/dev/null 2>&1 || true
        SERVER_PID=""
    fi
    rm -rf "$FIXTURE_ROOT"
}
trap cleanup EXIT

composer create-project naf/app "$FIXTURE_ROOT" --no-interaction --no-progress
composer config repositories.nafcms path "$PACKAGE_ROOT" --working-dir="$FIXTURE_ROOT"
COMPOSER_MIRROR_PATH_REPOS=1 composer require naf/cms:@dev --with-all-dependencies --no-interaction --no-progress --working-dir="$FIXTURE_ROOT"

if [[ -L "$FIXTURE_ROOT/vendor/naf/cms" ]]; then
    echo "Expected a mirrored package install, got a symlink." >&2
    exit 1
fi

cat > "$FIXTURE_ROOT/app/plugins.php" <<'PHP'
<?php

return [
    'naf/cli',
    'naf/database',
    'naf/session',
    'naf/form',
    'naf/view',
    'naf/i18n',
    'naf/orm',
    'naf/queue',
    'naf/schedule',
    'naf/mail',
    'naf/mcp',
    'naf/cms',
];
PHP

cat > "$FIXTURE_ROOT/app/config.php" <<'PHP'
<?php

return [
    'database' => [
        'driver' => 'sqlite',
        'database' => BASE_PATH . '/storage/nafcms-smoke.sqlite',
    ],
];
PHP

mkdir -p "$FIXTURE_ROOT/storage"

package_hash() {
    "${PHP_RUN[@]}" -r '
        $root = $argv[1];
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[str_replace("\\", "/", substr($file->getPathname(), strlen($root) + 1))] = hash_file("sha256", $file->getPathname());
            }
        }
        ksort($files);
        echo hash("sha256", json_encode($files, JSON_THROW_ON_ERROR));
    ' "$FIXTURE_ROOT/vendor/naf/cms"
}

BEFORE_HASH="$(package_hash)"

(cd "$FIXTURE_ROOT" && "${PHP_RUN[@]}" vendor/bin/naf nafcms:assets:publish)
(cd "$FIXTURE_ROOT" && "${PHP_RUN[@]}" vendor/bin/naf nafcms:assets:check)
(cd "$FIXTURE_ROOT" && "${PHP_RUN[@]}" vendor/bin/naf nafcms:agents:install)
grep -q 'managed-by: naf/cms agent-guide' "$FIXTURE_ROOT/AGENTS.md"
(cd "$FIXTURE_ROOT" && "${PHP_RUN[@]}" vendor/bin/naf db:migrate up)

(cd "$FIXTURE_ROOT" && "${PHP_RUN[@]}" -r '
    define("BASE_PATH", getcwd());
    require "vendor/autoload.php";
    $app = Naf\app();
    $plugin = $app->getPlugin("naf/cms");
    $before = [count(Naf\CMS\cms()->pages()->all()), count(Naf\CMS\cms()->blockTypes()->all())];
    $plugin->boot();
    $after = [count(Naf\CMS\cms()->pages()->all()), count(Naf\CMS\cms()->blockTypes()->all())];
    if ($before !== $after) throw new RuntimeException("Plugin bootstrap is not idempotent.");
    if (Naf\View\render("page.blocks.empty")->getStatusCode() !== 200) throw new RuntimeException("Plugin view failed.");
    if (Naf\CMS\cms()->pages()->require("admin.dashboard")->path() !== "/admin") throw new RuntimeException("Admin route missing.");
    if ($app->container()->get(Naf\CLI\Support\CommandRegistry::class)->get("user:create") === null) throw new RuntimeException("CMS command missing.");
    $identity = new Naf\MCP\Auth\McpIdentity("smoke", "Smoke", ["system:read"]);
    $health = $app->container()->get(Naf\MCP\Support\ToolRegistry::class)->call("cms_system_health", [], $identity);
    if (($health["database"]["available"] ?? false) !== true) throw new RuntimeException("Scoped MCP health tool failed.");
    (new Naf\CMS\Services\Ai\LocalAiPromptLibrary())->savePromptFile("IDENTITY.md", "Host override");
    (new Naf\CMS\Services\Settings\TranslationFileService())->save("de", ["Dashboard" => "Host-Dashboard"]);
    (new Naf\CMS\Services\Theme\ThemeAssetWriter())->write("smoke", ".theme-smoke { color: red; }");
')

AFTER_HASH="$(package_hash)"
if [[ "$BEFORE_HASH" != "$AFTER_HASH" ]]; then
    echo "Normal CMS writes changed files inside vendor/naf/cms." >&2
    exit 1
fi

(cd "$FIXTURE_ROOT" && "${PHP_RUN[@]}" -S "127.0.0.1:$PORT" -t public >"$FIXTURE_ROOT/server.log" 2>&1) &
SERVER_PID=$!

for _ in {1..30}; do
    if curl --silent --output /dev/null "http://127.0.0.1:$PORT/admin/login"; then
        break
    fi
    sleep 0.2
done

HEADERS="$(curl --silent --dump-header - --output /dev/null "http://127.0.0.1:$PORT/admin")"
if ! grep -Eq '^HTTP/(1\.[01]|2) 30[23]' <<<"$HEADERS" || ! grep -Eiq '^Location: /admin/login' <<<"$HEADERS"; then
    echo "Expected unauthenticated /admin to redirect to /admin/login." >&2
    echo "$HEADERS" >&2
    exit 1
fi

echo "NafCMS package smoke passed in $FIXTURE_ROOT"
