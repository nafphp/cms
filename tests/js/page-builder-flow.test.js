import assert from 'node:assert/strict';
import test from 'node:test';
import {config, installDom} from './support/page-builder-dom.js';

test('custom layout survives add, configure, nested save, reload, preview, publish and validation failure', async () => {
    installDom(config());
    const {PageBuilder} = await import('../../public/js/admin/page-builder/PageBuilder.js');
    const builder = new PageBuilder(document.querySelector('[data-page-builder]'));
    let savedPayload = null;
    window.NafCMS = {
        json: async (_url, options) => {
            savedPayload = JSON.parse(options.body);
            return {ok: true, json: async () => ({success: true, id: 41})};
        },
    };

    await builder.init();
    builder.addCustomSlot('section');
    const slotId = builder.selectedBlockId;
    builder.addLayoutChild('', 'text');

    const textInput = document.querySelector('[data-content-settings] [data-prop-name="text"]');
    assert.ok(textInput, 'nested text field is rendered into the DOM');
    textInput.value = 'Saved nested copy';
    textInput.dispatchEvent(new window.Event('input', {bubbles: true}));
    builder.persistedValues.title = 'Preview page';
    builder.persistedValues.path = '/preview-page';
    builder.persistedValues.status = 'published';

    assert.match(document.querySelector('[data-page-preview]').textContent, /Saved nested copy/);
    await builder.remotePersist();
    assert.equal(savedPayload.builderVersion, 1);
    assert.equal(savedPayload.status, 'published');
    assert.equal(savedPayload.blocks[slotId].props.children[0].props.text, 'Saved nested copy');

    const beforeRejectedSave = structuredClone(builder.blockContent);
    window.NafCMS.json = async () => ({
        ok: false,
        json: async () => ({success: false, errors: {blocks: ['Invalid select value.']}}),
    });
    await builder.remotePersist();
    assert.deepEqual(builder.blockContent, beforeRejectedSave, 'validation errors retain the editable DOM state');

    installDom(config({
        id: 41,
        templateId: 'custom',
        blockContent: savedPayload.blocks,
        customLayout: savedPayload.meta.custom_layout,
        persistedValues: {
            title: savedPayload.title,
            path: savedPayload.path,
            language: savedPayload.language,
            status: savedPayload.status,
            themeId: savedPayload.themeId,
        },
    }));
    window.NafCMS = {json: async () => ({ok: true, json: async () => ({success: true, id: 41})})};
    const reloaded = new PageBuilder(document.querySelector('[data-page-builder]'));
    await reloaded.init();

    assert.equal(reloaded.blockContent[slotId].props.children[0].props.text, 'Saved nested copy');
    assert.match(document.querySelector('[data-page-preview]').textContent, /Saved nested copy/);
    assert.equal(reloaded.pagePayload().status, 'published');
});

test('stale navigation identifiers are reconciled before a page is saved', async () => {
    const builderConfig = config({
        id: 42,
        templateId: 'default',
        blockContent: {
            site_header: {
                type: 'header',
                label: 'Header',
                previewTitle: 'Site header',
                props: {navigationIdentifier: 'removed-navigation', presentation: 'horizontal'},
            },
        },
        persistedValues: {
            title: 'Existing page',
            path: '/existing',
            language: 'en',
            status: 'draft',
            defaultNavigationIdentifier: 'removed-navigation',
        },
    });
    builderConfig.templates = [{
        id: 'default',
        label: 'Default',
        layout: 'layouts.page',
        view: 'page.templates.default',
        blocks: [{id: 'site_header', region: 'header', accepts: ['header']}],
    }];
    builderConfig.navigationChoices = [{
        identifier: 'main',
        label: 'Main navigation',
        language: '',
        items: [],
    }];
    builderConfig.defaultNavigationIdentifier = 'main';

    installDom(builderConfig);
    const {PageBuilder} = await import('../../public/js/admin/page-builder/PageBuilder.js');
    const builder = new PageBuilder(document.querySelector('[data-page-builder]'));
    await builder.init();

    assert.equal(builder.persistedValues.defaultNavigationIdentifier, 'main');
    assert.equal(builder.blockContent.site_header.props.navigationIdentifier, 'main');
    assert.equal(builder.pagePayload().blocks.site_header.props.navigationIdentifier, 'main');
});

test('alerts use their default confirmation label', async () => {
    installDom(config());
    const {default: alert} = await import('../../public/js/lib/notifyJs/Modules/Alert.js');
    const closed = alert('Could not save.');
    const button = document.querySelector('.notify.alert .confirm');

    assert.equal(button?.textContent, 'Okay');
    button?.click();
    await closed;
});
