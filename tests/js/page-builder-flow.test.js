import assert from 'node:assert/strict';
import test from 'node:test';
import {JSDOM} from 'jsdom';

const contentTypes = [
    {
        type: 'section', label: 'Section', description: 'Nested section', regions: ['main'], previewTitle: 'Section',
        defaults: {width: 'default', padding: 'medium', tone: 'none', children: []},
        fields: [
            {name: 'width', type: 'select', options: ['default', 'narrow', 'wide', 'full']},
            {name: 'padding', type: 'select', options: ['none', 'small', 'medium', 'large']},
            {name: 'tone', type: 'select', options: ['none', 'soft', 'card', 'accent']},
            {name: 'children', type: 'children'},
        ],
    },
    {
        type: 'text', label: 'Text block', description: 'Text', regions: ['main'], previewTitle: 'Short text',
        defaults: {text: 'Initial text', tone: 'body', align: 'left', verticalAlign: 'top'},
        fields: [
            {name: 'text', label: 'Text', type: 'textarea'},
            {name: 'tone', type: 'select', options: ['body', 'lead', 'muted']},
            {name: 'align', type: 'select', options: ['left', 'center', 'right']},
            {name: 'verticalAlign', type: 'select', options: ['top', 'center', 'bottom']},
        ],
    },
];

const config = (initialState = {}) => ({
    templates: [{
        id: 'custom', label: 'Custom', description: 'Custom layout', kind: 'custom',
        layout: 'layouts.page', view: 'page.templates.custom', blocks: [],
    }],
    contentTypes,
    themes: [{value: 'default', label: 'Default'}],
    themeSurfaces: {},
    navigationChoices: [],
    languages: ['en'],
    defaultLanguage: 'en',
    uiLanguage: 'en',
    translationEntries: {},
    initialState,
    mode: 'edit',
});

const rootMarkup = (builderConfig) => `
    <main data-page-builder data-save-url="/admin/pages" data-overview-url="/admin/pages"></main>
    <section data-step="template"></section><section data-step="layout"></section><section data-step="meta"></section>
    <div data-template-list></div><div data-template-preview></div><div data-template-preview-title></div>
    <div data-block-canvas></div><div data-filled-count></div><div data-page-preview></div>
    <div data-settings-drawer></div><div data-content-settings></div><pre data-builder-json></pre>
    <div data-preview-stage></div><div data-preview-ruler></div><div data-preview-frame></div>
    <div data-preview-title></div><div data-preview-viewport-label></div><div data-content-width-label></div>
    <button data-save-page></button><div data-meta-template></div><div data-meta-summary></div>
    <div data-meta-preview-title></div><div data-meta-preview-path></div><div data-meta-preview-description></div>`;

const installDom = (builderConfig) => {
    const dom = new JSDOM(`<!doctype html><html lang="en"><head></head><body>${rootMarkup(builderConfig)}</body></html>`, {
        url: 'https://cms.test/admin/pages/create?mode=edit',
    });
    const root = dom.window.document.querySelector('[data-page-builder]');
    root.dataset.config = JSON.stringify(builderConfig);
    for (const element of [...dom.window.document.body.children].slice(1)) {
        root.append(element);
    }
    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.HTMLInputElement = dom.window.HTMLInputElement;
    globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
    globalThis.HTMLTextAreaElement = dom.window.HTMLTextAreaElement;
    globalThis.CSS = {escape: (value) => String(value).replaceAll('"', '\\"')};
    window.requestAnimationFrame = (callback) => callback();
    window.cancelAnimationFrame = () => {};
    window.HTMLElement.prototype.scrollIntoView = () => {};
    return dom;
};

test('custom layout survives add, configure, nested save, reload, preview, publish and validation failure', async () => {
    installDom(config());
    const {PageBuilder} = await import('../../public/js/admin/page-builder/PageBuilder.js');
    const builder = new PageBuilder(document.querySelector('[data-page-builder]'));
    let savedPayload = null;
    window.NixCMS = {
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
    window.NixCMS.json = async () => ({
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
    window.NixCMS = {json: async () => ({ok: true, json: async () => ({success: true, id: 41})})};
    const reloaded = new PageBuilder(document.querySelector('[data-page-builder]'));
    await reloaded.init();

    assert.equal(reloaded.blockContent[slotId].props.children[0].props.text, 'Saved nested copy');
    assert.match(document.querySelector('[data-page-preview]').textContent, /Saved nested copy/);
    assert.equal(reloaded.pagePayload().status, 'published');
});
