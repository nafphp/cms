import { normalizeOllamaBaseUrl, requestOllama, trimValue } from './ollama-client.js';

const storagePrefix = 'nixcms:local-ai-settings:';
const storageKeys = {
    enabled: `${storagePrefix}enabled`,
    type: `${storagePrefix}type`,
    url: `${storagePrefix}url`,
    model: `${storagePrefix}model`,
    embeddingModel: `${storagePrefix}embedding-model`,
    apiKey: `${storagePrefix}api-key`,
    instructions: `${storagePrefix}instructions`,
};

const setStatus = (statusNode, message) => {
    if (statusNode) {
        statusNode.textContent = message;
    }
};

const normalizeEnabled = (value) => ['1', 'true', 'yes', 'on', 'enabled'].includes(trimValue(value).toLowerCase())
    ? '1'
    : '0';

const setOutput = (outputNode, message) => {
    if (outputNode) {
        outputNode.textContent = message || '-';
    }
};

const connectionClasses = {
    checking: 'inline-flex min-h-6 items-center rounded-full border border-amber-300/70 bg-amber-100 px-2.5 text-xs font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/12 dark:text-amber-200',
    online: 'inline-flex min-h-6 items-center rounded-full border border-emerald-300/70 bg-emerald-100 px-2.5 text-xs font-semibold text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/12 dark:text-emerald-200',
    offline: 'inline-flex min-h-6 items-center rounded-full border border-rose-300/70 bg-rose-100 px-2.5 text-xs font-semibold text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/12 dark:text-rose-200',
    unknown: 'inline-flex min-h-6 items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400',
};

