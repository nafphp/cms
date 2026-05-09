import { requestOllama, stripModelReasoning, trimValue } from '../ollama-client.js';
import { anonymousGreetingTemplates, greetingTemplates, loadCmsTranslations, randomItem, uiLanguage, uiText } from './i18n.js';
import { apiJson, autosize, createElement, isNearBottom, normalizeEnabled, normalizeStoredMessages, persistJson, scrollToEnd, storedJson, storedText } from './dom.js';
import { messageClass, modeButtonClass, renderMessageContent, thinkingHtml } from './markdown.js';
import { chatMessages, defaultSystemPrompt, userSoundsDissatisfied } from './prompting.js';
import { collectAssistantContext, registerGlobalAssistantActions } from './runtime.js';
import { registerPageBuilderAssistantActions } from './pagebuilder.js';
import { executeUiActions, registerThemeEditorAssistantActions } from './ui-actions.js';
import { canAutoRunToolCall, localModelKeepAlive, normalizeToolCalls, ollamaTools, sanitizeVisibleToolIdentifiers, toolActivityLine, toolArgumentsLabel, toolByName, toolDisplayName, toolResultsGroundingMessage } from './tools.js';

const storageKeys = {
    open: 'nixcms:local-ai-chat:open',
    mode: 'nixcms:local-ai-chat:mode',
    toolConfirmation: 'nixcms:local-ai-chat:tool-confirmation',
    messages: 'nixcms:local-ai-chat:messages',
    feedbackQueue: 'nixcms:local-ai-chat:feedback-queue',
    scroll: 'nixcms:local-ai-chat:scroll',
    draft: 'nixcms:local-ai-chat:draft',
    settingsModel: 'nixcms:local-ai-settings:model',
    settingsUrl: 'nixcms:local-ai-settings:url',
    settingsType: 'nixcms:local-ai-settings:type',
    settingsEnabled: 'nixcms:local-ai-settings:enabled',
    settingsInstructions: 'nixcms:local-ai-settings:instructions',
};

