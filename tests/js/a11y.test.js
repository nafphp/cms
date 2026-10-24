import assert from 'node:assert/strict';
import test from 'node:test';
import axe from 'axe-core';
import {config, installDom} from './support/page-builder-dom.js';

test('custom builder has no WCAG A/AA violations and slots can move by button', async () => {
    const dom = installDom(config());
    const {PageBuilder} = await import('../../public/js/admin/page-builder/PageBuilder.js');
    const builder = new PageBuilder(document.querySelector('[data-page-builder]'));
    await builder.init();
    builder.addCustomSlot('text');
    builder.addCustomSlot('text');
    const [first, second] = builder.customLayout.map((slot) => slot.id);
    const move = (id, direction) => document.querySelector(`[data-move-custom-slot="${id}"][data-direction="${direction}"]`).click();
    document.querySelector(`[data-move-custom-slot="${second}"][data-direction="-1"]`).focus();
    move(second, -1);
    assert.equal(document.activeElement.dataset.moveCustomSlot, second, 'keyboard focus stays on the moved slot');
    assert.deepEqual(builder.customLayout.map((slot) => slot.id), [second, first]);
    assert.equal(document.querySelector('[data-builder-announcer]').textContent, 'Moved to position 1 of 2');
    move(second, 1);
    assert.deepEqual(builder.customLayout.map((slot) => slot.id), [first, second]);
    assert.equal(document.querySelector('[data-builder-announcer]').textContent, 'Moved to position 2 of 2');
    move(second, 1);
    assert.deepEqual(builder.customLayout.map((slot) => slot.id), [first, second]);
    const results = await axe.run(document.documentElement, {
        runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa']},
        rules: {'color-contrast': {enabled: false}},
    });
    assert.deepEqual(results.violations.map(({id, nodes}) => ({id, elements: nodes.map((node) => node.html)})), []);
    dom.window.close();
});
