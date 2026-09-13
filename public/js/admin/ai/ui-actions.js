import { trimValue } from '../ollama-client.js';
import { normalizeGeneratedCode } from './dom.js';
import { assistantRuntime } from './runtime.js';

export const registerThemeEditorAssistantActions = () => {
    const editor = window.NafCMS?.themeEditor;
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

export const executeUiActions = async (result) => {
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
