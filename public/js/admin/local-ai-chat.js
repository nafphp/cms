import { requestOllama, stripModelReasoning, trimValue } from './ollama-client.js';
import { directAssistantCommandFor } from './local-ai-direct-commands.js';

const storageKeys = {
    open: 'nixcms:local-ai-chat:open',
    mode: 'nixcms:local-ai-chat:mode',
    messages: 'nixcms:local-ai-chat:messages',
    scroll: 'nixcms:local-ai-chat:scroll',
    draft: 'nixcms:local-ai-chat:draft',
    settingsModel: 'nixcms:local-ai-settings:model',
    settingsUrl: 'nixcms:local-ai-settings:url',
    settingsType: 'nixcms:local-ai-settings:type',
    settingsEnabled: 'nixcms:local-ai-settings:enabled',
    settingsInstructions: 'nixcms:local-ai-settings:instructions',
};

const greetingTemplates = [
    (name) => `Hallo ${name}, wie kann ich Dir helfen?`,
    (name) => `Hey ${name}, womit legen wir los?`,
    (name) => `Hallo ${name}, ich bin bereit. Was schauen wir uns an?`,
    (name) => `Hi ${name}, was darf ich fuer Dich im CMS tun?`,
    (name) => `Hallo ${name}, wobei soll ich Dich unterstuetzen?`,
];

const anonymousGreetingTemplates = [
    () => 'Hallo, wie kann ich Dir helfen?',
    () => 'Hey, womit legen wir los?',
    () => 'Hallo, ich bin bereit. Was schauen wir uns an?',
    () => 'Hi, was darf ich fuer Dich im CMS tun?',
    () => 'Hallo, wobei soll ich Dich unterstuetzen?',
];

const randomItem = (items) => items[Math.floor(Math.random() * items.length)] || items[0];

const createElement = (tag, className = '', text = '') => {
    const element = document.createElement(tag);
    if (className !== '') {
        element.className = className;
    }
    if (text !== '') {
        element.textContent = text;
    }
    return element;
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
})[char]);

const inlineMarkdown = (value) => escapeHtml(value)
    .replace(/`([^`]+)`/g, '<code class="rounded bg-slate-200/70 px-1.5 py-0.5 text-[0.82em] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong class="font-semibold text-slate-900 dark:text-white">$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>');

const splitTableRow = (line) => trimValue(line)
    .replace(/^\|/, '')
    .replace(/\|$/, '')
    .split('|')
    .map((cell) => trimValue(cell));

const isTableDivider = (line) => /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/.test(line);

const isListLine = (line) => /^\s*([-*]|\d+\.)\s+/.test(line);

const renderMarkdownTable = (lines) => {
    const header = splitTableRow(lines[0]);
    const rows = lines.slice(2).map(splitTableRow).filter((row) => row.some((cell) => cell !== ''));
    if (header.length === 0 || rows.length === 0) {
        return `<p>${inlineMarkdown(lines.join('\n'))}</p>`;
    }

    const cells = (row, tag, className) => row
        .map((cell) => `<${tag} class="${className}">${inlineMarkdown(cell)}</${tag}>`)
        .join('');

    return [
        '<div class="my-3 overflow-x-auto rounded-lg border border-slate-200 bg-white/60 dark:border-slate-700 dark:bg-slate-950/40">',
        '<table class="min-w-full border-collapse text-left text-xs leading-5">',
        `<thead class="bg-slate-100/80 text-slate-700 dark:bg-slate-800/80 dark:text-slate-100"><tr>${cells(header, 'th', 'border-b border-slate-200 px-3 py-2 font-semibold dark:border-slate-700')}</tr></thead>`,
        `<tbody>${rows.map((row) => `<tr class="border-t border-slate-100 dark:border-slate-800">${cells(row, 'td', 'px-3 py-2 align-top text-slate-600 dark:text-slate-300')}</tr>`).join('')}</tbody>`,
        '</table>',
        '</div>',
    ].join('');
};

const renderMarkdownList = (lines, ordered) => {
    const tag = ordered ? 'ol' : 'ul';
    const markerClass = ordered ? 'list-decimal' : 'list-disc';
    const items = lines
        .map((line) => line.replace(/^\s*([-*]|\d+\.)\s+/, ''))
        .map((line) => `<li>${inlineMarkdown(line)}</li>`)
        .join('');

    return `<${tag} class="my-2 ${markerClass} space-y-1 pl-5">${items}</${tag}>`;
};

const renderMarkdown = (content) => {
    const lines = String(content ?? '').replace(/\r\n?/g, '\n').split('\n');
    const html = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];

        if (trimValue(line) === '') {
            index += 1;
            continue;
        }

        if (line.startsWith('```')) {
            const code = [];
            index += 1;
            while (index < lines.length && !lines[index].startsWith('```')) {
                code.push(lines[index]);
                index += 1;
            }
            index += index < lines.length ? 1 : 0;
            html.push(`<pre class="my-3 max-h-72 overflow-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-100"><code>${escapeHtml(code.join('\n'))}</code></pre>`);
            continue;
        }

        if (line.includes('|') && lines[index + 1] && isTableDivider(lines[index + 1])) {
            const table = [line, lines[index + 1]];
            index += 2;
            while (index < lines.length && trimValue(lines[index]) !== '' && lines[index].includes('|')) {
                table.push(lines[index]);
                index += 1;
            }
            html.push(renderMarkdownTable(table));
            continue;
        }

        const heading = line.match(/^(#{1,3})\s+(.+)$/);
        if (heading) {
            const levelClass = heading[1].length === 1
                ? 'text-base'
                : 'text-sm';
            html.push(`<strong class="mt-3 mb-1 block ${levelClass} font-semibold text-slate-900 dark:text-white">${inlineMarkdown(heading[2])}</strong>`);
            index += 1;
            continue;
        }

        if (isListLine(line)) {
            const ordered = /^\s*\d+\.\s+/.test(line);
            const list = [];
            while (index < lines.length && isListLine(lines[index]) && (/^\s*\d+\.\s+/.test(lines[index]) === ordered)) {
                list.push(lines[index]);
                index += 1;
            }
            html.push(renderMarkdownList(list, ordered));
            continue;
        }

        const paragraph = [];
        while (
            index < lines.length
            && trimValue(lines[index]) !== ''
            && !lines[index].startsWith('```')
            && !(lines[index].includes('|') && lines[index + 1] && isTableDivider(lines[index + 1]))
            && !isListLine(lines[index])
        ) {
            paragraph.push(lines[index]);
            index += 1;
        }
        html.push(`<p class="my-2">${inlineMarkdown(paragraph.join(' '))}</p>`);
    }

    return html.join('');
};

const renderMessageContent = (node, role, content) => {
    if (!(node instanceof HTMLElement)) {
        return;
    }

    if (role === 'assistant') {
        node.innerHTML = renderMarkdown(content);
        return;
    }

    node.textContent = content;
};

const thinkingHtml = (label = 'Denke nach...') => `
    <div class="grid gap-2" data-ai-thinking>
        <span class="text-sm font-medium text-slate-500 dark:text-slate-300">${escapeHtml(label)}</span>
        <span class="block h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800" aria-hidden="true">
            <span class="block h-full w-1/3 rounded-full bg-[var(--admin-page-accent)] motion-safe:animate-[local-ai-thinking_1.1s_ease-in-out_infinite]"></span>
        </span>
    </div>
`;

const messageClass = (role) => role === 'user'
    ? 'ml-10 rounded-lg border border-[color-mix(in_srgb,var(--admin-page-accent)_38%,transparent)] bg-[color-mix(in_srgb,var(--admin-page-accent)_14%,white_86%)] px-4 py-3.5 text-sm leading-6 text-slate-900 shadow-sm dark:bg-[color-mix(in_srgb,var(--admin-page-accent)_24%,#020617_76%)] dark:text-white dark:shadow-none'
    : 'mr-10 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm leading-6 text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:shadow-none';

const modeButtonClass = (active) => active
    ? 'border-[var(--admin-page-accent)] bg-[color-mix(in_srgb,var(--admin-page-accent)_16%,transparent)] text-[var(--admin-page-accent)]'
    : 'border-slate-200 bg-white/58 text-slate-500 hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900/58 dark:text-slate-300 dark:hover:text-white';

const scrollToEnd = (node, behavior = 'smooth') => {
    if (node) {
        node.scrollTo({top: node.scrollHeight, behavior});
    }
};

const isNearBottom = (node) => {
    if (!(node instanceof HTMLElement)) {
        return true;
    }

    return node.scrollHeight - node.scrollTop - node.clientHeight < 36;
};

const normalizeEnabled = (value) => ['1', 'true', 'yes', 'on', 'enabled'].includes(trimValue(value).toLowerCase())
    ? '1'
    : '0';

const normalizeGeneratedCode = (value) => String(value || '').replace(/\r\n?/g, '\n');

