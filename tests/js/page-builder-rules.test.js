import assert from 'node:assert/strict';
import test from 'node:test';
import {config, installDom} from './support/page-builder-dom.js';

/*
 * The builder offers only what the server accepts: types an editor may not
 * add are hidden, existing blocks of such types stay visible, and the rules
 * for containers and the custom-layout chrome come from the type definitions.
 */

const types = [
    {type: 'header', label: 'Header', regions: ['header'], unique: true, defaults: {}, fields: [], insertable: true, editable: true, container: false, chromeRegions: ['header']},
    {
        type: 'section', label: 'Section', regions: ['main'], defaults: {children: []},
        fields: [{name: 'children', type: 'children'}], insertable: true, editable: true, container: true, chromeRegions: [],
    },
    {
        type: 'text', label: 'Text block', regions: ['main', 'footer'], defaults: {text: ''},
        fields: [{name: 'text', label: 'Text', type: 'textarea'}], insertable: true, editable: true, container: false, chromeRegions: ['footer'],
    },
    {
        type: 'cta', label: 'CTA', regions: ['main', 'footer'], defaults: {title: ''},
        fields: [{name: 'title', label: 'Title', type: 'text'}], insertable: true, editable: true, container: false, chromeRegions: [],
    },
    {
        type: 'quote', label: 'Quote', regions: ['main'], defaults: {quote: ''},
        fields: [{name: 'quote', label: 'Quote', type: 'textarea'}], insertable: false, editable: true, container: false, chromeRegions: [],
    },
    {
        type: 'embed', label: 'Embed', regions: ['main'], defaults: {url: ''},
        fields: [{name: 'url', label: 'URL', type: 'text'}], insertable: false, editable: false, container: false, chromeRegions: [],
    },
    {
        type: 'aside-note', label: 'Aside note', regions: ['aside'], defaults: {}, fields: [],
        insertable: true, editable: true, container: false, chromeRegions: [],
    },
    {
        type: 'heading-group', label: 'Heading group', regions: ['main'], defaults: {children: []},
        fields: [{name: 'children', type: 'children', accepts: ['text']}], insertable: true, editable: true, container: true, chromeRegions: [],
    },
    {
        type: 'plugin-card', label: 'Plugin card', regions: ['main'], defaults: {title: 'Card'},
        fields: [{name: 'title', label: 'Title', type: 'text'}], insertable: true, editable: true, container: false, chromeRegions: [],
    },
];

const slot = (id, type, region = 'main') => ({
    id, label: id, region, size: 'medium', position: 'stretch', maxHeight: 'none', overflow: 'visible',
    surface: 'none', accepts: [type], defaultContentType: type, deletable: true,
});

const customConfig = (initialState) => ({...config(initialState), contentTypes: types});

const startBuilder = async (builderConfig, json = async () => ({ok: true, json: async () => ({success: true})})) => {
    installDom(builderConfig);
    window.NafCMS = {json};
    const {PageBuilder} = await import('../../public/js/admin/page-builder/PageBuilder.js');
    const builder = new PageBuilder(document.querySelector('[data-page-builder]'));
    await builder.init();

    return builder;
};

const pickerTypes = (html) => [...html.matchAll(/data-(?:custom-slot-|layout-child-)?content-type="([^"]+)"/g)].map((match) => match[1]);

test('types an editor may not add are hidden, existing blocks of them stay on the page', async () => {
    const builder = await startBuilder(customConfig({
        id: 7,
        templateId: 'custom',
        customLayout: [slot('slot_quote', 'quote'), slot('slot_embed', 'embed')],
        blockContent: {
            slot_quote: {type: 'quote', label: 'Quote', props: {quote: 'Kept quote'}},
            slot_embed: {type: 'embed', label: 'Embed', props: {url: 'https://example.com/a'}},
        },
        persistedValues: {title: 'Rules', path: '/rules', language: 'en', status: 'draft'},
    }));

    const offered = pickerTypes(builder.renderCustomSlotContentOptionsHtml());
    assert.ok(offered.includes('text'));
    assert.ok(!offered.includes('quote'), 'disabled type is not offered');
    assert.ok(!offered.includes('embed'), 'restricted type is not offered');

    const slots = builder.customLayout.length;
    builder.addCustomSlot('quote');
    builder.addCustomSlot('embed');
    assert.equal(builder.customLayout.length, slots, 'adding is refused, also for programmatic callers such as the assistant');

    assert.match(document.querySelector('[data-page-preview]').textContent, /Kept quote/);

    builder.selectedBlockId = 'slot_quote';
    builder.selectedNestedPath = '';
    builder.renderSettings();
    assert.ok(document.querySelector('[data-content-settings] [data-prop-name="quote"]'), 'a disabled type stays editable');
    assert.equal(document.querySelector('[data-content-settings] [data-block-locked]'), null);

    builder.selectedBlockId = 'slot_embed';
    builder.renderSettings();
    assert.ok(document.querySelector('[data-content-settings] [data-block-locked]'), 'a restricted type shows why it cannot be changed');
    assert.equal(document.querySelector('[data-content-settings] [data-prop-name="url"]'), null);
});

