import { requestOllama, stripModelReasoning, trimValue } from './ollama-client.js';

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

const autosize = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 144)}px`;
};

const defaultSystemPrompt = [
    'You are the Local AI assistant inside the NixCMS admin UI.',
    'Do not claim direct database, filesystem, CMS, MCP, API, or tool access unless an explicit tool result is provided in the conversation.',
    'Do not invent CMS facts such as article counts, IDs, titles, statuses, users, roles, plugin names, queue numbers, or health states.',
    'If current CMS state is requested without a tool result, say that you do not know yet and that a CMS tool must be run first.',
    'Answer in the user language when it is clear.',
].join('\n');

const systemContent = (systemPrompt, instructions) => [
        systemPrompt !== '' ? systemPrompt : defaultSystemPrompt,
        instructions !== '' ? `Additional installation instructions:\n${instructions}` : '',
    ].filter(Boolean).join('\n\n');

const chatMessages = (messages, systemPrompt, instructions) => [
    {role: 'system', content: systemContent(systemPrompt, instructions)},
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

const ollamaTools = (tools) => tools
    .filter((tool) => tool && trimValue(tool.name) !== '')
    .map((tool) => ({
        type: 'function',
        function: {
            name: trimValue(tool.name),
            description: trimValue(tool.description),
            parameters: tool.inputSchema || {
                type: 'object',
                properties: {},
                additionalProperties: false,
            },
        },
    }));

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
        const selectedModel = currentModel();
        const availableTools = await loadTools().catch(() => []);
        const tools = ollamaTools(availableTools);
        const basePayload = {
            model: selectedModel,
            messages: chatMessages(messages, currentSystemPrompt(), currentInstructions()),
            tools,
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

                setStatus('Tool confirmation required');
                const approved = await requestToolApproval(assistantMessage, toolCalls);
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
                        result = await callTool(toolCall, availableTools);
                    } catch (error) {
                        result = {
                            tool: toolCall.name,
                            result: {
                                success: false,
                                error: error.message || 'Tool call failed.',
                            },
                        };
                    }

                    chatWithTools.push({
                        role: 'tool',
                        tool_name: result.tool || toolCall.name,
                        content: JSON.stringify(result.result ?? null),
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
