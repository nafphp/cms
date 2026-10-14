import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));

test('Tailwind includes package templates and excludes unrelated files', () => {
    const output = fs.mkdtempSync(path.join(os.tmpdir(), 'nafcms-tailwind-'));
    const noise = fs.mkdtempSync(path.join(root, 'tailwind-noise-'));
    const template = path.join(root, 'views', `__tailwind-regression-${process.pid}.phtml`);
    let templateCreated = false;

    try {
        fs.writeFileSync(template, '<div class="z-[98761]"></div>', { flag: 'wx' });
        templateCreated = true;
        fs.writeFileSync(path.join(noise, 'unrelated.html'), '<div class="z-[98762]"></div>');

        const result = spawnSync(process.execPath, [
            path.join(root, 'node_modules/@tailwindcss/cli/dist/index.mjs'),
            '-i', 'src/Resources/css/nafcms.css',
            '-o', path.join(output, 'built.css'),
            '--minify',
        ], { cwd: root, encoding: 'utf8', timeout: 30000 });

        assert.equal(result.status, 0, result.stderr);

        const css = fs.readFileSync(path.join(output, 'built.css'), 'utf8');
        assert.ok(css.includes('z-index:98761'), 'Template-only Tailwind utility was not generated');
        assert.ok(!css.includes('z-index:98762'), 'Unrelated local files changed the package build');
    } finally {
        if (templateCreated) fs.rmSync(template, { force: true });
        fs.rmSync(noise, { recursive: true, force: true });
        fs.rmSync(output, { recursive: true, force: true });
    }
});
