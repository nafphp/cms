import assert from 'node:assert/strict';
import test from 'node:test';
import {JSDOM} from 'jsdom';

import {
    COLOR_SCHEME_EVENT,
    COLOR_SCHEME_STORAGE_KEY,
    initColorScheme,
    isColorSchemeShortcut,
} from '../../public/js/components/colorScheme.js';

const setup = (scheme = 'dark') => {
    const dom = new JSDOM(`<!doctype html><html class="${scheme === 'dark' ? 'dark' : ''}"><body>
        <input id="field">
        <button type="button" data-color-scheme-toggle><span id="icon"></span></button>
    </body></html>`, {url: 'https://cms.test/admin'});
    initColorScheme(dom.window.document);

    return dom;
};

const press = (dom, init = {}, target = dom.window.document.body) => {
    const event = new dom.window.KeyboardEvent('keydown', {key: 'F9', bubbles: true, cancelable: true, ...init});
    target.dispatchEvent(event);

    return event;
};

const scheme = (dom) => (dom.window.document.documentElement.classList.contains('dark') ? 'dark' : 'light');

test('F9 toggles between dark and light and remembers the choice', () => {
    const dom = setup('dark');

    const event = press(dom);
    assert.equal(scheme(dom), 'light');
    assert.equal(event.defaultPrevented, true, 'F9 must not also open the browser reader view');
    assert.equal(dom.window.localStorage.getItem(COLOR_SCHEME_STORAGE_KEY), 'light');
    assert.equal(dom.window.document.documentElement.style.colorScheme, 'light');

    press(dom);
    assert.equal(scheme(dom), 'dark');
    assert.equal(dom.window.localStorage.getItem(COLOR_SCHEME_STORAGE_KEY), 'dark');
});

test('F9 also works while a form field has focus', () => {
    const dom = setup('dark');
    const field = dom.window.document.getElementById('field');
    field.focus();

    press(dom, {}, field);
    assert.equal(scheme(dom), 'light');
});

test('modified, repeated, and already handled F9 presses are ignored', () => {
    const dom = setup('dark');

    for (const init of [{ctrlKey: true}, {altKey: true}, {metaKey: true}, {shiftKey: true}, {repeat: true}]) {
        const event = press(dom, init);
        assert.equal(scheme(dom), 'dark', JSON.stringify(init));
        assert.equal(event.defaultPrevented, false, JSON.stringify(init));
    }

    dom.window.document.body.addEventListener('keydown', (event) => event.preventDefault(), {once: true});
    press(dom);
    assert.equal(scheme(dom), 'dark');

    assert.equal(isColorSchemeShortcut({key: 'F8'}), false);
});

test('the toggle button switches the scheme and announces the change', () => {
    const dom = setup('light');
    const announced = [];
    dom.window.document.addEventListener(COLOR_SCHEME_EVENT, (event) => announced.push(event.detail.scheme));

    dom.window.document.getElementById('icon').click();

    assert.equal(scheme(dom), 'dark');
    assert.deepEqual(announced, ['dark']);
});

test('binding twice does not toggle twice', () => {
    const dom = setup('dark');
    initColorScheme(dom.window.document);

    press(dom);
    assert.equal(scheme(dom), 'light');
});

test('a switch in another tab is applied without writing back', () => {
    const dom = setup('dark');

    dom.window.dispatchEvent(new dom.window.StorageEvent('storage', {key: COLOR_SCHEME_STORAGE_KEY, newValue: 'light'}));
    assert.equal(scheme(dom), 'light');
    assert.equal(dom.window.localStorage.getItem(COLOR_SCHEME_STORAGE_KEY), null);

    dom.window.dispatchEvent(new dom.window.StorageEvent('storage', {key: COLOR_SCHEME_STORAGE_KEY, newValue: 'bogus'}));
    assert.equal(scheme(dom), 'light');
});


test('system changes apply without persistence until the user chooses a scheme', () => {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', {url: 'https://cms.test/admin'});
    const media = new dom.window.EventTarget();
    dom.window.matchMedia = () => media;
    initColorScheme(dom.window.document);
    const change = (matches) => {
        const event = new dom.window.Event('change');
        event.matches = matches;
        media.dispatchEvent(event);
    };
    change(true);
    assert.equal(scheme(dom), 'dark');
    assert.equal(dom.window.localStorage.getItem(COLOR_SCHEME_STORAGE_KEY), null);
    change(false);
    assert.equal(scheme(dom), 'light');
    press(dom);
    assert.equal(scheme(dom), 'dark');
    change(false);
    assert.equal(scheme(dom), 'dark', 'an explicit choice wins over the system');
    dom.window.close();
});
