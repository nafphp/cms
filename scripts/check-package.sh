#!/usr/bin/env bash

set -euo pipefail

ROOT="${1:-$(pwd)}"
OUTPUT="$(mktemp -d "${TMPDIR:-/tmp}/nixcms-archive.XXXXXX")"
trap 'rm -rf "$OUTPUT"' EXIT

(cd "$ROOT" && composer archive --format=tar --file=nixcms --dir="$OUTPUT" --no-interaction)
CONTENTS="$(tar -tf "$OUTPUT/nixcms.tar")"

if grep -E '(^|/)(\.env(\.local)?$|logs/|storage/|\.idea/|\.claude/|node_modules/|\.phpunit[^/]*|tests/|public/(css|js)/themes/)|^prompts/' <<<"$CONTENTS"; then
    echo "Release archive contains excluded development or runtime files." >&2
    exit 1
fi

for required in composer.json LICENSE README.md SECURITY.md CHANGELOG.md RELEASE_NOTES_0.0.1.md RELEASE_CHECKLIST.md bootstrap.php src/config.php src/routes.php src/functions.php views src/Migrations public/css public/js; do
    if ! grep -Eq "(^|/)${required}(/|$)" <<<"$CONTENTS"; then
        echo "Release archive is missing ${required}." >&2
        exit 1
    fi
done

echo "Release archive contents passed."
