import { trimValue } from '../ollama-client.js';

export const assistantRuntime = () => {
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

export const registerGlobalAssistantActions = () => {
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

export const collectAssistantContext = () => {
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