const autosize = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 144)}px`;
};

const defaultSystemPrompt = [
    'You are the NixCMS Assistant inside the NixCMS admin UI.',
    'In user-facing replies, present yourself as the NixCMS Assistant, not as Google, Gemma, DeepSeek, Meta, OpenAI, Ollama, or any base-model/provider identity.',
    'If asked what model you are, say you are the NixCMS Assistant running through the locally configured Ollama model unless visible configuration explicitly provides more detail.',
    'If earlier assistant messages in the visible chat history used a provider identity or generic base-model self-description, treat those messages as stale and follow this system prompt instead.',
    'If asked what you can do or where to start, answer as a CMS admin assistant and frame capabilities around NixCMS work, not generic base-model abilities.',
    'Mention that exact actions depend on the tools selected for the current request and the user permissions. Do not recite a fixed exhaustive feature list.',
    'Do not claim direct database, filesystem, CMS, MCP, API, or tool access unless an explicit tool result is provided in the conversation.',
    'Do not invent CMS facts such as article counts, IDs, titles, statuses, users, roles, plugin names, queue numbers, or health states.',
    'If current CMS state is requested without a tool result, say that you do not know yet and that a CMS tool must be run first.',
    'Answer in the language of the user\'s latest message.',
].join('\n');

const detectLanguageInstruction = (value) => {
    const text = trimValue(value).toLowerCase();
    if (text === '') {
        return '';
    }

    const germanSignals = [
        ' der ', ' die ', ' das ', ' und ', ' oder ', ' ist ', ' sind ', ' wie ', ' was ', ' welche ',
        'welches ', 'wieviele', 'wieviel', 'aktuell', 'heute', 'morgen', 'deutschland', 'bitte',
        'kannst du', 'gibt es', 'für ', 'größe', 'ä', 'ö', 'ü', 'ß',
    ];
    const englishSignals = [
        ' the ', ' and ', ' or ', ' is ', ' are ', ' what ', ' which ', ' how ', ' today ', ' tomorrow ',
        'please', 'can you', 'current', 'germany',
    ];
    const padded = ` ${text} `;
    const germanScore = germanSignals.filter((signal) => padded.includes(signal)).length;
    const englishScore = englishSignals.filter((signal) => padded.includes(signal)).length;
    const informalGerman = textMatchesAny(text, [' du ', ' dir ', ' dich ', ' dein ', ' deine ', ' kannst du ', ' hast du ']);
    const formalGerman = textMatchesAny(text, [' sie ', ' ihnen ', ' ihrer ', ' ihre ', ' können sie ', ' haben sie ']);

    if (germanScore > englishScore) {
        return [
            'Language instruction: The user wrote the latest message in German. Answer in German.',
            informalGerman && !formalGerman ? 'Address style instruction: Use informal German Du/Dir/Dich.' : '',
            formalGerman && !informalGerman ? 'Address style instruction: Use formal German Sie/Ihnen.' : '',
        ].filter(Boolean).join('\n');
    }

    if (englishScore > germanScore) {
        return 'Language instruction: The user wrote the latest message in English. Answer in English.';
    }

    return '';
};

const systemContent = (systemPrompt, instructions) => [
        systemPrompt !== '' ? systemPrompt : defaultSystemPrompt,
        instructions !== '' ? `Additional installation instructions:\n${instructions}` : '',
    ].filter(Boolean).join('\n\n');

const contextInstruction = (context) => {
    if (!context || typeof context !== 'object' || Array.isArray(context)) {
        return '';
    }

    const compact = JSON.stringify(context, (key, value) => {
        if (key === 'contexts' || typeof value === 'function') {
            return undefined;
        }
        if (Array.isArray(value)) {
            return value.slice(0, 24);
        }

        return value;
    });

    return compact === '{}' ? '' : `Visible UI context for tool arguments:\n${compact}`;
};

const chatMessages = (messages, systemPrompt, instructions, uiContext = {}) => [
    {role: 'system', content: systemContent(
        systemPrompt,
        [
            instructions,
            detectLanguageInstruction(messages.at(-1)?.content ?? ''),
            contextInstruction(uiContext),
        ].filter(Boolean).join('\n\n')
    )},
    ...messages.slice(-16).map((message) => ({
        role: message.role,
        content: message.content,
    })),
];

const storedJson = (key, fallback) => {
    try {
        const parsed = JSON.parse(localStorage.getItem(key) || 'null');
        return parsed ?? fallback;
    } catch (error) {
        return fallback;
    }
};

const persistJson = (key, value) => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {}
};

const normalizeStoredMessages = (value) => Array.isArray(value)
    ? value
        .filter((message) => (
            message
            && ['user', 'assistant'].includes(String(message.role || ''))
            && trimValue(message.content || '') !== ''
        ))
        .map((message) => ({
            role: String(message.role),
            content: String(message.content).slice(0, 12000),
        }))
        .slice(-40)
    : [];

const storedText = (key, fallback = '') => {
    try {
        return trimValue(localStorage.getItem(key) || fallback);
    } catch (error) {
        return trimValue(fallback);
    }
};

const apiJson = async (url, options = {}) => {
    const csrfHeader = String(window.NixCMS?.csrfHeader || 'X-CSRF-Token');
    const csrfToken = typeof window.NixCMS?.csrfToken === 'function'
        ? String(window.NixCMS.csrfToken() || '')
        : '';
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {}),
    };
    if (csrfToken !== '' && !Object.hasOwn(headers, csrfHeader)) {
        headers[csrfHeader] = csrfToken;
    }

    if (typeof window.NixCMS?.json !== 'function') {
        throw new Error('NixCMS admin JSON client is not available yet.');
    }

    const response = await window.NixCMS.json(url, {
        ...options,
        headers,
    });
    const raw = await response.text().catch(() => '');
    let data = {};
    try {
        data = raw !== '' ? JSON.parse(raw) : {};
    } catch (error) {}
    if (!response.ok || data.success === false) {
        throw new Error(data.reason || data.error || raw || `Request failed with HTTP ${response.status}.`);
    }

    return data;
};

const assistantRuntime = () => {
    window.NixCMS = window.NixCMS || {};
    if (window.NixCMS.assistantActions) {
        return window.NixCMS.assistantActions;
    }

    const actions = new Map();
    const contextProviders = new Map();

    window.NixCMS.assistantActions = {
        register(name, definition) {
            const key = trimValue(name);
            if (key === '' || !definition || typeof definition.handler !== 'function') {
                return;
            }

            actions.set(key, {
                description: trimValue(definition.description || ''),
                parameters: definition.parameters && typeof definition.parameters === 'object' ? definition.parameters : {},
                handler: definition.handler,
            });
        },
        registerContext(name, provider) {
            const key = trimValue(name);
            if (key !== '' && typeof provider === 'function') {
                contextProviders.set(key, provider);
            }
        },
        async run(name, payload = {}) {
            const action = actions.get(trimValue(name));
            if (!action) {
                throw new Error(`Assistant UI action ${name} is not registered.`);
            }

            return action.handler(payload && typeof payload === 'object' && !Array.isArray(payload) ? payload : {});
        },
        context() {
            const contexts = {};
            contextProviders.forEach((provider, name) => {
                try {
                    const value = provider();
                    if (value && typeof value === 'object' && !Array.isArray(value)) {
                        contexts[name] = value;
                    }
                } catch (error) {}
            });

            return contexts;
        },
    };

    return window.NixCMS.assistantActions;
};

const registerGlobalAssistantActions = () => {
    const runtime = assistantRuntime();
    if (window.NixCMS?.__globalAssistantActionsRegistered) {
        return;
    }

    window.NixCMS.__globalAssistantActionsRegistered = true;
    runtime.register('admin.navigate', {
        description: 'Navigates within the NixCMS admin UI',
        parameters: {
            url: 'string',
        },
        handler: ({url}) => {
            const target = trimValue(url);
            if (!/^\/admin(?:\/|$|\?)/.test(target)) {
                throw new Error('Assistant navigation is limited to NixCMS admin URLs.');
            }

            window.location.assign(target);
            return {success: true, url: target};
        },
    });
};

const collectAssistantContext = () => {
    const runtimeContext = assistantRuntime().context();
    const firstContext = Object.values(runtimeContext)[0] || {};
    const now = new Date();

    return {
        ...firstContext,
        url: window.location.pathname,
        today: now.toISOString().slice(0, 10),
        currentDateTime: now.toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        contexts: runtimeContext,
    };
};

const pageBuilderInteractionElement = (builder) => [
    builder?.previewFrame,
    builder?.previewStage,
    builder?.blockCanvas,
    builder?.root?.querySelector?.('[data-preview-frame]'),
    builder?.root?.querySelector?.('[data-preview-stage]'),
    builder?.root,
].find((candidate) => candidate instanceof HTMLElement) || null;

const pageBuilderFocusElement = (builder) => [
    builder?.blockCanvas?.querySelector?.('.page-template-shell'),
    builder?.previewFrame?.querySelector?.('.page-template-shell'),
    builder?.previewStage?.querySelector?.('.page-template-shell'),
    builder?.root?.querySelector?.('[data-block-canvas] .page-template-shell'),
    builder?.root?.querySelector?.('[data-preview-frame] .page-template-shell'),
    pageBuilderInteractionElement(builder),
].find((candidate) => candidate instanceof HTMLElement) || null;

const pageBuilderTargetFromElement = (builder, element) => {
    const pick = element?.closest?.('[data-ai-pick-block-id]');
    const pickBlockId = trimValue(pick?.dataset?.aiPickBlockId || '');
    if (pickBlockId !== '') {
        return {
            blockId: pickBlockId,
            path: trimValue(pick.dataset.aiPickPath || ''),
            element: pick,
        };
    }

    const block = element?.closest?.('[data-edit-block], [data-choose-block]');
    const blockId = trimValue(block?.dataset?.editBlock || block?.dataset?.chooseBlock || '');
    if (blockId !== '') {
        return {blockId, path: '', element: block};
    }

    return null;
};

const pageBuilderContentForTarget = (builder, target) => {
    if (!builder || !target?.blockId) {
        return null;
    }

    if (target.path !== '' && typeof builder.contentAtPath === 'function') {
        const previousBlockId = builder.selectedBlockId;
        builder.selectedBlockId = target.blockId;
        const content = builder.contentAtPath(target.path);
        builder.selectedBlockId = previousBlockId;
        return content;
    }

    return builder.blockContent?.[target.blockId] || null;
};

const pageBuilderDefinitionForContent = (builder, content) => typeof builder?.contentTypeDefinition === 'function'
    ? builder.contentTypeDefinition(content?.type || '')
    : null;

const pageBuilderBlockForTarget = (builder, target) => {
    const blocks = builder?.selectedTemplate?.()?.blocks || [];
    return blocks.find((block) => block?.id === target?.blockId) || null;
};

const pageBuilderInspectableProps = (props) => {
    if (!props || typeof props !== 'object' || Array.isArray(props)) {
        return {};
    }

    return Object.fromEntries(Object.entries(props)
        .filter(([key]) => key !== 'children')
        .slice(0, 16)
        .map(([key, value]) => [
            key,
            value && typeof value === 'object' ? JSON.stringify(value).slice(0, 500) : value,
        ]));
};

const describePageBuilderTarget = (builder, target) => {
    const content = pageBuilderContentForTarget(builder, target);
    const definition = pageBuilderDefinitionForContent(builder, content);
    const block = pageBuilderBlockForTarget(builder, target);
    const props = content?.props && typeof content.props === 'object' && !Array.isArray(content.props) ? content.props : {};
    const children = Array.isArray(props.children) ? props.children : [];

    return {
        success: true,
        area: 'pageBuilder',
        blockId: target?.blockId || '',
        nestedPath: target?.path || '',
        slot: block ? {
            id: block.id || '',
            label: builder?.uiText?.(block.label || '') || block.label || '',
            region: typeof builder?.blockRegion === 'function' ? builder.blockRegion(block) : (block.region || ''),
            size: block.size || '',
            accepts: Array.isArray(block.accepts) ? block.accepts : [],
        } : null,
        content: content ? {
            type: content.type || '',
            label: builder?.uiText?.(content.label || '') || content.label || '',
            previewTitle: builder?.uiText?.(content.previewTitle || '') || content.previewTitle || '',
            props: pageBuilderInspectableProps(props),
            childCount: children.length,
            childTypes: children.map((child) => child?.type || '').filter(Boolean).slice(0, 12),
        } : null,
        definition: definition ? {
            type: definition.type || '',
            label: builder?.uiText?.(definition.label || '') || definition.label || '',
            description: builder?.uiText?.(definition.description || '') || definition.description || '',
            fields: Array.isArray(definition.fields) ? definition.fields.map((field) => field?.name || '').filter(Boolean).slice(0, 16) : [],
            regions: Array.isArray(definition.regions) ? definition.regions : [],
        } : null,
        text: trimValue(target?.element?.innerText || '').replace(/\s+/g, ' ').slice(0, 500),
    };
};

const pageBuilderInspectResultText = (result) => {
    if (!result || typeof result !== 'object') {
        return 'Zeigemodus wurde beendet, aber es wurden keine Elementdaten zurückgegeben.';
    }

    if (result.cancelled) {
        return result.message || 'Zeigemodus abgebrochen.';
    }

    if (result.success !== true) {
        return result.error || result.message || 'Zeigemodus konnte nicht gestartet werden.';
    }

    const content = result.content || {};
    const definition = result.definition || {};
    const slot = result.slot || {};
    const lines = [
        'Zeigemodus-Auswahl:',
        '',
        `- Block-ID: ${result.blockId || '-'}`,
        result.nestedPath ? `- Verschachtelter Pfad: ${result.nestedPath}` : '',
        `- Slot/Region: ${slot.label || slot.region || '-'}`,
        `- Typ: ${content.type || definition.type || '-'}`,
        `- Label: ${content.label || definition.label || '-'}`,
        definition.description ? `- Beschreibung: ${definition.description}` : '',
        Number.isFinite(content.childCount) ? `- Kinder: ${content.childCount}` : '',
        Array.isArray(content.childTypes) && content.childTypes.length > 0 ? `- Kind-Typen: ${content.childTypes.join(', ')}` : '',
        result.text ? `- Sichtbarer Text: ${result.text}` : '',
    ].filter(Boolean);

    return lines.join('\n');
};

const ensurePageBuilderInspectStyles = () => {
    if (document.getElementById('nixcms-ai-pagebuilder-inspect-style')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'nixcms-ai-pagebuilder-inspect-style';
    style.textContent = `
        body.nixcms-ai-pagebuilder-inspecting {
            cursor: crosshair;
        }
        body.nixcms-ai-pagebuilder-inspecting *,
        body.nixcms-ai-pagebuilder-inspecting *::before,
        body.nixcms-ai-pagebuilder-inspecting *::after {
            cursor: crosshair !important;
        }
        .nixcms-ai-pagebuilder-inspect-dim {
            position: fixed;
            z-index: 9989;
            pointer-events: none;
            background: rgba(2, 6, 23, .54);
            backdrop-filter: blur(5px) saturate(.78);
            -webkit-backdrop-filter: blur(5px) saturate(.78);
        }
        .nixcms-ai-pagebuilder-inspect-focus {
            position: fixed;
            z-index: 9990;
            pointer-events: none;
            border: 2px solid rgba(96, 165, 250, .58);
            border-radius: 16px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .52), 0 0 0 999px rgba(2, 6, 23, .08), 0 24px 90px rgba(59, 130, 246, .28);
        }
        .nixcms-ai-pagebuilder-inspect-cursor {
            position: fixed;
            left: 0;
            top: 0;
            z-index: 9995;
            pointer-events: none;
            width: 48px;
            height: 48px;
            margin: -24px 0 0 -24px;
            border: 2px solid rgba(186, 230, 253, .98);
            border-radius: 999px;
            background: radial-gradient(circle at center, rgba(240, 249, 255, .18) 0 12%, rgba(14, 165, 233, .08) 13% 48%, transparent 49%);
            box-shadow:
                0 0 0 8px rgba(14, 165, 233, .18),
                0 0 28px rgba(56, 189, 248, .78),
                0 0 72px rgba(59, 130, 246, .44),
                inset 0 0 0 12px rgba(14, 165, 233, .08);
            opacity: 0;
            transform: translate3d(0, 0, 0) scale(.88);
            transition: opacity .08s ease;
            will-change: transform, opacity;
        }
        .nixcms-ai-pagebuilder-inspect-cursor::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 6px;
            height: 6px;
            transform: translate(-50%, -50%);
            border-radius: 999px;
            background: #f8fafc;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, .28), 0 0 18px rgba(186, 230, 253, .98);
        }
        .nixcms-ai-pagebuilder-inspect-hint {
            position: fixed;
            left: 50%;
            top: 18px;
            z-index: 9994;
            max-width: min(560px, calc(100vw - 32px));
            transform: translateX(-50%);
            border: 1px solid rgba(96, 165, 250, .46);
            border-radius: 10px;
            background: rgba(15, 23, 42, .96);
            color: #e5e7eb;
            padding: 10px 13px;
            font: 600 13px/1.35 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            box-shadow: 0 18px 44px rgba(2, 6, 23, .42);
        }
        .nixcms-ai-pagebuilder-inspect-outline {
            position: fixed;
            z-index: 9993;
            pointer-events: none;
            border: 2px solid rgba(56, 189, 248, .96);
            border-radius: 12px;
            box-shadow: 0 0 0 6px rgba(14, 165, 233, .18), 0 20px 48px rgba(2, 6, 23, .28);
            transition: opacity .08s ease;
            will-change: transform, width, height, opacity;
        }
    `;
    document.head.appendChild(style);
};

const setBox = (node, box) => {
    node.style.left = `${Math.max(0, box.left)}px`;
    node.style.top = `${Math.max(0, box.top)}px`;
    node.style.width = `${Math.max(0, box.width)}px`;
    node.style.height = `${Math.max(0, box.height)}px`;
};

const paddedViewportBox = (element, padding = 18) => {
    const box = element.getBoundingClientRect();

    return {
        left: Math.max(0, box.left - padding),
        top: Math.max(0, box.top - padding),
        right: Math.min(window.innerWidth, box.right + padding),
        bottom: Math.min(window.innerHeight, box.bottom + padding),
    };
};

const startPageBuilderInspectMode = ({hint = ''} = {}) => new Promise((resolve) => {
    const builder = window.NixCMS?.pageBuilder;
    const focus = pageBuilderFocusElement(builder);
    const interaction = pageBuilderInteractionElement(builder);
    if (!builder || !(focus instanceof HTMLElement) || !(interaction instanceof HTMLElement)) {
        resolve({success: false, error: 'PageBuilder is not active on the current page.'});
        return;
    }

    ensurePageBuilderInspectStyles();

    const drawerWasOpen = Boolean(builder.settingsOpen);
    if (drawerWasOpen && typeof builder.closeSettings === 'function') {
        builder.closeSettings();
    }
    document.dispatchEvent(new CustomEvent('nixcms:assistant-pick-mode', {detail: {active: true}}));

    const hintNode = createElement(
        'div',
        'nixcms-ai-pagebuilder-inspect-hint',
        trimValue(hint) || 'Zeigemodus aktiv: Klick im PageBuilder auf einen Block oder ein Element. Escape bricht ab.'
    );
    const dimTop = createElement('div', 'nixcms-ai-pagebuilder-inspect-dim');
    const dimRight = createElement('div', 'nixcms-ai-pagebuilder-inspect-dim');
    const dimBottom = createElement('div', 'nixcms-ai-pagebuilder-inspect-dim');
    const dimLeft = createElement('div', 'nixcms-ai-pagebuilder-inspect-dim');
    const focusFrame = createElement('div', 'nixcms-ai-pagebuilder-inspect-focus');
    const cursorRing = createElement('div', 'nixcms-ai-pagebuilder-inspect-cursor');
    const outline = createElement('div', 'nixcms-ai-pagebuilder-inspect-outline');
    outline.style.opacity = '0';
    document.body.classList.add('nixcms-ai-pagebuilder-inspecting');
    document.body.append(dimTop, dimRight, dimBottom, dimLeft, focusFrame, hintNode, cursorRing, outline);
    let cursorX = 0;
    let cursorY = 0;
    let cursorVisible = false;
    let cursorFrame = 0;
    let outlineTarget = null;
    let outlineFrame = 0;

    const updateSpotlight = () => {
        const box = paddedViewportBox(focus, 18);
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        setBox(dimTop, {left: 0, top: 0, width: viewportWidth, height: box.top});
        setBox(dimRight, {left: box.right, top: box.top, width: viewportWidth - box.right, height: box.bottom - box.top});
        setBox(dimBottom, {left: 0, top: box.bottom, width: viewportWidth, height: viewportHeight - box.bottom});
        setBox(dimLeft, {left: 0, top: box.top, width: box.left, height: box.bottom - box.top});
        setBox(focusFrame, {left: box.left, top: box.top, width: box.right - box.left, height: box.bottom - box.top});
    };

    const paintCursor = () => {
        cursorFrame = 0;
        cursorRing.style.opacity = cursorVisible ? '1' : '0';
        cursorRing.style.transform = `translate3d(${cursorX}px, ${cursorY}px, 0) scale(${cursorVisible ? 1 : 0.88})`;
    };

    const scheduleCursorPaint = () => {
        if (cursorFrame === 0) {
            cursorFrame = window.requestAnimationFrame(paintCursor);
        }
    };

    const paintOutline = () => {
        outlineFrame = 0;
        if (!(outlineTarget instanceof HTMLElement)) {
            outline.style.opacity = '0';
            return;
        }

        const box = outlineTarget.getBoundingClientRect();
        outline.style.opacity = '1';
        outline.style.transform = `translate3d(${Math.max(0, box.left)}px, ${Math.max(0, box.top)}px, 0)`;
        outline.style.width = `${Math.max(0, box.width)}px`;
        outline.style.height = `${Math.max(0, box.height)}px`;
    };

    const scheduleOutlinePaint = () => {
        if (outlineFrame === 0) {
            outlineFrame = window.requestAnimationFrame(paintOutline);
        }
    };

    const cleanup = (result) => {
        document.body.classList.remove('nixcms-ai-pagebuilder-inspecting');
        if (cursorFrame !== 0) {
            window.cancelAnimationFrame(cursorFrame);
        }
        if (outlineFrame !== 0) {
            window.cancelAnimationFrame(outlineFrame);
        }
        [dimTop, dimRight, dimBottom, dimLeft, focusFrame, hintNode, cursorRing, outline].forEach((node) => node.remove());
        document.removeEventListener('mousemove', onMove, true);
        document.removeEventListener('pointerdown', blockPageBuilderInteraction, true);
        document.removeEventListener('mousedown', blockPageBuilderInteraction, true);
        document.removeEventListener('mouseup', blockPageBuilderInteraction, true);
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('dblclick', blockPageBuilderInteraction, true);
        document.removeEventListener('keydown', onKeyDown, true);
        window.removeEventListener('resize', updateSpotlight, true);
        window.removeEventListener('scroll', updateSpotlight, true);
        if (drawerWasOpen && typeof builder.openSettings === 'function') {
            builder.openSettings();
        }
        document.dispatchEvent(new CustomEvent('nixcms:assistant-pick-mode', {detail: {active: false}}));
        resolve(result);
    };

    const moveOutline = (element) => {
        outlineTarget = element instanceof HTMLElement ? element : null;
        scheduleOutlinePaint();
    };

    const eventInsideBuilder = (event) => interaction.contains(event.target);

    const onMove = (event) => {
        cursorX = event.clientX;
        cursorY = event.clientY;
        cursorVisible = true;
        scheduleCursorPaint();
        const target = pageBuilderTargetFromElement(builder, event.target);
        moveOutline(target?.element || null);
    };

    const blockPageBuilderInteraction = (event) => {
        if (eventInsideBuilder(event)) {
            event.preventDefault();
            event.stopPropagation();
        }
    };

    const onClick = (event) => {
        if (eventInsideBuilder(event)) {
            event.preventDefault();
            event.stopPropagation();
        }

        const target = pageBuilderTargetFromElement(builder, event.target);
        if (!target) {
            return;
        }

        builder.selectedBlockId = target.blockId;
        builder.selectedNestedPath = target.path || '';
        builder.renderSettings?.();
        cleanup(describePageBuilderTarget(builder, target));
    };

    const onKeyDown = (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        event.preventDefault();
        cleanup({success: false, cancelled: true, message: 'Zeigemodus abgebrochen.'});
    };

    document.addEventListener('mousemove', onMove, true);
    document.addEventListener('pointerdown', blockPageBuilderInteraction, true);
    document.addEventListener('mousedown', blockPageBuilderInteraction, true);
    document.addEventListener('mouseup', blockPageBuilderInteraction, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('dblclick', blockPageBuilderInteraction, true);
    document.addEventListener('keydown', onKeyDown, true);
    window.addEventListener('resize', updateSpotlight, true);
    window.addEventListener('scroll', updateSpotlight, true);
    updateSpotlight();
});

const registerThemeEditorAssistantActions = () => {
    const editor = window.NixCMS?.themeEditor;
    const runtime = assistantRuntime();
    if (!editor || editor.__assistantActionsRegistered) {
        return;
    }

    editor.__assistantActionsRegistered = true;
    runtime.registerContext('themeEditor', () => ({
        area: 'themeEditor',
        themeId: typeof editor.currentThemeId === 'function' ? editor.currentThemeId() : '',
        snapshot: typeof editor.snapshot === 'function' ? editor.snapshot() : null,
    }));
    runtime.register('themeEditor.setCss', {
        description: 'Replaces the current theme editor CSS',
        parameters: {css: 'string'},
        handler: ({css = ''} = {}) => {
            editor.setCss?.(normalizeGeneratedCode(css));
            editor.persistDraft?.();
            editor.lintAll?.();
            return {success: true};
        },
    });
    runtime.register('themeEditor.setJs', {
        description: 'Replaces the current theme editor JavaScript',
        parameters: {js: 'string'},
        handler: ({js = ''} = {}) => {
            editor.setJs?.(normalizeGeneratedCode(js));
            editor.persistDraft?.();
            editor.lintAll?.();
            return {success: true};
        },
    });
    runtime.register('themeEditor.updateMeta', {
        description: 'Updates the current theme editor metadata',
        parameters: {label: 'string', identifier: 'string', surfaces: 'object'},
        handler: (payload = {}) => {
            const snapshot = typeof editor.snapshot === 'function' ? editor.snapshot() : {};
            editor.applySnapshot?.({
                ...snapshot,
                ...payload,
            });
            editor.persistDraft?.();
            return {success: true};
        },
    });
};

const executeUiActions = async (result) => {
    const uiActions = result?.result?.uiActions ?? result?.result?.ui_actions ?? [];
    if (!Array.isArray(uiActions) || uiActions.length === 0) {
        return [];
    }

    const executed = [];
    for (const item of uiActions) {
        const name = trimValue(item?.action || item?.name || '');
        if (name === '') {
            continue;
        }

        executed.push(await assistantRuntime().run(name, item?.payload || {}));
    }

    return executed;
};

const canAutoRunToolCall = () => false;

const registerPageBuilderAssistantActions = () => {
    const builder = window.NixCMS?.pageBuilder;
    const runtime = assistantRuntime();
    if (!builder || builder.__assistantActionsRegistered) {
        return;
    }

    builder.__assistantActionsRegistered = true;
    runtime.registerContext('pageBuilder', () => ({
        area: 'pageBuilder',
        pageId: builder.savedId ? String(builder.savedId) : '',
        layoutId: builder.templateId || '',
        selectedBlockId: builder.selectedBlockId || '',
        selectedRegion: builder.selectedBlock?.()?.region || '',
        availableBlockModules: Array.isArray(builder.contentTypes)
            ? builder.contentTypes.map((type) => type.type || type.id || '').filter(Boolean)
            : [],
        availableBlockModuleDefinitions: Array.isArray(builder.contentTypes)
            ? builder.contentTypes.map((type) => ({
                type: type.type || type.id || '',
                label: typeof builder.uiText === 'function' ? builder.uiText(type.label || '') : (type.label || ''),
                description: typeof builder.uiText === 'function' ? builder.uiText(type.description || '') : (type.description || ''),
                regions: Array.isArray(type.regions) ? type.regions : [],
                unique: type.unique === true,
                fields: Array.isArray(type.fields) ? type.fields.map((field) => field?.name || '').filter(Boolean) : [],
            })).filter((type) => trimValue(type.type) !== '')
            : [],
        theme: typeof builder.currentThemeId === 'function' ? builder.currentThemeId() : '',
        pagePayload: typeof builder.pagePayload === 'function' ? builder.pagePayload() : null,
    }));
    runtime.register('pagebuilder.inspectElement', {
        description: 'Starts PageBuilder show mode and returns information about the clicked block or nested element',
        parameters: {
            hint: 'string',
        },
        handler: (payload = {}) => startPageBuilderInspectMode(payload),
    });
    runtime.register('pagebuilder.addBlock', {
        description: 'Adds a block to the current page builder view',
        parameters: {
            region: 'string',
            blockType: 'string',
            config: 'object',
        },
        handler: ({region = '', blockType = '', config = {}} = {}) => {
            if (typeof builder.addCustomSlot !== 'function') {
                throw new Error('The current page builder cannot add custom blocks.');
            }

            builder.addCustomSlot(blockType);
            if (builder.selectedBlockId && config && typeof config === 'object' && !Array.isArray(config)) {
                builder.blockContent[builder.selectedBlockId] = {
                    ...(builder.blockContent[builder.selectedBlockId] || {}),
                    props: {
                        ...(builder.blockContent[builder.selectedBlockId]?.props || {}),
                        ...config,
                    },
                };
            }
            if (builder.selectedBlockId && region && typeof builder.updateCustomSlot === 'function') {
                builder.updateCustomSlot(builder.selectedBlockId, {region});
            }
            builder.persistState?.();
            builder.render?.();

            return {success: true, blockId: builder.selectedBlockId};
        },
    });
    runtime.register('pagebuilder.updateBlock', {
        description: 'Updates a block in the current page builder view',
        parameters: {
            blockId: 'string',
            config: 'object',
        },
        handler: ({blockId = '', config = {}} = {}) => {
            const id = String(blockId || builder.selectedBlockId || '');
            if (id === '' || !builder.blockContent[id]) {
                throw new Error('The requested block is not available in the current page builder.');
            }
            if (!config || typeof config !== 'object' || Array.isArray(config)) {
                throw new Error('Block update config must be an object.');
            }

            builder.blockContent[id] = {
                ...builder.blockContent[id],
                props: {
                    ...(builder.blockContent[id]?.props || {}),
                    ...config,
                },
            };
            builder.selectedBlockId = id;
            builder.persistState?.();
            builder.render?.();

            return {success: true, blockId: id};
        },
    });
    runtime.register('pagebuilder.setTheme', {
        description: 'Sets the current page builder page theme',
        parameters: {
            themeId: 'string',
        },
        handler: ({themeId = ''} = {}) => {
            const id = trimValue(themeId);
            if (id === '') {
                throw new Error('Theme id is required.');
            }

            builder.persistedValues.themeId = id;
            builder.persistState?.();
            builder.render?.();

            return {success: true, themeId: id};
        },
    });
};

const sanitizeOllamaSchema = (schema) => {
    const normalized = schema && typeof schema === 'object' && !Array.isArray(schema)
        ? {...schema}
        : {};

    if (normalized.type === 'object' || normalized.properties !== undefined) {
        normalized.type = 'object';
        normalized.properties = normalized.properties && typeof normalized.properties === 'object' && !Array.isArray(normalized.properties)
            ? Object.fromEntries(Object.entries(normalized.properties).map(([key, value]) => [key, sanitizeOllamaSchema(value)]))
            : {};
        normalized.additionalProperties = normalized.additionalProperties === true;
    }

    if (normalized.type === 'array') {
        normalized.items = sanitizeOllamaSchema(normalized.items || {});
    }

    if (Array.isArray(normalized.required) && normalized.required.length === 0) {
        delete normalized.required;
    }

    return normalized;
};

const ollamaTools = (tools) => tools
    .filter((tool) => tool && trimValue(tool.name) !== '')
    .map((tool) => ({
        type: 'function',
        function: {
            name: trimValue(tool.name),
            description: trimValue(tool.description),
            parameters: sanitizeOllamaSchema(tool.inputSchema || {
                type: 'object',
                properties: {},
                additionalProperties: false,
            }),
        },
    }));

const toolMeta = (tool) => (tool && typeof tool.meta === 'object' && tool.meta !== null) ? tool.meta : {};

const textMatchesAny = (value, needles) => {
    const text = ` ${trimValue(value).toLowerCase()} `;
    return needles.some((needle) => text.includes(needle));
};

const categoryKeywords = {
    cms_status: [
        'system', 'status', 'health', 'gesundheit', 'queue', 'warteschlange', 'plugin', 'plugins',
        'rolle', 'rollen', 'berechtigung', 'berechtigungen', 'user', 'nutzer', 'benutzer',
    ],
    cms_content: [
        'artikel', 'article', 'seite', 'seiten', 'page', 'pages', 'content', 'inhalt', 'media',
        'medien', 'bild', 'bilder', 'datei', 'dateien', 'kategorie', 'tag', 'version',
    ],
    cms_write: [
        'erstelle', 'erstellen', 'anlegen', 'schreibe', 'speichern', 'draft', 'entwurf',
        'append', 'ergänze', 'aendere', 'ändere', 'publish', 'veröffentliche',
    ],
    cms_audit: [
        'log', 'logs', 'audit', 'protokoll', 'ereignis', 'ereignisse', 'geändert', 'geaendert',
        'passiert', 'wer hat', 'aktivität', 'aktivitaet',
    ],
    external_planning: [
        'wetter', 'temperatur', 'regen', 'schnee', 'uv', 'pollen', 'luftqualität', 'luftqualitaet',
        'feiertag', 'feiertage', 'brückentag', 'brueckentag', 'holiday', 'weather',
    ],
    external_business: [
        'währung', 'waehrung', 'wechselkurs', 'kurs', 'currency', 'exchange', 'euro', 'usd',
        'dollar', 'chf', 'gbp', 'preis', 'preise',
    ],
    external_trends: [
        'trend', 'trends', 'trendthemen', 'aktuell', 'themen', 'news', 'nachrichten',
        'was ist los', 'content ideen', 'kampagnenideen',
    ],
};

const wantsAllTools = (content) => textMatchesAny(content, [
    ' welche tools ', ' welche mcp ', ' alle tools ', ' tool liste ', ' werkzeuge ',
    ' what tools ', ' list tools ', ' available tools ', ' capabilities ',
]);

const selectToolCategories = (content) => {
    const categories = Object.entries(categoryKeywords)
        .filter(([, keywords]) => textMatchesAny(content, keywords))
        .map(([category]) => category);

    if (categories.includes('cms_write') && !categories.includes('cms_content')) {
        categories.push('cms_content');
    }

    return [...new Set(categories)];
};

const compactToolCatalog = (tools) => tools.map((tool) => ({
    name: trimValue(tool.name),
    description: trimValue(tool.description).slice(0, 220),
    category: String(toolMeta(tool).category || 'plugin'),
    source: String(toolMeta(tool).source || 'cms'),
    risk: String(toolMeta(tool).risk || 'read'),
    tags: Array.isArray(toolMeta(tool).tags) ? toolMeta(tool).tags.slice(0, 10) : [],
}));

const parseJsonObject = (value) => {
    const text = trimValue(value);
    if (text === '') {
        return null;
    }

    try {
        const parsed = JSON.parse(text);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
    } catch (error) {
        const match = text.match(/\{[\s\S]*\}/);
        if (!match) {
            return null;
        }

        try {
            const parsed = JSON.parse(match[0]);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
        } catch (innerError) {
            return null;
        }
    }
};

const routeToolsWithModel = async (baseUrl, model, tools, content) => {
    if (!Array.isArray(tools) || tools.length === 0 || trimValue(model) === '') {
        return null;
    }

    const response = await requestOllama(baseUrl, '/api/chat', {
        model,
        keep_alive: '0',
        think: false,
        stream: false,
        options: {
            temperature: 0,
            num_ctx: 4096,
            num_predict: 220,
        },
        messages: [
            {
                role: 'system',
                content: [
                    'You are a tool router for a CMS assistant.',
                    'Select only the tools needed for the latest user request.',
                    'Return JSON only with this shape: {"categories":["..."],"tools":["..."],"reason":"short"}',
                    'Use write-risk tools only when the user asks to create, modify, publish, delete, or append content.',
                    'If the user asks what tools are available, include all relevant categories.',
                    'Do not answer the user request.',
                ].join('\n'),
            },
            {
                role: 'user',
                content: JSON.stringify({
                    latest_user_message: content,
                    available_tools: compactToolCatalog(tools),
                }),
            },
        ],
    });
    const routed = parseJsonObject(stripModelReasoning(response?.message?.content ?? ''));
    if (!routed) {
        return null;
    }

    return {
        categories: Array.isArray(routed.categories) ? routed.categories.map((value) => trimValue(value)).filter(Boolean) : [],
        tools: Array.isArray(routed.tools) ? routed.tools.map((value) => trimValue(value)).filter(Boolean) : [],
        reason: trimValue(routed.reason || ''),
    };
};

const selectedToolsForMessage = (tools, content, routed = null) => {
    if (!Array.isArray(tools) || tools.length === 0) {
        return [];
    }

    if (wantsAllTools(content)) {
        return tools;
    }

    const routedNames = new Set(Array.isArray(routed?.tools) ? routed.tools : []);
    const routedCategories = Array.isArray(routed?.categories) ? routed.categories : [];
    if (routedNames.size > 0 || routedCategories.length > 0) {
        const selected = tools.filter((tool) => (
            routedNames.has(trimValue(tool.name))
            || routedCategories.includes(String(toolMeta(tool).category || ''))
        ));
        if (selected.length > 0) {
            return selected.slice(0, 24);
        }
    }

    const categories = selectToolCategories(content);
    if (categories.length === 0) {
        return tools.filter((tool) => toolMeta(tool).risk === 'read').slice(0, 10);
    }

    const selected = tools.filter((tool) => categories.includes(String(toolMeta(tool).category || '')));
    if (selected.length === 0) {
        return tools.filter((tool) => toolMeta(tool).risk === 'read').slice(0, 10);
    }

    const readTools = selected.filter((tool) => toolMeta(tool).risk !== 'write');
    const writeTools = selected.filter((tool) => toolMeta(tool).risk === 'write');

    return [...readTools, ...writeTools].slice(0, 16);
};

const normalizeToolArguments = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    if (typeof value === 'string' && trimValue(value) !== '') {
        try {
            const parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    return {};
};

const normalizeToolCalls = (response) => {
    const calls = response?.message?.tool_calls;
    return Array.isArray(calls) ? calls
        .map((call) => ({
            name: trimValue(call?.function?.name),
            arguments: normalizeToolArguments(call?.function?.arguments),
        }))
        .filter((call) => call.name !== '')
        : [];
};

const assistantToolMessage = (response) => {
    const toolCalls = normalizeToolCalls(response);
    const message = {
        role: 'assistant',
        tool_calls: toolCalls.map((call, index) => ({
            type: 'function',
            function: {
                index,
                name: call.name,
                arguments: call.arguments || {},
            },
        })),
    };
    const content = stripModelReasoning(response?.message?.content ?? '');
    if (content !== '') {
        message.content = content;
    }

    return message;
};

const toolArgumentsLabel = (args) => {
    const json = JSON.stringify(args || {}, null, 2);
    return json === '{}' ? 'Keine Argumente' : json;
};

const fallbackValue = (value) => {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'boolean') {
        return value ? 'yes' : 'no';
    }

    if (typeof value === 'number' || typeof value === 'string') {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.length === 0
            ? '[]'
            : JSON.stringify(value);
    }

    return JSON.stringify(value);
};

const toolResultMarkdown = (result) => {
    if (!result || typeof result !== 'object' || Array.isArray(result)) {
        return fallbackValue(result);
    }

    const entries = Object.entries(result);
    if (entries.length === 0) {
        return '-';
    }

    return [
        '| Feld | Wert |',
        '| --- | --- |',
        ...entries.map(([key, value]) => `| ${key} | ${fallbackValue(value).replace(/\|/g, '\\|')} |`),
    ].join('\n');
};

const toolResultsFallback = (results) => {
    if (!Array.isArray(results) || results.length === 0) {
        return 'Ollama returned an empty response.';
    }

    return [
        'Das Modell hat nach dem Tool-Aufruf keine ausformulierte Antwort geliefert. Hier ist das Tool-Ergebnis als kompakte Übersicht:',
        '',
        ...results.map((entry) => [
            `**Tool:** \`${entry.tool}\``,
            '',
            toolResultMarkdown(entry.result ?? null),
        ].join('\n')),
    ].join('\n');
};

