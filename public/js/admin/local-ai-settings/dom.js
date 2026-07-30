// Small DOM helpers shared by the panel and its modals.

import { trimValue } from '../ollama-client.js';

export const setStatus = (statusNode, message) => {
    if (statusNode) {
        statusNode.textContent = message;
    }
};

export const normalizeEnabled = (value) => ['1', 'true', 'yes', 'on', 'enabled'].includes(trimValue(value).toLowerCase())
    ? '1'
    : '0';

export const setOutput = (outputNode, message) => {
    if (outputNode) {
        outputNode.textContent = message || '-';
    }
};

export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
})[char]);

export const connectionClasses = {
    checking: 'inline-flex min-h-6 items-center rounded-full border border-amber-300/70 bg-amber-100 px-2.5 text-xs font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/12 dark:text-amber-200',
    online: 'inline-flex min-h-6 items-center rounded-full border border-emerald-300/70 bg-emerald-100 px-2.5 text-xs font-semibold text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/12 dark:text-emerald-200',
    offline: 'inline-flex min-h-6 items-center rounded-full border border-rose-300/70 bg-rose-100 px-2.5 text-xs font-semibold text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/12 dark:text-rose-200',
    unknown: 'inline-flex min-h-6 items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400',
};

export const setConnectionStatus = (statusNode, state, label) => {
    if (!(statusNode instanceof HTMLElement)) {
        return;
    }

    statusNode.className = connectionClasses[state] || connectionClasses.unknown;
    statusNode.textContent = label;
};

    const setBusy = (buttons, busy) => {
        buttons.forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.disabled = busy;
                button.classList.toggle('opacity-60', busy);
                button.classList.toggle('cursor-wait', busy);
            }
        });
    };

