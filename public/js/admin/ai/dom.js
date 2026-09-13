import { trimValue } from '../ollama-client.js';

export const createElement = (tag, className = '', text = '') => {
    const element = document.createElement(tag);
    if (className !== '') {
        element.className = className;
    }
    if (text !== '') {
        element.textContent = text;
    }
    return element;
};


export const scrollToEnd = (node, behavior = 'smooth') => {
    if (node) {
        node.scrollTo({top: node.scrollHeight, behavior});
    }
};

export const isNearBottom = (node) => {
    if (!(node instanceof HTMLElement)) {
        return true;
    }

    return node.scrollHeight - node.scrollTop - node.clientHeight < 36;
};

export const normalizeEnabled = (value) => ['1', 'true', 'yes', 'on', 'enabled'].includes(trimValue(value).toLowerCase())
    ? '1'
    : '0';

export const normalizeGeneratedCode = (value) => String(value || '').replace(/\r\n?/g, '\n');

export const autosize = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 144)}px`;
};

export const storedJson = (key, fallback) => {
    try {
        const parsed = JSON.parse(localStorage.getItem(key) || 'null');
        return parsed ?? fallback;
    } catch (error) {
        return fallback;
    }
};

export const persistJson = (key, value) => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {}
};

export const normalizeStoredMessages = (value) => Array.isArray(value)
    ? value
        .filter((message) => (
            message
            && ['user', 'assistant', 'status'].includes(String(message.role || ''))
            && trimValue(message.content || '') !== ''
        ))
        .map((message) => ({
            role: String(message.role),
            content: String(message.content).slice(0, 12000),
        }))
        .slice(-40)
    : [];

export const storedText = (key, fallback = '') => {
    try {
        return trimValue(localStorage.getItem(key) || fallback);
    } catch (error) {
        return trimValue(fallback);
    }
};

export const apiJson = async (url, options = {}) => {
    const csrfHeader = String(window.NafCMS?.csrfHeader || 'X-CSRF-Token');
    const csrfToken = typeof window.NafCMS?.csrfToken === 'function'
        ? String(window.NafCMS.csrfToken() || '')
        : '';
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {}),
    };
    if (csrfToken !== '' && !Object.hasOwn(headers, csrfHeader)) {
        headers[csrfHeader] = csrfToken;
    }

    if (typeof window.NafCMS?.json !== 'function') {
        throw new Error('NafCMS admin JSON client is not available yet.');
    }

    const response = await window.NafCMS.json(url, {
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
