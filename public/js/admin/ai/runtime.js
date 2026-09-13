import { trimValue } from '../ollama-client.js';

export const assistantRuntime = () => {
    window.NafCMS = window.NafCMS || {};
    if (window.NafCMS.assistantActions) {
        return window.NafCMS.assistantActions;
    }

    const actions = new Map();
    const contextProviders = new Map();

    window.NafCMS.assistantActions = {
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

    return window.NafCMS.assistantActions;
};

export const registerGlobalAssistantActions = () => {
    const runtime = assistantRuntime();
    if (window.NafCMS?.__globalAssistantActionsRegistered) {
        return;
    }

    window.NafCMS.__globalAssistantActionsRegistered = true;
    runtime.register('admin.navigate', {
        description: 'Navigates within the NafCMS admin UI',
        parameters: {
            url: 'string',
        },
        handler: ({url}) => {
            const target = trimValue(url);
            if (!/^\/admin(?:\/|$|\?)/.test(target)) {
                throw new Error('Assistant navigation is limited to NafCMS admin URLs.');
            }

            window.location.assign(target);
            return {success: true, url: target};
        },
    });
};

/**
 * Editors that have no dedicated assistant module still need to report their
 * area, otherwise the server-side hard filter treats the turn as "no editor
 * context" and drops every write tool.
 */
export const registerEditorAssistantContexts = () => {
    const runtime = assistantRuntime();
    if (window.NafCMS?.__editorAssistantContextsRegistered) {
        return;
    }

    window.NafCMS.__editorAssistantContextsRegistered = true;

    const articleForm = document.querySelector('[data-article-editor]');
    if (articleForm) {
        runtime.registerContext('articleEditor', () => ({
            area: 'articleEditor',
            contentType: 'article',
            articleId: trimValue(articleForm.dataset.articleId) || '',
            language: trimValue(articleForm.dataset.articleLanguage) || '',
        }));
    }

    const formBuilder = document.querySelector('[data-form-builder]');
    if (formBuilder) {
        runtime.registerContext('formBuilder', () => ({
            area: 'formBuilder',
            contentType: 'form',
        }));
    }
};

export const collectAssistantContext = () => {
    const runtimeContext = assistantRuntime().context();
    const values = Object.values(runtimeContext);
    // Prefer whichever provider actually reports an editor area; falling back to
    // "the first registered one" silently mislabelled the turn when several
    // providers were present.
    const activeContext = values.find((context) => trimValue(context?.area) !== '') || values[0] || {};
    const now = new Date();

    return {
        ...activeContext,
        url: window.location.pathname,
        today: now.toISOString().slice(0, 10),
        currentDateTime: now.toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        contexts: runtimeContext,
    };
};