const initChat = (root) => {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const panel = root.querySelector('[data-ai-panel]');
    const toggle = root.querySelector('[data-ai-toggle]');
    const close = root.querySelector('[data-ai-close]');
    const newChat = root.querySelector('[data-ai-new-chat]');
    const form = root.querySelector('[data-ai-form]');
    const input = root.querySelector('[data-ai-input]');
    const send = root.querySelector('[data-ai-send]');
    const messagesNode = root.querySelector('[data-ai-messages]');
    const scrollBottom = root.querySelector('[data-ai-scroll-bottom]');
    const statusNode = root.querySelector('[data-ai-chat-status]');
    const modeButtons = [...root.querySelectorAll('[data-ai-mode]')];
    const emptyMessageHtml = root.querySelector('[data-ai-empty-message]')?.outerHTML || '';
    const configuredEnabled = normalizeEnabled(root.dataset.aiEnabled || '0');
    const configuredAiType = trimValue(root.dataset.aiType || 'local');
    const configuredBaseUrl = trimValue(root.dataset.aiUrl || 'http://localhost:11434');
    const configuredModel = trimValue(root.dataset.aiModel || 'llama3.1:8b');
    const configuredInstructions = trimValue(root.dataset.aiInstructions || '');
    const configuredSystemPrompt = trimValue(root.dataset.aiSystemPrompt || '');
    const currentUserName = trimValue(root.dataset.aiUserName || '');
    const messages = normalizeStoredMessages(storedJson(storageKeys.messages, []));
    let mode = localStorage.getItem(storageKeys.mode) === 'static' ? 'static' : 'stream';
    let isBusy = false;
    let stickToBottom = true;
    let toolsPromise = null;
    let pickModeChatWasOpen = false;

    const currentAiType = () => storedText(storageKeys.settingsType, configuredAiType || 'local');
    const currentAgentEnabled = () => normalizeEnabled(storedText(storageKeys.settingsEnabled, configuredEnabled));
    const currentBaseUrl = () => storedText(storageKeys.settingsUrl, configuredBaseUrl || 'http://localhost:11434');
    const currentModel = () => storedText(storageKeys.settingsModel, configuredModel || 'llama3.1:8b');
    const currentInstructions = () => storedText(storageKeys.settingsInstructions, configuredInstructions);
    const currentSystemPrompt = () => configuredSystemPrompt || defaultSystemPrompt;

    const loadTools = async () => {
        toolsPromise = toolsPromise || apiJson('/api/admin/ai/tools')
            .then((data) => Array.isArray(data.tools) ? data.tools : [])
            .catch((error) => {
                toolsPromise = null;
                throw error;
            });

        return toolsPromise;
    };

    const selectTools = async (message, context, limit = 12) => {
        const data = await apiJson('/api/admin/ai/tools/select', {
            method: 'POST',
            body: JSON.stringify({
                _csrf: typeof window.NixCMS?.csrfToken === 'function'
                    ? String(window.NixCMS.csrfToken() || '')
                    : '',
                message,
                context,
                limit,
            }),
        });

        return Array.isArray(data.tools) ? data.tools : [];
    };

    const callTool = async (request, tools = null) => {
        const availableTools = tools || await loadTools();
        const allowed = availableTools.some((tool) => tool && tool.name === request.name);
        if (!allowed) {
            throw new Error(`Tool ${request.name} is not available for the current admin user.`);
        }

        const data = await apiJson('/api/admin/ai/tools/call', {
            method: 'POST',
            body: JSON.stringify({
                _csrf: typeof window.NixCMS?.csrfToken === 'function'
                    ? String(window.NixCMS.csrfToken() || '')
                    : '',
                name: request.name,
                arguments: request.arguments || {},
            }),
        }).catch((error) => {
            throw new Error(`NixCMS tool API failed for ${request.name}: ${error.message || error}`);
        });

        return {
            tool: data.tool || request.name,
            result: data.result ?? null,
        };
    };

    const setStatus = (value) => {
        if (statusNode) {
            statusNode.textContent = value;
        }
    };
    const syncConfiguredStatus = () => {
        setStatus(currentAiType() === 'local' ? currentModel() : 'Remote mode is not available yet.');
    };

    const syncAgentVisibility = () => {
        const enabled = currentAgentEnabled() === '1';
        root.classList.toggle('hidden', !enabled);
        root.classList.toggle('grid', enabled);
        if (toggle instanceof HTMLButtonElement) {
            toggle.disabled = !enabled;
        }
    };

    const setScrollButtonVisible = (visible) => {
        if (!(scrollBottom instanceof HTMLButtonElement)) {
            return;
        }

        scrollBottom.classList.toggle('opacity-0', !visible);
        scrollBottom.classList.toggle('pointer-events-none', !visible);
        scrollBottom.classList.toggle('translate-y-2', !visible);
    };

    const persistScrollPosition = () => {
        if (!(messagesNode instanceof HTMLElement)) {
            return;
        }

        stickToBottom = isNearBottom(messagesNode);
        setScrollButtonVisible(!stickToBottom);

        try {
            localStorage.setItem(
                storageKeys.scroll,
                stickToBottom ? 'bottom' : `top:${Math.round(messagesNode.scrollTop)}`
            );
        } catch (error) {}
    };

    const restoreScrollPosition = () => {
        if (!(messagesNode instanceof HTMLElement)) {
            return;
        }

        let stored = 'bottom';
        try {
            stored = localStorage.getItem(storageKeys.scroll) || 'bottom';
        } catch (error) {}

        if (stored.startsWith('top:')) {
            const top = Number.parseInt(stored.slice(4), 10);
            messagesNode.scrollTop = Number.isFinite(top)
                ? Math.max(0, Math.min(top, messagesNode.scrollHeight - messagesNode.clientHeight))
                : messagesNode.scrollHeight;
        } else {
            messagesNode.scrollTop = messagesNode.scrollHeight;
        }

        stickToBottom = isNearBottom(messagesNode);
        setScrollButtonVisible(!stickToBottom);
    };

    const scrollToBottomAndPersist = (behavior = 'smooth') => {
        scrollToEnd(messagesNode, behavior);
        window.setTimeout(persistScrollPosition, behavior === 'smooth' ? 180 : 0);
    };

    const lockToBottom = () => {
        if (!(messagesNode instanceof HTMLElement) || !stickToBottom) {
            return;
        }

        messagesNode.scrollTop = messagesNode.scrollHeight;
        setScrollButtonVisible(false);
        try {
            localStorage.setItem(storageKeys.scroll, 'bottom');
        } catch (error) {}

        window.requestAnimationFrame(() => {
            if (stickToBottom && messagesNode instanceof HTMLElement) {
                messagesNode.scrollTop = messagesNode.scrollHeight;
            }
        });

        window.setTimeout(() => {
            if (stickToBottom && messagesNode instanceof HTMLElement) {
                messagesNode.scrollTop = messagesNode.scrollHeight;
            }
        }, 40);
    };

    const setOpen = (open, options = {}) => {
        if (!(panel instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement)) {
            return;
        }

        const persist = options.persist !== false;
        const focus = options.focus !== false;
        panel.classList.toggle('pointer-events-none', !open);
        panel.classList.toggle('opacity-0', !open);
        panel.classList.toggle('translate-y-4', !open);
        panel.classList.toggle('scale-[.98]', !open);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.classList.toggle('opacity-0', open);
        toggle.classList.toggle('pointer-events-none', open);
        document.documentElement.classList.toggle('local-ai-chat-open', open);

        if (persist) {
            try {
                localStorage.setItem(storageKeys.open, open ? 'open' : 'closed');
            } catch (error) {}
        }

        if (open) {
            window.requestAnimationFrame(restoreScrollPosition);
        }

        if (open && focus && input instanceof HTMLTextAreaElement) {
            window.setTimeout(() => input.focus(), 150);
        }
    };

    const setMode = (nextMode) => {
        mode = nextMode === 'static' ? 'static' : 'stream';
        localStorage.setItem(storageKeys.mode, mode);
        modeButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }
            const active = button.dataset.aiMode === mode;
            button.className = `min-h-8 rounded-lg border px-3 text-xs font-semibold transition ${modeButtonClass(active)}`;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const setBusy = (busy) => {
        isBusy = busy;
        if (send instanceof HTMLButtonElement) {
            send.disabled = busy || currentAiType() !== 'local' || currentAgentEnabled() !== '1';
        }
        if (input instanceof HTMLTextAreaElement) {
            input.disabled = busy || currentAiType() !== 'local' || currentAgentEnabled() !== '1';
        }
    };

    const renderEmptyMessage = () => {
        if (messagesNode instanceof HTMLElement && emptyMessageHtml !== '') {
            messagesNode.innerHTML = emptyMessageHtml;
            const message = messagesNode.querySelector('[data-ai-empty-message]');
            const templates = currentUserName !== '' ? greetingTemplates : anonymousGreetingTemplates;
            const greeting = randomItem(templates)(currentUserName);
            if (message instanceof HTMLElement) {
                message.textContent = greeting;
            }
        }
    };

    const appendMessage = (role, content) => {
        if (!(messagesNode instanceof HTMLElement)) {
            return null;
        }

        const wrapper = createElement('article', messageClass(role));
        renderMessageContent(wrapper, role, content);
        messagesNode.appendChild(wrapper);
        if (stickToBottom) {
            lockToBottom();
        } else {
            setScrollButtonVisible(true);
        }
        return wrapper;
    };

    const setAssistantThinking = (node, label = 'Denke nach...') => {
        if (node instanceof HTMLElement) {
            node.innerHTML = thinkingHtml(label);
            lockToBottom();
        }
    };

    const requestToolApproval = (node, toolCalls) => new Promise((resolve) => {
        if (!(node instanceof HTMLElement)) {
            resolve(false);
            return;
        }

        node.innerHTML = '';
        const wrapper = createElement('div', 'grid gap-3');
        const header = createElement('div', 'grid gap-1');
        header.append(
            createElement('strong', 'text-sm font-semibold text-slate-900 dark:text-white', 'Tool-Anfrage'),
            createElement('span', 'text-xs leading-5 text-slate-500 dark:text-slate-400', 'Das Modell möchte CMS-Tools nutzen. Bitte bestätige die Ausführung.'),
        );
        wrapper.append(header);

        toolCalls.forEach((toolCall) => {
            const item = createElement('div', 'grid gap-2 rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-950/50');
            item.append(createElement('code', 'text-xs font-bold text-[var(--admin-page-accent)]', toolCall.name));
            item.append(createElement('pre', 'm-0 max-h-32 overflow-auto whitespace-pre-wrap break-words rounded-md bg-slate-100 p-2 text-[11px] leading-5 text-slate-600 dark:bg-slate-900 dark:text-slate-300', toolArgumentsLabel(toolCall.arguments)));
            wrapper.append(item);
        });

        const actions = createElement('div', 'flex flex-wrap justify-end gap-2');
        const deny = createElement('button', 'inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-white', 'Nicht ausführen');
        const approve = createElement('button', 'inline-flex min-h-9 items-center justify-center rounded-lg bg-[var(--admin-page-accent)] px-3 text-xs font-semibold text-white transition hover:brightness-95', 'Ausführen');
        deny.type = 'button';
        approve.type = 'button';
        actions.append(deny, approve);
        wrapper.append(actions);
        node.append(wrapper);
        lockToBottom();
        window.requestAnimationFrame(lockToBottom);
        window.setTimeout(lockToBottom, 80);

        approve.addEventListener('click', () => resolve(true), {once: true});
        deny.addEventListener('click', () => resolve(false), {once: true});
    });

    const renderStoredMessages = () => {
        if (!(messagesNode instanceof HTMLElement)) {
            return;
        }

        if (messages.length === 0) {
            renderEmptyMessage();
            return;
        }

        messagesNode.innerHTML = '';
        messages.forEach((message) => appendMessage(message.role, message.content));
    };

    const persistMessages = () => persistJson(storageKeys.messages, messages);

    const resetChat = () => {
        messages.length = 0;
        renderEmptyMessage();
        try {
            localStorage.removeItem(storageKeys.messages);
            localStorage.removeItem(storageKeys.draft);
            localStorage.setItem(storageKeys.scroll, 'bottom');
        } catch (error) {}
        if (input instanceof HTMLTextAreaElement) {
            input.value = '';
            autosize(input);
        }
        stickToBottom = true;
        setScrollButtonVisible(false);
        syncConfiguredStatus();
        scrollToBottomAndPersist('auto');
    };

    const submitMessage = async () => {
        if (isBusy || currentAgentEnabled() !== '1' || currentAiType() !== 'local' || !(input instanceof HTMLTextAreaElement)) {
            return;
        }

        const content = trimValue(input.value);
        if (content === '') {
            return;
        }

        input.value = '';
        try {
            localStorage.removeItem(storageKeys.draft);
        } catch (error) {}
        autosize(input);
        messages.push({role: 'user', content});
        persistMessages();
        stickToBottom = true;
        appendMessage('user', content);
        const assistantMessage = appendMessage('assistant', '');
        setAssistantThinking(assistantMessage);
        setBusy(true);
        setStatus(mode === 'stream' ? 'Streaming...' : 'Thinking...');

        let responseText = '';
        registerGlobalAssistantActions();
        registerPageBuilderAssistantActions();
        registerThemeEditorAssistantActions();
        const directCommand = directAssistantCommandFor(content);

        if (directCommand?.name === 'pagebuilder.inspect') {
            setStatus('Zeigemodus aktiv');
            setAssistantThinking(assistantMessage, 'Zeigemodus aktiv...');
            try {
                const result = await startPageBuilderInspectMode({
                    hint: 'Zeigemodus aktiv: Klick im PageBuilder auf einen Block oder ein Element. Escape bricht ab.',
                });
                const finalText = pageBuilderInspectResultText(result);
                if (assistantMessage) {
                    renderMessageContent(assistantMessage, 'assistant', finalText);
                    lockToBottom();
                }
                messages.push({role: 'assistant', content: finalText});
                persistMessages();
            } catch (error) {
                const message = error.message || 'Zeigemodus konnte nicht gestartet werden.';
                if (assistantMessage) {
                    renderMessageContent(assistantMessage, 'assistant', message);
                }
                messages.push({role: 'assistant', content: message});
                persistMessages();
            } finally {
                setBusy(false);
                setStatus(currentModel());
            }
            return;
        }

        const selectedModel = currentModel();
        const uiContext = collectAssistantContext();
        const availableTools = await loadTools().catch(() => []);
        setStatus('Selecting tools...');
        const selectedByServer = await selectTools(content, uiContext, 12).catch(() => []);
        const routedTools = selectedByServer.length > 0 ? null : await routeToolsWithModel(currentBaseUrl(), selectedModel, availableTools, content)
            .catch(() => null);
        const activeTools = selectedByServer.length > 0
            ? selectedByServer
            : selectedToolsForMessage(availableTools, content, routedTools);
        const tools = ollamaTools(activeTools);
        setStatus(mode === 'stream' ? 'Streaming...' : 'Thinking...');
        const basePayload = {
            model: selectedModel,
            messages: chatMessages(messages, currentSystemPrompt(), currentInstructions(), uiContext),
            ...(tools.length > 0 ? {tools} : {}),
            keep_alive: '0',
            think: false,
            options: {
                temperature: 0.2,
                num_ctx: 4096,
                num_predict: 700,
            },
        };

        try {
            const toolResults = [];
            let payload = basePayload;
            let aborted = false;

            for (let round = 0; round < 5; round += 1) {
                responseText = '';

                let response = null;
                try {
                    response = mode === 'stream'
                        ? await requestOllama(currentBaseUrl(), '/api/chat', payload, (delta) => {
                            responseText += delta;
                            if (assistantMessage) {
                                assistantMessage.textContent = responseText;
                            }
                            if (stickToBottom) {
                                lockToBottom();
                            }
                        })
                        : await requestOllama(currentBaseUrl(), '/api/chat', {
                            ...payload,
                            stream: false,
                        });
                } catch (error) {
                    if (toolResults.length > 0) {
                        responseText = [
                            toolResultsFallback(toolResults),
                            '',
                            `Ollama follow-up after tool result failed: ${error.message || error}`,
                        ].join('\n');
                        break;
                    }

                    throw error;
                }

                const toolCalls = normalizeToolCalls(response);
                if (toolCalls.length === 0) {
                    responseText = stripModelReasoning(responseText || (response?.message?.content ?? ''));
                    break;
                }

                const requiresApproval = !toolCalls.every(canAutoRunToolCall);
                setStatus(requiresApproval ? 'Tool confirmation required' : 'Running tools...');
                const approved = requiresApproval
                    ? await requestToolApproval(assistantMessage, toolCalls)
                    : true;
                if (!approved) {
                    responseText = 'Tool-Anfrage abgebrochen.';
                    if (assistantMessage) {
                        renderMessageContent(assistantMessage, 'assistant', responseText);
                    }
                    messages.push({role: 'assistant', content: responseText});
                    persistMessages();
                    setStatus(selectedModel);
                    aborted = true;
                    return;
                }

                const chatWithTools = [
                    ...payload.messages,
                    assistantToolMessage(response),
                ];

                for (const toolCall of toolCalls) {
                    setStatus(`Running ${toolCall.name}...`);
                    setAssistantThinking(assistantMessage, `Nutze ${toolCall.name}...`);

                    let result = null;
                    try {
                        result = await callTool(toolCall, activeTools);
                    } catch (error) {
                        result = {
                            tool: toolCall.name,
                            result: {
                                success: false,
                                error: error.message || 'Tool call failed.',
                            },
                        };
                    }

                    let uiActionResults = [];
                    let uiActionError = '';
                    try {
                        uiActionResults = await executeUiActions(result);
                    } catch (error) {
                        uiActionError = `UI action failed: ${error.message || error}`;
                    }
                    chatWithTools.push({
                        role: 'tool',
                        tool_name: result.tool || toolCall.name,
                        content: JSON.stringify({
                            result: result.result ?? null,
                            ...(uiActionResults.length > 0 ? {uiActions: uiActionResults} : {}),
                            ...(uiActionError !== '' ? {success: false, error: uiActionError} : {}),
                        }),
                    });
                    toolResults.push(result);
                }

                payload = {
                    ...payload,
                    messages: chatWithTools,
                };

                if (round === 4) {
                    responseText = toolResultsFallback(toolResults);
                    break;
                }

                setStatus(mode === 'stream' ? 'Streaming...' : 'Thinking...');
                setAssistantThinking(assistantMessage);
            }

            if (!aborted && responseText === '' && toolResults.length > 0) {
                responseText = toolResultsFallback(toolResults);
            }

            const finalText = responseText || 'Ollama returned an empty response.';
            if (assistantMessage) {
                renderMessageContent(assistantMessage, 'assistant', finalText);
                lockToBottom();
            }
            messages.push({role: 'assistant', content: finalText});
            persistMessages();
            setStatus(selectedModel);
        } catch (error) {
            const message = error.message || 'Could not generate a response.';
            if (assistantMessage) {
                renderMessageContent(assistantMessage, 'assistant', message);
            }
            messages.push({role: 'assistant', content: message});
            persistMessages();
            setStatus('Connection failed');
        } finally {
            setBusy(false);
            if (stickToBottom) {
                scrollToBottomAndPersist('auto');
            }
        }
    };

    toggle?.addEventListener('click', () => setOpen(true));
    close?.addEventListener('click', () => setOpen(false));
    newChat?.addEventListener('click', resetChat);
    scrollBottom?.addEventListener('click', () => scrollToBottomAndPersist('smooth'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    modeButtons.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.aiMode || 'stream'));
    });

    input?.addEventListener('input', () => autosize(input));
    input?.addEventListener('input', () => {
        try {
            localStorage.setItem(storageKeys.draft, input.value);
        } catch (error) {}
    });
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submitMessage();
        }
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitMessage();
    });
    messagesNode?.addEventListener('scroll', persistScrollPosition, {passive: true});
    document.addEventListener('nixcms:assistant-pick-mode', (event) => {
        const active = event?.detail?.active === true;
        if (active) {
            pickModeChatWasOpen = panel instanceof HTMLElement && panel.getAttribute('aria-hidden') === 'false';
            if (pickModeChatWasOpen) {
                setOpen(false, {persist: false, focus: false});
            }
            return;
        }

        if (pickModeChatWasOpen) {
            setOpen(true, {persist: false, focus: false});
        }
        pickModeChatWasOpen = false;
    });

    renderStoredMessages();
    restoreScrollPosition();
    if (input instanceof HTMLTextAreaElement) {
        try {
            input.value = localStorage.getItem(storageKeys.draft) || '';
        } catch (error) {}
        autosize(input);
    }
    setMode(mode);
    syncAgentVisibility();
    syncConfiguredStatus();
    setBusy(false);
    setOpen(storedText(storageKeys.open, 'closed') === 'open', {persist: false, focus: false});
    window.addEventListener('storage', (event) => {
        if (event.key === storageKeys.open) {
            setOpen(event.newValue === 'open', {persist: false, focus: false});
        }

        if ([
            storageKeys.settingsModel,
            storageKeys.settingsUrl,
            storageKeys.settingsType,
            storageKeys.settingsEnabled,
            storageKeys.settingsInstructions,
        ].includes(event.key || '')) {
            syncAgentVisibility();
            syncConfiguredStatus();
            setBusy(false);
        }
    });
    window.addEventListener('nixcms:local-ai-settings-changed', (event) => {
        if (event?.detail?.key === storageKeys.settingsEnabled) {
            syncAgentVisibility();
            setBusy(false);
        }
    });
};

document.querySelectorAll('[data-local-ai-chat]').forEach(initChat);