const setConnectionStatus = (statusNode, state, label) => {
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

export function initLocalAiSettings(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const enabledInput = root.querySelector('[name="ai.enabled"]');
    const typeInput = root.querySelector('[name="ai.type"]');
    const urlInput = root.querySelector('[name="ai.url"]');
    const apiKeyInput = root.querySelector('[name="ai.api_key"]');
    const instructionsInput = root.querySelector('[name="ai.instructions"]');
    const modelSelect = root.querySelector('[data-ai-model-select]');
    const embeddingModelSelect = root.querySelector('[data-ai-embedding-model-select]');
    const loadModelsButton = root.querySelector('[data-ai-load-models]');
    const testButton = root.querySelector('[data-ai-test]');
    const statusNode = root.querySelector('[data-ai-status]');
    const connectionStatusNode = root.querySelector('[data-ai-connection-status]');
    const outputNode = root.querySelector('[data-ai-output]');
    const buttons = [loadModelsButton, testButton];

    const currentUrl = () => normalizeOllamaBaseUrl(urlInput instanceof HTMLInputElement ? urlInput.value : '');
    const currentModel = () => trimValue(modelSelect instanceof HTMLSelectElement ? modelSelect.value : '');
    const currentEmbeddingModel = () => trimValue(embeddingModelSelect instanceof HTMLSelectElement ? embeddingModelSelect.value : '');

    const restoreField = (field, key) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }

        try {
            const stored = localStorage.getItem(key);
            if (stored !== null && stored !== field.value) {
                field.closest('form')?.setAttribute('data-local-storage-dirty', '1');
                field.value = stored;
            }
        } catch (error) {}
    };

    const persistField = (field, key) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }

        field.addEventListener('input', () => {
            try {
                localStorage.setItem(key, field.value);
            } catch (error) {}
        });
        field.addEventListener('change', () => {
            try {
                localStorage.setItem(key, field.value);
            } catch (error) {}
        });
    };

    restoreField(enabledInput, storageKeys.enabled);
    restoreField(typeInput, storageKeys.type);
    restoreField(urlInput, storageKeys.url);
    restoreField(apiKeyInput, storageKeys.apiKey);
    restoreField(instructionsInput, storageKeys.instructions);
    persistField(enabledInput, storageKeys.enabled);
    persistField(typeInput, storageKeys.type);
    persistField(urlInput, storageKeys.url);
    persistField(apiKeyInput, storageKeys.apiKey);
    persistField(instructionsInput, storageKeys.instructions);
    [enabledInput, typeInput, urlInput, apiKeyInput, instructionsInput].forEach((field) => {
        if (field instanceof HTMLElement) {
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });

    const broadcastEnabledState = () => {
        if (!(enabledInput instanceof HTMLSelectElement)) {
            return;
        }

        try {
            localStorage.setItem(storageKeys.enabled, normalizeEnabled(enabledInput.value));
        } catch (error) {}

        window.dispatchEvent(new CustomEvent('nixcms:local-ai-settings-changed', {
            detail: {
                key: storageKeys.enabled,
                value: normalizeEnabled(enabledInput.value),
            },
        }));
    };

    const syncAvailability = () => {
        const isLocal = !(typeInput instanceof HTMLSelectElement) || typeInput.value === 'local';
        buttons.forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.title = isLocal ? '' : 'Remote providers are not implemented yet.';
                button.classList.toggle('opacity-60', !isLocal);
            }
        });
        if (modelSelect instanceof HTMLSelectElement) {
            modelSelect.disabled = !isLocal;
        }
        if (embeddingModelSelect instanceof HTMLSelectElement) {
            embeddingModelSelect.disabled = !isLocal;
        }
    };

    const isEmbeddingModelByName = (model) => {
        const name = trimValue(model?.name || '').toLowerCase();
        const details = model?.details && typeof model.details === 'object' ? model.details : {};
        const families = [
            trimValue(details.family || ''),
            ...(Array.isArray(details.families) ? details.families.map((family) => trimValue(family)) : []),
        ].join(' ').toLowerCase();
        const text = `${name} ${families}`;

        return ['embed', 'embedding', 'nomic-embed', 'all-minilm', 'bge-', 'mxbai-embed', 'snowflake-arctic-embed', 'e5-']
            .some((needle) => text.includes(needle));
    };

    const inspectModels = async (models) => {
        const embeddingModels = new Set();
        const toolModels = new Set();
        let inspected = 0;
        let unknown = 0;

        await Promise.all(models.map(async (model) => {
            const name = trimValue(model?.name || '');
            if (name === '') {
                return;
            }

            try {
                const details = await requestOllama(currentUrl(), '/api/show', {model: name});
                inspected += 1;
                const capabilities = Array.isArray(details.capabilities)
                    ? details.capabilities.map((capability) => trimValue(capability).toLowerCase())
                    : [];

                if (capabilities.length === 0) {
                    unknown += 1;
                }

                if (capabilities.includes('embedding')) {
                    embeddingModels.add(name);
                }

                if (capabilities.includes('tools')) {
                    toolModels.add(name);
                }
            } catch (error) {
                if (isEmbeddingModelByName(model)) {
                    embeddingModels.add(name);
                }
            }
        }));

        return {embeddingModels, toolModels, inspected, unknown};
    };

    const replaceOptions = (select, names, preferred, emptyLabel = '') => {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        select.innerHTML = '';
        if (emptyLabel !== '') {
            select.append(new Option(emptyLabel, ''));
        }
        if (preferred !== '' && !names.includes(preferred)) {
            select.append(new Option(`${preferred} (configured)`, preferred));
        }
        names.forEach((name) => select.append(new Option(name, name)));
        if (preferred !== '') {
            select.value = preferred;
        }
    };

    const loadModels = async () => {
        if (!(modelSelect instanceof HTMLSelectElement)) {
            return;
        }

        setBusy(buttons, true);
        setConnectionStatus(connectionStatusNode, 'checking', 'Checking');
        setStatus(statusNode, 'Loading models...');
        setOutput(outputNode, '-');

        try {
            if (typeInput instanceof HTMLSelectElement && typeInput.value !== 'local') {
                setStatus(statusNode, 'Switch AI type to Local Ollama first.');
                setOutput(outputNode, 'Remote providers are not implemented yet.');
                return;
            }

            const payload = await requestOllama(currentUrl(), '/api/tags');
            const models = Array.isArray(payload.models) ? payload.models : [];
            const inspectedModels = await inspectModels(models);
            const toolNames = models
                .filter((model) => inspectedModels.toolModels.has(trimValue(model?.name || '')))
                .map((model) => trimValue(model?.name ?? ''))
                .filter((name) => name !== '')
                .sort((left, right) => left.localeCompare(right));
            const embeddingNames = models
                .filter((model) => inspectedModels.embeddingModels.has(trimValue(model?.name || '')) || isEmbeddingModelByName(model))
                .map((model) => trimValue(model?.name ?? ''))
                .filter((name) => name !== '')
                .sort((left, right) => left.localeCompare(right));
            const configured = trimValue(modelSelect.dataset.configuredModel || '');
            const configuredEmbedding = trimValue(embeddingModelSelect instanceof HTMLSelectElement ? embeddingModelSelect.dataset.configuredModel || '' : '');
            const storedModel = (() => {
                try {
                    return trimValue(localStorage.getItem(storageKeys.model) || '');
                } catch (error) {
                    return '';
                }
            })();
            const storedEmbeddingModel = (() => {
                try {
                    return trimValue(localStorage.getItem(storageKeys.embeddingModel) || '');
                } catch (error) {
                    return '';
                }
            })();
            const preferred = storedModel || currentModel() || configured;
            const preferredEmbedding = storedEmbeddingModel || currentEmbeddingModel() || configuredEmbedding;

            replaceOptions(modelSelect, toolNames, toolNames.includes(preferred) ? preferred : '');
            replaceOptions(embeddingModelSelect, embeddingNames, preferredEmbedding, 'Keyword routing only');

            if (modelSelect.options.length === 0) {
                modelSelect.append(new Option('No tool-capable models found', ''));
                setStatus(statusNode, 'No tool-capable models found.');
                setOutput(outputNode, 'Install or choose an Ollama chat model whose /api/show capabilities include "tools".');
                return;
            }

            if (preferred !== '' && toolNames.includes(preferred)) {
                modelSelect.value = preferred;
            } else if (toolNames.length > 0) {
                modelSelect.value = toolNames[0];
                modelSelect.dispatchEvent(new Event('change', {bubbles: true}));
            }
            if (embeddingModelSelect instanceof HTMLSelectElement && preferredEmbedding !== '') {
                embeddingModelSelect.value = preferredEmbedding;
            }

            setConnectionStatus(connectionStatusNode, 'online', 'Online');
            setStatus(statusNode, `${toolNames.length} tool-capable model${toolNames.length === 1 ? '' : 's'} available, ${embeddingNames.length} embedding candidate${embeddingNames.length === 1 ? '' : 's'}.`);
        } catch (error) {
            setConnectionStatus(connectionStatusNode, 'offline', 'Offline');
            setStatus(statusNode, 'Model loading failed.');
            setOutput(outputNode, error.message || 'Could not load Ollama models.');
        } finally {
            setBusy(buttons, false);
            syncAvailability();
        }
    };

    const testLiveResponse = async () => {
        const model = currentModel();
        if (typeInput instanceof HTMLSelectElement && typeInput.value !== 'local') {
            setStatus(statusNode, 'Switch AI type to Local Ollama first.');
            setOutput(outputNode, 'Remote providers are not implemented yet.');
            return;
        }

        if (model === '') {
            setStatus(statusNode, 'Choose a model first.');
            setOutput(outputNode, 'Load models from Ollama or keep the configured model option.');
            return;
        }

        setBusy(buttons, true);
        setConnectionStatus(connectionStatusNode, 'checking', 'Checking');
        setStatus(statusNode, 'Streaming...');
        setOutput(outputNode, '');

        let streamed = '';
        const extraInstructions = trimValue(instructionsInput instanceof HTMLTextAreaElement ? instructionsInput.value : '');
        const prompt = [
            'You are the local NixCMS assistant.',
            'Reply in one concise sentence that confirms local AI is ready.',
            extraInstructions !== '' ? `Additional project instructions:\n${extraInstructions}` : '',
        ].filter(Boolean).join('\n\n');

        try {
            await requestOllama(currentUrl(), '/api/generate', {
                model,
                prompt,
                keep_alive: '0',
                think: false,
                options: {
                    temperature: 0,
                    num_predict: 80,
                    stop: ['\n\n'],
                },
            }, (delta) => {
                streamed += delta;
                setOutput(outputNode, streamed);
            });

            setConnectionStatus(connectionStatusNode, 'online', 'Online');
            setStatus(statusNode, streamed.trim() === '' ? 'Empty response.' : 'Live response OK.');
            if (streamed.trim() === '') {
                setOutput(outputNode, 'Ollama answered, but did not return text.');
            }
        } catch (error) {
            setConnectionStatus(connectionStatusNode, 'offline', 'Offline');
            setStatus(statusNode, 'Live test failed.');
            setOutput(outputNode, error.message || 'Could not generate with Ollama.');
        } finally {
            setBusy(buttons, false);
            syncAvailability();
        }
    };

    if (loadModelsButton instanceof HTMLButtonElement) {
        loadModelsButton.addEventListener('click', loadModels);
    }

    if (testButton instanceof HTMLButtonElement) {
        testButton.addEventListener('click', testLiveResponse);
    }

    if (typeInput instanceof HTMLSelectElement) {
        typeInput.addEventListener('change', syncAvailability);
    }

    if (enabledInput instanceof HTMLSelectElement) {
        enabledInput.addEventListener('change', broadcastEnabledState);
        broadcastEnabledState();
    }

    if (modelSelect instanceof HTMLSelectElement) {
        modelSelect.addEventListener('change', () => {
            try {
                localStorage.setItem(storageKeys.model, modelSelect.value);
            } catch (error) {}
        });
    }

    if (embeddingModelSelect instanceof HTMLSelectElement) {
        embeddingModelSelect.addEventListener('change', () => {
            try {
                localStorage.setItem(storageKeys.embeddingModel, embeddingModelSelect.value);
            } catch (error) {}
        });
    }

    syncAvailability();
    if (!(typeInput instanceof HTMLSelectElement) || typeInput.value === 'local') {
        window.setTimeout(loadModels, 50);
    }
}
