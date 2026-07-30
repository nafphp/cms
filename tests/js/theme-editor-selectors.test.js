import assert from 'node:assert/strict';
import test from 'node:test';

import {
    findThemeSelectorIds,
    findThemeSelectorIdsInPrelude,
    replaceThemeSelectors,
    replaceThemeClassInSelectorPrelude,
} from '../../public/js/admin/theme-editor/selectors.js';

/*
 * These functions decide whether renaming a theme rewrites the author's CSS.
 * A false positive silently edits a comment or a string literal, a false
 * negative leaves the stylesheet pointing at a theme that no longer exists —
 * which is why they parse the CSS character by character instead of using a
 * regex, and why the awkward cases below are pinned down.
 */

test('finds theme ids in plain selectors', () => {
    assert.deepEqual(findThemeSelectorIds('.theme-dark { color: red; }'), ['dark']);
    assert.deepEqual(
        findThemeSelectorIds('.theme-dark .page-theme, .theme-light a { color: red; }'),
        ['dark', 'light'],
    );
});

test('ignores theme ids inside comments', () => {
    assert.deepEqual(findThemeSelectorIds('/* .theme-ghost */ .theme-real { color: red; }'), ['real']);
});

test('ignores theme ids inside quoted strings', () => {
    assert.deepEqual(
        findThemeSelectorIds('.theme-real::after { content: ".theme-ghost"; }'),
        ['real'],
    );
});

test('ignores declaration bodies entirely', () => {
    assert.deepEqual(
        findThemeSelectorIds('.theme-real { background: url("x.theme-ghost.png"); }'),
        ['real'],
    );
});

test('does not treat a longer class name as a theme id', () => {
    assert.deepEqual(findThemeSelectorIdsInPrelude('.theme-darkish-extra'), ['darkish-extra']);
    assert.deepEqual(findThemeSelectorIdsInPrelude('.not-theme-dark'), []);
    assert.deepEqual(findThemeSelectorIdsInPrelude('.mytheme-dark'), []);
});

test('renames a theme selector', () => {
    assert.equal(
        replaceThemeSelectors('.theme-old { color: red; }', 'old', 'new'),
        '.theme-new { color: red; }',
    );
});

test('renaming leaves comments and strings untouched', () => {
    const css = '/* .theme-old */ .theme-old::after { content: ".theme-old"; }';
    assert.equal(
        replaceThemeSelectors(css, 'old', 'new'),
        '/* .theme-old */ .theme-new::after { content: ".theme-old"; }',
    );
});

test('renaming is a no-op when ids are missing or equal', () => {
    const css = '.theme-old { color: red; }';
    assert.equal(replaceThemeSelectors(css, 'old', 'old'), css);
    assert.equal(replaceThemeSelectors(css, '', 'new'), css);
    assert.equal(replaceThemeSelectors(css, 'old', ''), css);
});

test('renaming does not touch a partially matching class', () => {
    assert.equal(
        replaceThemeClassInSelectorPrelude('.theme-old-extra .theme-old', 'old', 'new'),
        '.theme-old-extra .theme-new',
    );
});

test('renaming rewrites every occurrence in one prelude', () => {
    assert.equal(
        replaceThemeClassInSelectorPrelude('.theme-old a, .theme-old b', 'old', 'new'),
        '.theme-new a, .theme-new b',
    );
});