export const initChat = (root) => {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const panel = root.querySelector('[data-ai-panel]');
    const toggle = root.querySelector('[data-ai-toggle]');
    const close = root.querySelector('[data-ai-close]');
    const newChat = root.querySelector('[data-ai-new-chat]');
    const toolConfirmation = root.querySelector('[data-ai-tool-confirmation]');
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
    let assistantAutoScrollPaused = false;
    let toolsPromise = null;
    let warmupPromise = null;
    let warmedModelKey = '';
    let warmedModelCheckedAt = 0;
    let warmupTimer = 0;
    let pickModeChatWasOpen = false;
    let feedbackFlushPromise = null;

    const currentAiType = () => storedText(storageKeys.settingsType, configuredAiType || 'local');
    const currentAgentEnabled = () => normalizeEnabled(storedText(storageKeys.settingsEnabled, configuredEnabled));
    const currentBaseUrl = () => storedText(storageKeys.settingsUrl, configuredBaseUrl || 'http://localhost:11434');
    const currentModel = () => storedText(storageKeys.settingsModel, configuredModel || 'llama3.1:8b');
    const currentInstructions = () => storedText(storageKeys.settingsInstructions, configuredInstructions);
    const currentSystemPrompt = () => configuredSystemPrompt || defaultSystemPrompt;
    const currentToolConfirmationEnabled = () => storedText(storageKeys.toolConfirmation, '1') !== '0';

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

    const csrfPayload = () => ({
        _csrf: typeof window.NixCMS?.csrfToken === 'function'
            ? String(window.NixCMS.csrfToken() || '')
            : '',
    });

    const postMistake = (payload) => apiJson('/api/admin/ai/mistakes', {
        method: 'POST',
        keepalive: true,
        body: JSON.stringify({
            ...csrfPayload(),
            ...payload,
        }),
    });

    const recordMistake = (event, context = '', correction = '') => {
        postMistake({event, context, correction}).catch(() => {});
    };

    const compactFeedbackText = (value, limit = 6000) => {
        const text = trimValue(String(value ?? '').replace(/\s+/g, ' '));

        return text.length > limit ? `${text.slice(0, limit - 1)}...` : text;
    };

    const feedbackId = () => {
        if (typeof globalThis.crypto?.randomUUID === 'function') {
            return globalThis.crypto.randomUUID();
        }

        return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    };

    const storedFeedbackQueue = () => {
        try {
            const queue = JSON.parse(localStorage.getItem(storageKeys.feedbackQueue) || '[]');

            return Array.isArray(queue) ? queue.filter((item) => item && typeof item === 'object') : [];
        } catch (error) {
            return [];
        }
    };

    const persistFeedbackQueue = (queue) => {
        try {
            localStorage.setItem(storageKeys.feedbackQueue, JSON.stringify(queue.slice(-20)));
        } catch (error) {}
    };

    const mistakePayloadForFeedback = (question, answer) => ({
        event: 'Assistant answer was marked as not helpful by the user.',
        context: [
            `User question: ${compactFeedbackText(question)}`,
            `Assistant answer: ${compactFeedbackText(answer)}`,
        ].join(' | '),
        correction: 'Review this question and answer in the mistake review flow, ask for missing context if needed, and distill durable guidance into MEMORY.md once resolved.',
    });

    const flushMistakeFeedbackQueue = async () => {
        if (feedbackFlushPromise) {
            return feedbackFlushPromise;
        }

        feedbackFlushPromise = (async () => {
            let queue = storedFeedbackQueue();
            while (queue.length > 0) {
                const item = queue[0];
                try {
                    await postMistake(item.payload || {});
                    queue = storedFeedbackQueue().filter((candidate) => candidate.id !== item.id);
                    persistFeedbackQueue(queue);
                    window.dispatchEvent(new CustomEvent('nixcms:ai-mistake-recorded'));
                } catch (error) {
                    break;
                }
            }
        })().finally(() => {
            feedbackFlushPromise = null;
        });

        return feedbackFlushPromise;
    };

    const enqueueMistakeFeedback = (question, answer) => {
        const item = {
            id: feedbackId(),
            createdAt: new Date().toISOString(),
            payload: mistakePayloadForFeedback(question, answer),
        };
        const queue = storedFeedbackQueue();
        queue.push(item);
        persistFeedbackQueue(queue);
        flushMistakeFeedbackQueue();
    };

    const feedbackIcon = (type) => type === 'up'
        ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10v12"/><path d="M15 5.9 14 10h5.8a2 2 0 0 1 1.9 2.6l-2.3 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.8a2 2 0 0 0 1.8-1.1L12 2a3.1 3.1 0 0 1 3 3.9Z"/></svg>'
        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.1 10 14H4.2a2 2 0 0 1-1.9-2.6l2.3-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.8a2 2 0 0 0-1.8 1.1L12 22a3.1 3.1 0 0 1-3-3.9Z"/></svg>';

    const createFeedbackButton = (type, label) => {
        const button = createElement('button', 'local-ai-feedback__button');
        button.type = 'button';
        button.dataset.aiFeedbackButton = type;
        button.title = label;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', 'false');
        button.innerHTML = feedbackIcon(type);

        return button;
    };

    const enableAssistantFeedback = (node, question, answer) => {
        if (
            !(node instanceof HTMLElement)
            || node.querySelector('[data-ai-feedback]')
            || trimValue(question) === ''
            || trimValue(answer) === ''
        ) {
            return;
        }

        node.classList.add('local-ai-message-feedback-host');

        const controls = createElement('div', 'local-ai-feedback');
        controls.dataset.aiFeedback = '1';
        const up = createFeedbackButton('up', uiText('Good answer'));
        const down = createFeedbackButton('down', uiText('Report answer issue'));
        controls.append(up, down);
        node.append(controls);

        up.addEventListener('click', () => {
            up.setAttribute('aria-pressed', 'true');
            down.setAttribute('aria-pressed', 'false');
        });

        down.addEventListener('click', () => {
            if (down.dataset.aiFeedbackSent === '1') {
                return;
            }

            down.dataset.aiFeedbackSent = '1';
            down.setAttribute('aria-pressed', 'true');
            up.setAttribute('aria-pressed', 'false');
            down.title = uiText('Feedback saved');
            down.setAttribute('aria-label', uiText('Feedback saved'));
            enqueueMistakeFeedback(question, answer);
        });
    };

    const recordMistakeFromDissatisfaction = (content) => {
        const previousAssistant = [...messages].reverse().find((message) => message.role === 'assistant');
        recordMistake(
            'User dissatisfaction signal in Nixorious chat.',
            [
                `User message: ${content}`,
                previousAssistant ? `Previous assistant answer: ${previousAssistant.content}` : '',
            ].filter(Boolean).join(' | '),
            'Review identity, tool execution, UI state, and prompt clarity before answering similar requests.'
        );
    };

    const setStatus = (value) => {
        if (statusNode) {
            statusNode.textContent = value;
        }
    };
    const syncConfiguredStatus = () => {
        setStatus(currentAiType() === 'local' ? currentModel() : uiText('Remote mode is not available yet.'));
    };

    const warmupKey = () => `${currentBaseUrl()}::${currentModel()}`;
    const warmupFreshMs = 12 * 60 * 1000;
    const isWarmupFresh = () => warmedModelKey === warmupKey() && Date.now() - warmedModelCheckedAt < warmupFreshMs;

    const isConfiguredModelLoaded = async () => {
        const model = trimValue(currentModel());
        if (model === '') {
            return false;
        }

        const data = await requestOllama(currentBaseUrl(), '/api/ps');
        const models = Array.isArray(data?.models) ? data.models : [];

        return models.some((item) => {
            const name = trimValue(item?.name || item?.model);
            return name === model || name.replace(/:latest$/, '') === model.replace(/:latest$/, '');
        });
    };

    const markModelWarm = (key) => {
        warmedModelKey = key;
        warmedModelCheckedAt = Date.now();
    };

    const warmUpModel = async ({forceCheck = false} = {}) => {
        if (
            currentAgentEnabled() !== '1'
            || currentAiType() !== 'local'
            || trimValue(currentModel()) === ''
        ) {
            return false;
        }

        const key = warmupKey();
        if (!forceCheck && isWarmupFresh()) {
            return true;
        }

        if (warmupPromise) {
            return warmupPromise;
        }

        if (!isBusy && statusNode && panel instanceof HTMLElement && panel.getAttribute('aria-hidden') === 'false') {
            setStatus(uiText('Checking model...'));
        }

        warmupPromise = isConfiguredModelLoaded()
            .then((loaded) => {
                if (loaded) {
                    markModelWarm(key);
                    return true;
                }

                if (!isBusy && statusNode && panel instanceof HTMLElement && panel.getAttribute('aria-hidden') === 'false') {
                    setStatus(uiText('Warming up...'));
                }

                return requestOllama(currentBaseUrl(), '/api/chat', {
                    model: currentModel(),
                    keep_alive: localModelKeepAlive,
                    stream: false,
                    think: false,
                    options: {
                        temperature: 0,
                        num_ctx: 512,
                        num_predict: 1,
                    },
                    messages: [
                        {role: 'system', content: 'Warm up the local model. Reply with one short token.'},
                        {role: 'user', content: 'ping'},
                    ],
                });
            })
            .then(() => {
                markModelWarm(key);
                return true;
            })
            .catch(() => false)
            .finally(() => {
                warmupPromise = null;
                if (!isBusy) {
                    syncConfiguredStatus();
                }
            });

        return warmupPromise;
    };

    const scheduleWarmup = (delay = 700, options = {}) => {
        const forceCheck = options.forceCheck === true;
        if (warmupTimer !== 0 || (!forceCheck && isWarmupFresh())) {
            return;
        }

        warmupTimer = window.setTimeout(() => {
            warmupTimer = 0;
            warmUpModel({forceCheck});
        }, delay);
    };

    const requestWarmupSoon = (options = {}) => scheduleWarmup(
        document.visibilityState === 'hidden' ? 1000 : 0,
        options
    );

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
        if (!(messagesNode instanceof HTMLElement) || !stickToBottom || assistantAutoScrollPaused) {
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
            requestWarmupSoon();
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

    const setToolConfirmation = (enabled, persist = true) => {
        if (toolConfirmation instanceof HTMLButtonElement) {
            const track = toolConfirmation.querySelector('[data-ai-tool-confirmation-track]');
            const knob = toolConfirmation.querySelector('[data-ai-tool-confirmation-knob]');
            toolConfirmation.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            toolConfirmation.classList.toggle('text-[var(--admin-page-accent)]', enabled);
            if (track instanceof HTMLElement) {
                track.classList.toggle('bg-[color-mix(in_srgb,var(--admin-page-accent)_76%,#334155_24%)]', enabled);
                track.classList.toggle('dark:bg-[color-mix(in_srgb,var(--admin-page-accent)_76%,#334155_24%)]', enabled);
                track.classList.toggle('bg-slate-300', !enabled);
                track.classList.toggle('dark:bg-slate-700', !enabled);
            }
            if (knob instanceof HTMLElement) {
                knob.style.transform = enabled ? 'translateX(0.75rem)' : 'translateX(0)';
            }
        }
        if (persist) {
            try {
                localStorage.setItem(storageKeys.toolConfirmation, enabled ? '1' : '0');
            } catch (error) {}
        }
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

    const appendMessage = (role, content, options = {}) => {
        if (!(messagesNode instanceof HTMLElement)) {
            return null;
        }

        const wrapper = createElement('article', messageClass(role));
        renderMessageContent(wrapper, role, content);
        if (role === 'assistant') {
            enableAssistantFeedback(wrapper, options.question || '', content);
        }
        messagesNode.appendChild(wrapper);
        if (stickToBottom) {
            lockToBottom();
        } else {
            setScrollButtonVisible(true);
        }
        return wrapper;
    };

    const streamMessageContent = async (message, role, content, toolsForSanitizing = []) => {
        if (!(message instanceof HTMLElement)) {
            return;
        }

        const text = String(content ?? '');
        if (text === '') {
            renderMessageContent(message, role, '');
            return;
        }

        let visible = '';
        const chunks = text.match(/.{1,10}(\s|$)|.{1,10}/gs) || [text];
        for (const chunk of chunks) {
            visible += chunk;
            renderMessageContent(message, role, sanitizeVisibleToolIdentifiers(visible, toolsForSanitizing));
            await new Promise((resolve) => window.setTimeout(resolve, 12));
        }
    };

    const appendStatusMessage = (content, beforeNode = null) => {
        if (!(messagesNode instanceof HTMLElement)) {
            return null;
        }

        const wrapper = createElement('div', messageClass('status'), content);
        if (beforeNode instanceof HTMLElement && beforeNode.parentElement === messagesNode) {
            messagesNode.insertBefore(wrapper, beforeNode);
        } else {
            messagesNode.appendChild(wrapper);
        }
        if (stickToBottom) {
            lockToBottom();
        } else {
            setScrollButtonVisible(true);
        }
        return wrapper;
    };

    const setAssistantThinking = (node, label = uiText('Thinking...')) => {
        if (node instanceof HTMLElement) {
            node.innerHTML = thinkingHtml(label);
            lockToBottom();
        }
    };

    const pauseAssistantAutoScroll = () => {
        assistantAutoScrollPaused = true;
    };

    const resumeAssistantAutoScroll = () => {
        assistantAutoScrollPaused = false;
        if (messagesNode instanceof HTMLElement) {
            stickToBottom = isNearBottom(messagesNode);
            setScrollButtonVisible(!stickToBottom);
        }
    };

    const requestToolApproval = (node, toolCalls, tools = []) => new Promise((resolve) => {
        if (!(node instanceof HTMLElement)) {
            resolve(false);
            return;
        }

        node.innerHTML = '';
        const wrapper = createElement('div', 'grid gap-3');
        const header = createElement('div', 'grid gap-1');
        header.append(
            createElement('strong', 'text-sm font-semibold text-slate-900 dark:text-white', uiText('Tool request')),
            createElement('span', 'text-xs leading-5 text-slate-500 dark:text-slate-400', uiText('The model wants to use CMS tools. Please confirm execution.')),
        );
        wrapper.append(header);

        toolCalls.forEach((toolCall) => {
            const item = createElement('div', 'grid gap-2 rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-950/50');
            item.append(createElement('strong', 'text-sm font-semibold text-[var(--admin-page-accent)]', toolDisplayName(toolByName(tools, toolCall.name) || toolCall.name)));
            item.append(createElement('pre', 'm-0 max-h-32 overflow-auto whitespace-pre-wrap break-words rounded-md bg-slate-100 p-2 text-[11px] leading-5 text-slate-600 dark:bg-slate-900 dark:text-slate-300', toolArgumentsLabel(toolCall.arguments)));
            wrapper.append(item);
        });

        const actions = createElement('div', 'flex flex-wrap justify-end gap-2');
        const deny = createElement('button', 'inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-white', uiText('Do not run'));
        const approve = createElement('button', 'inline-flex min-h-9 items-center justify-center rounded-lg bg-[var(--admin-page-accent)] px-3 text-xs font-semibold text-white transition hover:brightness-95', uiText('Run'));
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
        let lastQuestion = '';
        messages.forEach((message) => {
            appendMessage(message.role, message.content, {question: lastQuestion});
            if (message.role === 'user') {
                lastQuestion = message.content;
            }
        });
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
        if (userSoundsDissatisfied(content)) {
            recordMistakeFromDissatisfaction(content);
        }
        stickToBottom = true;
        appendMessage('user', content);
        const assistantMessage = appendMessage('assistant', '');
        setAssistantThinking(assistantMessage);
        setBusy(true);
        setStatus(mode === 'stream' ? uiText('Streaming...') : uiText('Thinking...'));

        let responseText = '';
        registerGlobalAssistantActions();
        registerPageBuilderAssistantActions();
        registerThemeEditorAssistantActions();

        if (!isWarmupFresh()) {
            setStatus(uiText('Warming up...'));
            await warmUpModel({forceCheck: true}).catch(() => false);
        }

        const selectedModel = currentModel();
        const uiContext = collectAssistantContext();
        setStatus(uiText('Selecting tools...'));
        const selectedByServer = await selectTools(content, uiContext, 12).catch(() => []);
        const availableTools = await loadTools().catch(() => []);
        const searchTool = toolByName(availableTools, 'cms_tools_search');
        let activeTools = [...selectedByServer]
            .filter((tool, index, tools) => trimValue(tool?.name) !== ''
                && tools.findIndex((candidate) => trimValue(candidate?.name) === trimValue(tool?.name)) === index)
            .slice(0, 12);
        if (searchTool && !activeTools.some((tool) => trimValue(tool?.name) === 'cms_tools_search')) {
            activeTools = activeTools.length === 0
                ? [searchTool]
                : [...activeTools, searchTool].slice(0, 12);
        }
        const tools = ollamaTools(activeTools);
        setStatus(mode === 'stream' ? uiText('Streaming...') : uiText('Thinking...'));
        const basePayload = {
            model: selectedModel,
            messages: chatMessages(
                messages,
                currentSystemPrompt(),
                currentInstructions(),
                uiContext,
                currentToolConfirmationEnabled(),
                activeTools,
                uiLanguage()
            ),
            ...(tools.length > 0 ? {tools} : {}),
            keep_alive: localModelKeepAlive,
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
            let streamingRenderFrame = 0;
            let streamedVisibleResponse = false;

            const renderStreamingText = () => {
                streamingRenderFrame = 0;
                if (assistantMessage) {
                    streamedVisibleResponse = true;
                    renderMessageContent(assistantMessage, 'assistant', sanitizeVisibleToolIdentifiers(responseText, activeTools));
                }
            };

            const scheduleStreamingRender = () => {
                if (streamingRenderFrame === 0) {
                    streamedVisibleResponse = true;
                    streamingRenderFrame = window.requestAnimationFrame(renderStreamingText);
                }
            };

            for (let round = 0; round < 5; round += 1) {
                responseText = '';

                let response = null;
                try {
                    const shouldStreamRequest = mode === 'stream';
                    response = shouldStreamRequest
                        ? await requestOllama(currentBaseUrl(), '/api/chat', payload, (delta) => {
                            responseText += delta;
                            pauseAssistantAutoScroll();
                            scheduleStreamingRender();
                        })
                        : await requestOllama(currentBaseUrl(), '/api/chat', {
                            ...payload,
                            stream: false,
                        });
                } catch (error) {
                    if (toolResults.length > 0) {
                        recordMistake(
                            'Follow-up after successful tool call failed.',
                            `Error: ${error.message || error}`,
                            'Do not synthesize a fallback answer in code; strengthen the model input and retry the assistant path.'
                        );
                    }

                    throw error;
                }

                let toolCalls = normalizeToolCalls(response);
                if (toolCalls.length === 0) {
                    responseText = stripModelReasoning(responseText || (response?.message?.content ?? ''));
                    break;
                }

                const requiresApproval = currentToolConfirmationEnabled()
                    && !toolCalls.every((toolCall) => canAutoRunToolCall(toolCall, activeTools));
                setStatus(requiresApproval ? uiText('Tool confirmation required') : uiText('Running tools...'));
                const approved = requiresApproval
                    ? await requestToolApproval(assistantMessage, toolCalls, activeTools)
                    : true;
                if (!approved) {
                    const activity = toolActivityLine(toolCalls, activeTools, 'rejected');
                    if (activity !== '') {
                        appendStatusMessage(activity, assistantMessage);
                        messages.push({role: 'status', content: activity});
                        persistMessages();
                    }
                    responseText = uiText('Tool call rejected.');
                    if (assistantMessage) {
                        renderMessageContent(assistantMessage, 'assistant', responseText);
                        enableAssistantFeedback(assistantMessage, content, responseText);
                    }
                    messages.push({role: 'assistant', content: responseText});
                    persistMessages();
                    setStatus(selectedModel);
                    aborted = true;
                    return;
                }
                const activity = toolActivityLine(toolCalls, activeTools, requiresApproval ? 'approved' : 'run');
                if (activity !== '') {
                    appendStatusMessage(activity, assistantMessage);
                    messages.push({role: 'status', content: activity});
                    persistMessages();
                }

                for (const toolCall of toolCalls) {
                    const toolLabel = toolDisplayName(toolByName(activeTools, toolCall.name) || toolCall.name);
                    setStatus(uiText('Running :tool...', {tool: toolLabel}));
                    setAssistantThinking(assistantMessage, uiText('Using :tool...', {tool: toolLabel}));

                    let result = null;
                    try {
                        result = await callTool(toolCall, activeTools);
                    } catch (error) {
                        result = {
                            tool: toolCall.name,
                            result: {
                                success: false,
                                error: error.message || uiText('Tool call failed.'),
                            },
                        };
                        recordMistake(
                            'Tool call failed in Nixorious chat.',
                            `Tool: ${toolCall.name}; Error: ${error.message || error}`,
                            'Make tool failures visible and avoid implying that unavailable data was retrieved.'
                        );
                    }

                    let uiActionResults = [];
                    let uiActionError = '';
                    try {
                        uiActionResults = await executeUiActions(result);
                    } catch (error) {
                        uiActionError = `UI action failed: ${error.message || error}`;
                    }
                    toolResults.push(result);
                }

                payload = {
                    model: selectedModel,
                    messages: [
                        ...chatMessages(
                            messages.filter((message) => message.role === 'user'),
                            currentSystemPrompt(),
                            currentInstructions(),
                            uiContext,
                            currentToolConfirmationEnabled(),
                            [],
                            uiLanguage()
                        ),
                        {role: 'user', content: toolResultsGroundingMessage(toolResults, activeTools)},
                    ],
                    keep_alive: localModelKeepAlive,
                    think: false,
                    options: {
                        temperature: 0,
                        num_ctx: 8192,
                        num_predict: 900,
                    },
                };

                if (round === 4) {
                    throw new Error(uiText('The model did not finish the tool-call loop.'));
                }

                setStatus(mode === 'stream' ? uiText('Streaming...') : uiText('Thinking...'));
                setAssistantThinking(assistantMessage);
            }

            if (streamingRenderFrame !== 0) {
                window.cancelAnimationFrame(streamingRenderFrame);
                streamingRenderFrame = 0;
            }
            const visibleFinalText = sanitizeVisibleToolIdentifiers(responseText || uiText('Ollama returned an empty response.'), activeTools);
            if (assistantMessage) {
                const shouldProgressivelyRender = mode === 'stream' && toolResults.length > 0 && !streamedVisibleResponse;
                if (shouldProgressivelyRender) {
                    await streamMessageContent(assistantMessage, 'assistant', visibleFinalText, activeTools);
                } else {
                    renderMessageContent(assistantMessage, 'assistant', visibleFinalText);
                }
                enableAssistantFeedback(assistantMessage, content, visibleFinalText);
            }
            resumeAssistantAutoScroll();
            messages.push({role: 'assistant', content: visibleFinalText});
            persistMessages();
            setStatus(selectedModel);
        } catch (error) {
            const message = error.message || uiText('Could not generate a response.');
            recordMistake(
                'Assistant response generation failed.',
                `Error: ${message}`,
                'Check Ollama connectivity, prompt shape, tool routing, and frontend request handling.'
            );
            if (assistantMessage) {
                renderMessageContent(assistantMessage, 'assistant', message);
                enableAssistantFeedback(assistantMessage, content, message);
            }
            messages.push({role: 'assistant', content: message});
            persistMessages();
            setStatus(uiText('Connection failed'));
        } finally {
            resumeAssistantAutoScroll();
            setBusy(false);
        }
    };

    toggle?.addEventListener('click', () => setOpen(true));
    toggle?.addEventListener('pointerdown', requestWarmupSoon);
    toggle?.addEventListener('pointerenter', requestWarmupSoon);
    toggle?.addEventListener('focus', requestWarmupSoon);
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
    toolConfirmation?.addEventListener('click', () => {
        setToolConfirmation(!currentToolConfirmationEnabled());
    });

    input?.addEventListener('input', () => autosize(input));
    input?.addEventListener('focus', requestWarmupSoon);
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
    window.addEventListener('pageshow', () => {
        requestWarmupSoon({forceCheck: true});
        flushMistakeFeedbackQueue();
    });
    window.addEventListener('pagehide', flushMistakeFeedbackQueue);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            requestWarmupSoon({forceCheck: true});
            flushMistakeFeedbackQueue();
        }
    });
    window.addEventListener('online', flushMistakeFeedbackQueue);
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
    loadCmsTranslations().then(() => {
        if (messages.length === 0) {
            renderEmptyMessage();
        }
        syncConfiguredStatus();
    });
    restoreScrollPosition();
    if (input instanceof HTMLTextAreaElement) {
        try {
            input.value = localStorage.getItem(storageKeys.draft) || '';
        } catch (error) {}
        autosize(input);
    }
    setMode(mode);
    setToolConfirmation(currentToolConfirmationEnabled(), false);
    syncAgentVisibility();
    syncConfiguredStatus();
    setBusy(false);
    setOpen(storedText(storageKeys.open, 'closed') === 'open', {persist: false, focus: false});
    requestWarmupSoon({forceCheck: true});
    flushMistakeFeedbackQueue();
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
            warmedModelKey = '';
            warmedModelCheckedAt = 0;
            syncAgentVisibility();
            syncConfiguredStatus();
            setBusy(false);
            requestWarmupSoon({forceCheck: true});
        }
    });
    window.addEventListener('nixcms:local-ai-settings-changed', (event) => {
        warmedModelKey = '';
        warmedModelCheckedAt = 0;
        if (event?.detail?.key === storageKeys.settingsEnabled) {
            syncAgentVisibility();
            setBusy(false);
        }
        requestWarmupSoon({forceCheck: true});
    });
};
