import {JSDOM, VirtualConsole} from 'jsdom';

const contentTypes = [
    {
        type: 'header', label: 'Header', description: 'Site header', regions: ['header'], previewTitle: 'Site header',
        defaults: {navigationIdentifier: '', presentation: 'horizontal'},
        fields: [
            {name: 'navigationIdentifier', type: 'select', options: [
                {label: 'No navigation', value: ''},
                {label: 'Main navigation', value: 'main'},
            ]},
            {name: 'presentation', type: 'select', options: ['horizontal', 'compact']},
        ],
    },
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

export const config = (initialState = {}) => ({
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

export const rootMarkup = (builderConfig) => `
    <main data-page-builder data-save-url="/admin/pages" data-overview-url="/admin/pages"></main>
    <section data-step="template"></section><section data-step="layout"></section><section data-step="meta"></section>
    <div class="sr-only" aria-live="polite" data-builder-announcer></div>
    <div data-template-list></div><div data-template-preview></div><div data-template-preview-title></div>
    <div data-block-canvas></div><div data-filled-count></div><div data-page-preview></div>
    <div data-settings-drawer></div><div data-content-settings></div><pre data-builder-json></pre>
    <div data-preview-stage></div><div data-preview-ruler></div><div data-preview-frame></div>
    <div data-preview-title></div><div data-preview-viewport-label></div><div data-content-width-label></div>
    <button data-save-page>Save page</button><div data-meta-template></div><dl data-meta-summary></dl>
    <div data-meta-preview-title></div><div data-meta-preview-path></div><div data-meta-preview-description></div>`;

export const installDom = (builderConfig) => {
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', (error) => { throw error; });
    const dom = new JSDOM(`<!doctype html><html lang="en"><head><title>Page builder</title></head><body>${rootMarkup(builderConfig)}</body></html>`, {
        url: 'https://cms.test/admin/pages/create?mode=edit',
        virtualConsole,
    });
    const root = dom.window.document.querySelector('[data-page-builder]');
    root.dataset.config = JSON.stringify(builderConfig);
    for (const element of [...dom.window.document.body.children].slice(1)) {
        root.append(element);
    }
    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    globalThis.DOMParser = dom.window.DOMParser;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.Element = dom.window.Element;
    globalThis.Node = dom.window.Node;
    globalThis.Event = dom.window.Event;
    globalThis.CustomEvent = dom.window.CustomEvent;
    globalThis.HTMLDivElement = dom.window.HTMLDivElement;
    globalThis.HTMLInputElement = dom.window.HTMLInputElement;
    globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
    globalThis.HTMLTextAreaElement = dom.window.HTMLTextAreaElement;
    globalThis.getComputedStyle = dom.window.getComputedStyle.bind(dom.window);
    globalThis.CSS = {escape: (value) => String(value).replaceAll('"', '\\"')};
    window.requestAnimationFrame = (callback) => callback();
    window.cancelAnimationFrame = () => {};
    window.HTMLElement.prototype.scrollIntoView = () => {};
    return dom;
};