test('child pickers follow container accepts, slot nestedAccepts and main placement', async () => {
    const builderConfig = customConfig({
        id: 8,
        templateId: 'landing',
        blockContent: {
            main: {type: 'section', label: 'Section', props: {children: [{type: 'heading-group', label: 'Group', props: {children: []}}]}},
        },
        persistedValues: {title: 'Nested', path: '/nested', language: 'en', status: 'draft'},
    });
    builderConfig.templates = [{
        id: 'landing', label: 'Landing', kind: 'static', layout: 'layouts.page', view: 'page.templates.landing',
        blocks: [{id: 'main', label: 'Main', region: 'main', accepts: ['section'], nestedAccepts: ['text', 'heading-group', 'quote']}],
    }];
    const builder = await startBuilder(builderConfig);
    builder.selectedBlockId = 'main';
    builder.selectedNestedPath = '';

    assert.deepEqual(
        pickerTypes(builder.renderLayoutChildContentOptionsHtml('')).sort(),
        ['heading-group', 'text'],
        'nestedAccepts limits the slot; quote is listed there but not insertable',
    );
    assert.deepEqual(pickerTypes(builder.renderLayoutChildContentOptionsHtml('0')), ['text'], 'the container field narrows further');

    builder.addLayoutChild('', 'cta');
    builder.addLayoutChild('0', 'heading-group');
    assert.equal(builder.blockContent.main.props.children.length, 1);
    assert.equal(builder.blockContent.main.props.children[0].props.children.length, 0);

    builder.addLayoutChild('0', 'text');
    assert.equal(builder.blockContent.main.props.children[0].props.children[0].type, 'text');
});

test('children are placed as main content and plugin containers count as containers', async () => {
    const builder = await startBuilder(customConfig({
        id: 9,
        templateId: 'custom',
        customLayout: [slot('slot_section', 'section')],
        blockContent: {slot_section: {type: 'section', label: 'Section', props: {children: []}}},
        persistedValues: {title: 'Main', path: '/main', language: 'en', status: 'draft'},
    }));
    builder.selectedBlockId = 'slot_section';
    builder.selectedNestedPath = '';

    const offered = pickerTypes(builder.renderLayoutChildContentOptionsHtml(''));
    assert.ok(!offered.includes('aside-note'), 'an aside-only type is not offered inside a container');
    assert.ok(!offered.includes('header'));
    assert.ok(offered.includes('heading-group'));

    assert.equal(builder.isContainerType('heading-group'), true);
    assert.equal(builder.isContainerType('text'), false);
    assert.equal(builder.isContainerType('grid'), true, 'unknown definitions fall back to the shipped containers');
});

test('custom-layout chrome takes the types that name the area, and header is only offered when insertable', async () => {
    const restricted = types.map((type) => (type.type === 'header' ? {...type, insertable: false} : type));
    const builder = await startBuilder({
        ...customConfig({
            id: 10,
            templateId: 'custom',
            customLayout: [{...slot('slot_footer', 'text', 'footer'), accepts: []}],
            blockContent: {slot_footer: {type: 'text', label: 'Text', props: {text: 'Footer'}}},
            persistedValues: {title: 'Chrome', path: '/chrome', language: 'en', status: 'draft'},
        }),
        contentTypes: restricted,
    });
    const footer = builder.customLayout.find((item) => item.id === 'slot_footer');

    assert.equal(builder.contentTypeSupportsBlock(builder.contentTypeDefinition('text'), footer), true);
    assert.equal(builder.contentTypeSupportsBlock(builder.contentTypeDefinition('cta'), footer), false);
    assert.equal(document.querySelector('[data-add-chrome-slot="header"]'), null);
});

