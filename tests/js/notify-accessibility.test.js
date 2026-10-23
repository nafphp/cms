import assert from 'node:assert/strict';
import test from 'node:test';
import {config, installDom} from './support/page-builder-dom.js';

test('toasts share an existing polite status region without dialog attributes', async () => {
    installDom(config());
    const {default: toast, ensureToastRegion} = await import('../../public/js/lib/notifyJs/Modules/Toast.js');
    const region = ensureToastRegion();
    assert.equal(region.children.length, 0);
    assert.equal(ensureToastRegion(), region);
    toast('Saved');
    assert.equal(region.getAttribute('role'), 'status');
    assert.equal(region.getAttribute('aria-live'), 'polite');
    assert.equal(region.getAttribute('aria-atomic'), 'false');
    assert.equal(region.firstElementChild.hasAttribute('role'), false);
    assert.equal(region.firstElementChild.hasAttribute('aria-modal'), false);
});

test('modal focus wraps in both directions and returns to its opener', async () => {
    installDom(config());
    const opener = document.querySelector('[data-save-page]');
    opener.focus();
    const {default: alert} = await import('../../public/js/lib/notifyJs/Modules/Alert.js');
    const promise = alert('Problem');
    const dialog = document.querySelector('.notify.alert');
    const confirm = dialog.querySelector('.confirm');
    assert.equal(document.activeElement, confirm);
    const extra = document.createElement('button');
    extra.textContent = 'Extra';
    dialog.append(extra);
    const tab = (shiftKey) => document.dispatchEvent(new window.KeyboardEvent('keydown', {key: 'Tab', shiftKey, cancelable: true}));
    tab(true);
    assert.equal(document.activeElement, extra);
    tab(false);
    assert.equal(document.activeElement, confirm);
    confirm.click();
    await promise;
    dialog.dispatchEvent(new window.Event('animationend'));
    assert.equal(document.activeElement, opener);
});