test('blocks without a drawn preview are rendered by the server and cached', async () => {
    const requests = [];
    let respond = async () => ({ok: true, json: async () => ({success: true, html: '<!doctype html><p>Plugin card HTML</p>'})});
    const builder = await startBuilder(customConfig({
        id: 11,
        templateId: 'custom',
        customLayout: [slot('slot_card', 'plugin-card')],
        blockContent: {slot_card: {type: 'plugin-card', label: 'Card', props: {title: 'Hello'}}},
        persistedValues: {title: 'Preview', path: '/preview', language: 'en', status: 'draft'},
    }), async (url, options) => {
        requests.push({url, body: JSON.parse(options.body)});
        return respond();
    });
    builder.blockPreviewDelay = 0;
    builder.renderBlocks();
    builder.renderPagePreview();
    await new Promise((resolve) => setTimeout(resolve, 20));

    const previewRequests = requests.filter((request) => request.url === '/api/admin/pages/block-preview');
    assert.equal(previewRequests.length, 1, 'one request for the block, however often it was drawn');
    assert.deepEqual(previewRequests[0].body.content, {type: 'plugin-card', label: 'Card', props: {title: 'Hello'}});

    const frame = document.querySelector('[data-page-preview] iframe[data-block-preview-frame]');
    assert.ok(frame, 'the server preview is shown in a frame');
    assert.equal(frame.getAttribute('sandbox'), '', 'the frame runs no scripts');
    assert.match(frame.getAttribute('srcdoc'), /Plugin card HTML/);

    respond = async () => ({ok: false, json: async () => ({success: false})});
    builder.blockContent.slot_card.props.title = 'Changed';
    builder.lastBlockPreviewByType.clear();
    builder.renderPagePreview();
    await new Promise((resolve) => setTimeout(resolve, 20));
    assert.match(document.querySelector('[data-page-preview]').textContent, /Preview unavailable/);
});


test('custom palette and programmatic insertion obey template and chrome restrictions', async () => {
    const restrictedTypes = [
        ...types,
        {type: 'article-only', label: 'Article', regions: ['main'], contentTypes: ['article'], insertable: true},
        {type: 'dynamic-only', label: 'Dynamic', regions: ['main'], templateKinds: ['dynamic'], insertable: true},
        {type: 'not-chrome', label: 'Not chrome', regions: ['header'], chromeRegions: [], insertable: true},
    ];
    const builder = await startBuilder({...customConfig({templateId: 'custom'}), contentTypes: restrictedTypes});
    const offered = pickerTypes(builder.renderCustomSlotContentOptionsHtml());
    for (const type of ['article-only', 'dynamic-only', 'not-chrome']) {
        assert.ok(!offered.includes(type), `${type} cannot be placed in this template`);
        builder.addCustomSlot(type);
    }
    assert.equal(builder.customLayout.length, 0, 'invalid types are also refused for programmatic calls');
});

test('existing unique blocks are retained when a newer type definition marks them unique', async () => {
    const builder = await startBuilder(customConfig({
        id: 12, templateId: 'custom',
        customLayout: [slot('old_header_a', 'header', 'header'), slot('old_header_b', 'header', 'header')],
        blockContent: {old_header_a: {type: 'header', props: {}}, old_header_b: {type: 'header', props: {}}},
        persistedValues: {title: 'Existing', path: '/existing', language: 'en', status: 'draft'},
    }));
    assert.deepEqual(builder.customLayout.map((item) => item.id), ['old_header_a', 'old_header_b']);
    assert.deepEqual(Object.keys(builder.pagePayload().blocks), ['old_header_a', 'old_header_b']);
    builder.addChromeSlot('header');
    assert.equal(builder.customLayout.length, 2, 'new unique blocks are still refused');
});

test('debounced plugin previews snapshot props so cache keys match requests', async () => {
    const requests = [];
    const builder = await startBuilder(customConfig({
        templateId: 'custom', customLayout: [], blockContent: {},
        persistedValues: {title: 'Preview', path: '/preview', language: 'en', status: 'draft'},
    }), async (_url, options) => {
        const payload = JSON.parse(options.body);
        requests.push(payload.content.props.title);
        return {ok: true, json: async () => ({success: true, html: `<p>${payload.content.props.title}</p>`})};
    });
    const content = {type: 'plugin-card', label: 'Card', props: {title: 'Old'}};
    builder.serverBlockPreview(content);
    content.props.title = 'New';
    builder.serverBlockPreview(content);
    window.clearTimeout(builder.blockPreviewTimer);
    await builder.flushBlockPreviews();
    assert.deepEqual(requests, ['Old', 'New']);
    assert.equal(builder.blockPreviews.get(JSON.stringify(['plugin-card', {title: 'Old'}])).html, '<p>Old</p>');
    assert.equal(builder.blockPreviews.get(JSON.stringify(['plugin-card', {title: 'New'}])).html, '<p>New</p>');
});
