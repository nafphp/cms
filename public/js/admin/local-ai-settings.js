import { normalizeOllamaBaseUrl, requestOllama, stripModelReasoning, trimValue } from './ollama-client.js';
import Notify from '../lib/notifyJs/Notify.js';

const storagePrefix = 'nixcms:local-ai-settings:';
const storageKeys = {
    enabled: `${storagePrefix}enabled`,
    type: `${storagePrefix}type`,
    url: `${storagePrefix}url`,
    model: `${storagePrefix}model`,
    embeddingModel: `${storagePrefix}embedding-model`,
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

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
})[char]);

class LocalAiSettingsModal extends Notify {
    defaultOptions = {
        role: 'dialog',
        className: '',
        onClose: null,
        bindContent: null,
        overlay: {
            enabled: true,
            mode: 'blur',
            opacity: 0.54,
            blur: '5px',
            closeOnClick: true,
        },
    };

    bind() {
        this.container.querySelectorAll('[data-local-ai-modal-close]').forEach((button) => {
            button.addEventListener('click', () => this.remove());
        });

        if (this.options.bindContent instanceof Function) {
            this.options.bindContent(this.container, this);
        }
    }

    render() {
        const className = trimValue(this.options.className);
        const content = `
            <div class="notify prompt local-ai-settings-modal ${className}">
                ${this.message}
            </div>`;

        this.container = (new DOMParser()).parseFromString(content, 'text/html').body.firstElementChild;
        document.body.appendChild(this.container);
    }

    remove() {
        if (this.isClosing) {
            return;
        }

        if (this.options.onClose instanceof Function) {
            this.options.onClose(this);
        }

        this.animateRemove();
    }
}

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

const uiLanguage = () => trimValue(document.documentElement.lang || navigator.language || 'en').slice(0, 2).toLowerCase();

const labelsForLanguage = () => {
    const de = uiLanguage() === 'de';

    return de ? {
        reviewTitle: 'Fehler analysieren',
        reviewIntro: 'Ich schaue mir die offenen Punkte an und schlage kurze Memory-Einträge vor.',
        reviewButton: 'Fehler analysieren',
        close: 'Schließen',
        previous: 'Zurück',
        next: 'Weiter',
        acceptSolution: 'Das ist die Lösung',
        deleteMistake: 'Fehler löschen',
        researchMore: 'Weiter recherchieren',
        addInfo: 'Info ergänzen',
        solution: 'Mögliche Lösung',
        proposedMemory: 'Vorgeschlagener Memory-Satz',
        researchTitle: 'Weitere Infos ergänzen',
        researchIntro: 'Ergänze Kontext, Widerspruch oder was noch nicht passt. Das Modell arbeitet daraus einen neuen Lösungsvorschlag.',
        researchPlaceholder: 'Was fehlt noch? Was soll Nixorious beim nächsten Vorschlag beachten?',
        empty: 'Juhuu, keine Fehler mehr.',
        emptyMessages: [
            'Juhuu, keine Fehler mehr.',
            'Alles sauber. Nixorious darf kurz durchatmen.',
            'Keine offenen Punkte mehr. Sieht gut aus.',
            'Fehlerliste leer. Sehr angenehm.',
        ],
        loading: 'Analysiere Mistakes mit dem lokalen Modell...',
        researching: 'Arbeite Zusatzinfos in den Lösungsvorschlag ein...',
        saving: 'Speichere Memory-Eintrag...',
        saved: 'Gelöst: Memory aktualisiert und Mistake entfernt.',
        deleted: 'Fehler gelöscht.',
        deleting: 'Lösche Fehler...',
        failed: 'Analyse fehlgeschlagen.',
        chooseModel: 'Bitte zuerst ein lokales Chat-Modell auswählen.',
        editorTitle: 'Prompt-Dateien bearbeiten',
        editorIntro: 'Bearbeite die lokalen Markdown-Dateien direkt. MISTAKES.md bleibt Logmaterial und wird nicht in den Chat-Prompt geladen.',
        editorSave: 'Datei speichern',
        editorLoading: 'Lade Prompt-Dateien...',
        editorSaving: 'Speichere Prompt-Datei...',
        editorSaved: 'Prompt-Datei gespeichert.',
        editorFailed: 'Prompt-Dateien konnten nicht geladen werden.',
        promptActive: 'Aktiver Prompt',
        promptSupport: 'Arbeitsdatei',
    } : {
        reviewTitle: 'Analyze mistakes',
        reviewIntro: 'I will review the open points and suggest short memory entries.',
        reviewButton: 'Analyze',
        close: 'Close',
        previous: 'Previous',
        next: 'Next',
        acceptSolution: 'This is the solution',
        deleteMistake: 'Delete mistake',
        researchMore: 'Research further',
        addInfo: 'Add info',
        solution: 'Possible solution',
        proposedMemory: 'Suggested memory sentence',
        researchTitle: 'Add more information',
        researchIntro: 'Add context, disagreement, or what is still missing. The model will produce a better solution proposal.',
        researchPlaceholder: 'What is missing? What should Nixorious consider in the next proposal?',
        empty: 'Hooray, no mistakes left.',
        emptyMessages: [
            'Hooray, no mistakes left.',
            'All clear. Nixorious may breathe for a second.',
            'No open points left. Looking good.',
            'The mistake list is empty. Pleasant.',
        ],
        loading: 'Analyzing mistakes with the local model...',
        researching: 'Applying the additional information to the solution...',
        saving: 'Saving memory entry...',
        saved: 'Resolved: Memory updated and mistake removed.',
        deleted: 'Mistake deleted.',
        deleting: 'Deleting mistake...',
        failed: 'Analysis failed.',
        chooseModel: 'Choose a local chat model first.',
        editorTitle: 'Edit prompt files',
        editorIntro: 'Edit the local Markdown files directly. MISTAKES.md remains working log material and is not loaded into the chat prompt.',
        editorSave: 'Save file',
        editorLoading: 'Loading prompt files...',
        editorSaving: 'Saving prompt file...',
        editorSaved: 'Prompt file saved.',
        editorFailed: 'Could not load prompt files.',
        promptActive: 'Active prompt',
        promptSupport: 'Working file',
    };
};

const apiJson = async (url, options = {}) => {
    const client = typeof window.NixCMS?.json === 'function'
        ? window.NixCMS.json
        : window.fetch;
    const csrfHeader = String(window.NixCMS?.csrfHeader || 'X-CSRF-Token');
    const csrfToken = typeof window.NixCMS?.csrfToken === 'function'
        ? String(window.NixCMS.csrfToken() || '')
        : '';
    const headers = {
        'Accept': 'application/json',
        ...(options.body ? {'Content-Type': 'application/json'} : {}),
        ...(options.headers || {}),
    };
    if (csrfToken !== '' && !Object.hasOwn(headers, csrfHeader)) {
        headers[csrfHeader] = csrfToken;
    }

    const response = await client.call(window, url, {
        ...options,
        headers,
    });
    const raw = await response.text().catch(() => '');
    let payload = {};
    try {
        payload = raw !== '' ? JSON.parse(raw) : {};
    } catch (error) {}
    if (!response.ok || payload.success === false) {
        throw new Error(payload.reason || payload.message || raw || `Request failed with HTTP ${response.status}.`);
    }

    return payload;
};

const parseModelJson = (value) => {
    const cleaned = stripModelReasoning(value);
    const candidates = [
        cleaned,
        cleaned.match(/\{[\s\S]*\}/)?.[0] || '',
    ].filter(Boolean);

    for (const candidate of candidates) {
        try {
            return JSON.parse(candidate);
        } catch (error) {}
    }

    return {};
};

const normalizeReviewItems = (items) => (Array.isArray(items) ? items : [])
    .map((item) => ({
        title: trimValue(item?.title || ''),
        problem: trimValue(item?.problem || ''),
        question: trimValue(item?.question || ''),
        solution: trimValue(item?.solution || item?.proposed_solution || item?.proposedSolution || ''),
        proposedMemory: trimValue(item?.proposed_memory || item?.proposedMemory || ''),
        sourceIds: Array.isArray(item?.source_ids || item?.sourceIds)
            ? (item.source_ids || item.sourceIds).map((id) => trimValue(id)).filter(Boolean)
            : [],
        researchNotes: [],
    }))
    .filter((item) => item.title !== '' || item.problem !== '' || item.proposedMemory !== '' || item.solution !== '');

const randomItem = (items) => Array.isArray(items) && items.length > 0
    ? items[Math.floor(Math.random() * items.length)]
    : '';

const createMistakeReviewModal = (onClose = null) => {
    const labels = labelsForLanguage();
    const instance = new LocalAiSettingsModal(`
        <button class="close" type="button" data-ai-review-close data-local-ai-modal-close aria-label="${escapeHtml(labels.close)}"></button>
        <div class="local-ai-review-modal">
            <div class="local-ai-modal-head">
                <div>
                    <h3>${escapeHtml(labels.reviewTitle)}</h3>
                    <p>${escapeHtml(labels.reviewIntro)}</p>
                </div>
            </div>
            <div class="local-ai-review-status" data-ai-review-status></div>
            <div class="local-ai-review-empty hidden" data-ai-review-empty></div>
            <div class="local-ai-review-body" data-ai-review-body>
                <div class="local-ai-review-card">
                    <div class="local-ai-review-count" data-ai-review-count></div>
                    <h4 data-ai-review-title></h4>
                    <p data-ai-review-problem></p>
                    <p data-ai-review-question></p>
                </div>
                <label class="local-ai-review-field">
                    <span>${escapeHtml(labels.solution)}</span>
                    <textarea data-ai-review-solution></textarea>
                </label>
                <label class="local-ai-review-field">
                    <span>${escapeHtml(labels.proposedMemory)}</span>
                    <textarea data-ai-review-memory></textarea>
                </label>
            </div>
            <div class="local-ai-review-actions">
                <div class="local-ai-review-actions__left">
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-review-previous>${escapeHtml(labels.previous)}</button>
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-review-next>${escapeHtml(labels.next)}</button>
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-danger" data-ai-review-delete>${escapeHtml(labels.deleteMistake)}</button>
                </div>
                <div class="local-ai-review-actions__right">
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-accent-soft" data-ai-review-research>${escapeHtml(labels.researchMore)}</button>
                    <button type="button" class="local-ai-modal-button local-ai-modal-button-primary" data-ai-review-save>${escapeHtml(labels.acceptSolution)}</button>
                </div>
            </div>
        </div>
    `, {
        className: 'local-ai-review-notify',
        onClose,
    });
    const modal = instance.container;

    return {
        labels,
        instance,
        modal,
        body: modal.querySelector('[data-ai-review-body]'),
        empty: modal.querySelector('[data-ai-review-empty]'),
        status: modal.querySelector('[data-ai-review-status]'),
        count: modal.querySelector('[data-ai-review-count]'),
        title: modal.querySelector('[data-ai-review-title]'),
        problem: modal.querySelector('[data-ai-review-problem]'),
        question: modal.querySelector('[data-ai-review-question]'),
        solution: modal.querySelector('[data-ai-review-solution]'),
        memory: modal.querySelector('[data-ai-review-memory]'),
        close: modal.querySelector('[data-ai-review-close]'),
        previous: modal.querySelector('[data-ai-review-previous]'),
        next: modal.querySelector('[data-ai-review-next]'),
        delete: modal.querySelector('[data-ai-review-delete]'),
        research: modal.querySelector('[data-ai-review-research]'),
        save: modal.querySelector('[data-ai-review-save]'),
    };
};

const createPromptEditorModal = (onClose = null) => {
    const labels = labelsForLanguage();
    const instance = new LocalAiSettingsModal(`
        <button class="close" type="button" data-ai-prompt-close data-local-ai-modal-close aria-label="${escapeHtml(labels.close)}"></button>
        <div class="local-ai-prompt-modal">
            <div class="local-ai-modal-head">
                <div>
                    <h3>${escapeHtml(labels.editorTitle)}</h3>
                    <p>${escapeHtml(labels.editorIntro)}</p>
                </div>
            </div>
            <div class="local-ai-prompt-toolbar">
                <div class="local-ai-prompt-tabs" data-ai-prompt-tabs></div>
                <div class="local-ai-review-status" data-ai-prompt-status></div>
            </div>
            <div class="local-ai-prompt-editor">
                <div class="local-ai-prompt-meta">
                    <strong data-ai-prompt-name></strong>
                    <span data-ai-prompt-kind></span>
                </div>
                <textarea spellcheck="false" data-ai-prompt-editor></textarea>
            </div>
            <div class="local-ai-review-actions local-ai-review-actions--end">
                <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-prompt-close-secondary data-local-ai-modal-close>${escapeHtml(labels.close)}</button>
                <button type="button" class="local-ai-modal-button local-ai-modal-button-primary" data-ai-prompt-save>${escapeHtml(labels.editorSave)}</button>
            </div>
        </div>
    `, {
        className: 'local-ai-prompt-notify',
        onClose,
    });
    const modal = instance.container;

    return {
        labels,
        instance,
        modal,
        tabs: modal.querySelector('[data-ai-prompt-tabs]'),
        status: modal.querySelector('[data-ai-prompt-status]'),
        name: modal.querySelector('[data-ai-prompt-name]'),
        kind: modal.querySelector('[data-ai-prompt-kind]'),
        editor: modal.querySelector('[data-ai-prompt-editor]'),
        save: modal.querySelector('[data-ai-prompt-save]'),
        close: modal.querySelector('[data-ai-prompt-close]'),
        closeSecondary: modal.querySelector('[data-ai-prompt-close-secondary]'),
    };
};

const createResearchModal = (onClose = null) => {
    const labels = labelsForLanguage();
    const instance = new LocalAiSettingsModal(`
        <button class="close" type="button" data-ai-research-close data-local-ai-modal-close aria-label="${escapeHtml(labels.close)}"></button>
        <div class="local-ai-research-modal">
            <div class="local-ai-modal-head">
                <div>
                    <h3>${escapeHtml(labels.researchTitle)}</h3>
                    <p>${escapeHtml(labels.researchIntro)}</p>
                </div>
            </div>
            <textarea data-ai-research-input placeholder="${escapeHtml(labels.researchPlaceholder)}"></textarea>
            <div class="local-ai-review-actions local-ai-review-actions--end">
                <button type="button" class="local-ai-modal-button local-ai-modal-button-secondary" data-ai-research-close-secondary data-local-ai-modal-close>${escapeHtml(labels.close)}</button>
                <button type="button" class="local-ai-modal-button local-ai-modal-button-primary" data-ai-research-submit>${escapeHtml(labels.addInfo)}</button>
            </div>
        </div>
    `, {
        className: 'local-ai-research-notify',
        onClose,
    });
    const modal = instance.container;

    return {
        labels,
        instance,
        modal,
        input: modal.querySelector('[data-ai-research-input]'),
        close: modal.querySelector('[data-ai-research-close]'),
        closeSecondary: modal.querySelector('[data-ai-research-close-secondary]'),
        submit: modal.querySelector('[data-ai-research-submit]'),
    };
};

export function initLocalAiSettings(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const enabledInput = root.querySelector('[name="ai.enabled"]');
    const typeInput = root.querySelector('[name="ai.type"]');
    const urlInput = root.querySelector('[name="ai.url"]');
    const instructionsInput = root.querySelector('[name="ai.instructions"]');
    const modelSelect = root.querySelector('[data-ai-model-select]');
    const embeddingModelSelect = root.querySelector('[data-ai-embedding-model-select]');
    const loadModelsButton = root.querySelector('[data-ai-load-models]');
    const testButton = root.querySelector('[data-ai-test]');
    const reviewMistakesButton = root.querySelector('[data-ai-review-mistakes]');
    const editPromptsButton = root.querySelector('[data-ai-edit-prompts]');
    const statusNode = root.querySelector('[data-ai-status]');
    const connectionStatusNode = root.querySelector('[data-ai-connection-status]');
    const outputNode = root.querySelector('[data-ai-output]');
    const buttons = [loadModelsButton, testButton, reviewMistakesButton, editPromptsButton];

    const currentUrl = () => normalizeOllamaBaseUrl(urlInput instanceof HTMLInputElement ? urlInput.value : '');
    const currentModel = () => trimValue(modelSelect instanceof HTMLSelectElement ? modelSelect.value : '');
    const currentEmbeddingModel = () => trimValue(embeddingModelSelect instanceof HTMLSelectElement ? embeddingModelSelect.value : '');
    const languageName = () => uiLanguage() === 'de' ? 'German' : 'English';
    let mistakeCount = 0;

    const setReviewButtonState = (count = mistakeCount) => {
        mistakeCount = Math.max(0, Number.parseInt(String(count), 10) || 0);
        if (!(reviewMistakesButton instanceof HTMLButtonElement)) {
            return;
        }

        const labels = labelsForLanguage();
        reviewMistakesButton.textContent = `${labels.reviewButton} (${mistakeCount})`;
        reviewMistakesButton.disabled = mistakeCount === 0;
        reviewMistakesButton.className = mistakeCount > 0
            ? 'inline-flex min-h-10 items-center justify-center rounded-lg bg-accent px-3 text-sm font-semibold text-white shadow-sm shadow-accent/20 transition hover:brightness-105 disabled:opacity-50'
            : 'inline-flex min-h-10 items-center justify-center rounded-lg border border-accent/35 bg-accent/5 px-3 text-sm font-semibold text-accent/70 transition disabled:cursor-not-allowed disabled:opacity-55 dark:bg-accent/10';
    };

    const refreshMistakeCount = async () => {
        if (!(reviewMistakesButton instanceof HTMLButtonElement)) {
            return null;
        }

        try {
            const context = await apiJson('/api/admin/ai/mistakes/review');
            const entries = Array.isArray(context.mistakeEntries) ? context.mistakeEntries : [];
            setReviewButtonState(entries.length);

            return context;
        } catch (error) {
            setReviewButtonState(0);
            return null;
        }
    };

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
    restoreField(instructionsInput, storageKeys.instructions);
    persistField(enabledInput, storageKeys.enabled);
    persistField(typeInput, storageKeys.type);
    persistField(urlInput, storageKeys.url);
    persistField(instructionsInput, storageKeys.instructions);
    [enabledInput, typeInput, urlInput, instructionsInput].forEach((field) => {
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
        setReviewButtonState();
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

    let reviewModal = null;
    let researchModal = null;
    let reviewItems = [];
    let reviewIndex = 0;

    const ensureReviewModal = () => {
        if (reviewModal === null) {
            reviewModal = createMistakeReviewModal(() => {
                reviewModal = null;
            });
            reviewModal.previous?.addEventListener('click', () => {
                reviewIndex = Math.max(0, reviewIndex - 1);
                renderReviewItem();
            });
            reviewModal.next?.addEventListener('click', () => {
                reviewIndex = Math.min(reviewItems.length - 1, reviewIndex + 1);
                renderReviewItem();
            });
            reviewModal.delete?.addEventListener('click', deleteReviewMistake);
            reviewModal.research?.addEventListener('click', openResearchModal);
            reviewModal.save?.addEventListener('click', saveReviewMemory);
        }

        return reviewModal;
    };

    const ensureResearchModal = () => {
        if (researchModal === null) {
            researchModal = createResearchModal(() => {
                researchModal = null;
            });
            researchModal.submit?.addEventListener('click', submitResearchInfo);
        }

        return researchModal;
    };

    const openReviewModal = () => {
        ensureReviewModal();
    };

    const closeReviewModal = () => {
        if (reviewModal === null) {
            return;
        }

        reviewModal.instance?.remove();
        reviewModal = null;
    };

    function openResearchModal() {
        const modal = ensureResearchModal();
        if (modal.input instanceof HTMLTextAreaElement) {
            modal.input.value = '';
        }
        window.setTimeout(() => modal.input instanceof HTMLTextAreaElement && modal.input.focus(), 0);
    }

    function closeResearchModal() {
        if (researchModal === null) {
            return;
        }

        researchModal.instance?.remove();
        researchModal = null;
    }

    const setReviewBusy = (busy) => {
        if (reviewModal === null) {
            return;
        }

        [reviewModal.previous, reviewModal.next, reviewModal.delete, reviewModal.research, reviewModal.save].forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                if (busy) {
                    button.disabled = true;
                } else if (button === reviewModal.previous) {
                    button.disabled = reviewIndex === 0 || reviewItems.length === 0;
                } else if (button === reviewModal.next) {
                    button.disabled = reviewIndex >= reviewItems.length - 1 || reviewItems.length === 0;
                } else {
                    button.disabled = reviewItems.length === 0;
                }
                button.classList.toggle('cursor-wait', busy);
            }
        });
    };

    const renderReviewItem = () => {
        const modal = ensureReviewModal();
        const item = reviewItems[reviewIndex] || null;
        const hasItem = item !== null;

        modal.body?.classList.toggle('hidden', !hasItem);
        modal.empty?.classList.toggle('hidden', hasItem);
        if (!hasItem) {
            setStatus(modal.status, '');
            if (modal.empty instanceof HTMLElement) {
                modal.empty.textContent = randomItem(modal.labels.emptyMessages) || modal.labels.empty;
            }
            return;
        }

        setStatus(modal.status, '');
        if (modal.count) {
            modal.count.textContent = `${reviewIndex + 1} / ${reviewItems.length}`;
        }
        if (modal.title) {
            modal.title.textContent = item.title || 'Open point';
        }
        if (modal.problem) {
            modal.problem.textContent = item.problem || '';
        }
        if (modal.question) {
            modal.question.textContent = item.question || '';
        }
        if (modal.solution instanceof HTMLTextAreaElement) {
            modal.solution.value = item.solution || item.proposedMemory || '';
        }
        if (modal.memory instanceof HTMLTextAreaElement) {
            modal.memory.value = item.proposedMemory || '';
        }
        if (modal.previous instanceof HTMLButtonElement) {
            modal.previous.disabled = reviewIndex === 0;
        }
        if (modal.next instanceof HTMLButtonElement) {
            modal.next.disabled = reviewIndex >= reviewItems.length - 1;
        }
    };

    const analyzeMistakes = async (mistakes, memory, mistakeEntries = []) => {
        const model = currentModel();
        if (model === '') {
            throw new Error(ensureReviewModal().labels.chooseModel);
        }

        const response = await requestOllama(currentUrl(), '/api/chat', {
            model,
            keep_alive: '5m',
            think: false,
            format: 'json',
            options: {
                temperature: 0.1,
                num_predict: 1200,
            },
            messages: [
                {
                    role: 'system',
                    content: [
                        'You review Nixorious assistant mistake logs for durable product guidance.',
                        'Return JSON only: {"items":[{"title":"...","problem":"...","question":"...","solution":"...","proposed_memory":"...","source_ids":["..."]}]}.',
                        'Find at most 5 open points. Prefer concrete, stable behavior lessons over one-off bugs.',
                        'For each item, include all source_ids of mistake entries that would be resolved by the proposed memory.',
                        'Do not copy the full mistake log into the answer.',
                        `Write title, problem, question, and solution in ${languageName()}.`,
                        'solution explains the concrete fix or behavioral rule in 2-5 concise sentences.',
                        'Each proposed_memory must be one short English sentence suitable for MEMORY.md.',
                    ].join('\n'),
                },
                {
                    role: 'user',
                    content: [
                        'Current MEMORY.md:',
                        memory || '-',
                        '',
                        'Mistake entries with stable IDs:',
                        JSON.stringify(mistakeEntries, null, 2),
                        '',
                        'MISTAKES.md:',
                        mistakes || '-',
                    ].join('\n'),
                },
            ],
        });

        const content = response.message?.content || response.response || '';
        return normalizeReviewItems(parseModelJson(content).items);
    };

    const distillReviewMemory = async () => {
        const modal = ensureReviewModal();
        const item = reviewItems[reviewIndex] || {};
        const solution = trimValue(modal.solution instanceof HTMLTextAreaElement ? modal.solution.value : '');
        const proposed = trimValue(modal.memory instanceof HTMLTextAreaElement ? modal.memory.value : '');
        if (proposed === '' && solution === '') {
            return '';
        }

        const response = await requestOllama(currentUrl(), '/api/chat', {
            model: currentModel(),
            keep_alive: '5m',
            think: false,
            format: 'json',
            options: {
                temperature: 0,
                num_predict: 180,
            },
            messages: [
                {
                    role: 'system',
                    content: [
                        'Return JSON only: {"memory":"..."}',
                        'Distill this into exactly one short durable MEMORY.md sentence.',
                        'It must be actionable, stable across sessions, and no longer than 180 characters.',
                        'Always write the memory sentence in English, regardless of the UI language.',
                    ].join('\n'),
                },
                {
                    role: 'user',
                    content: JSON.stringify({
                        title: item.title || '',
                        problem: item.problem || '',
                        solution,
                        proposed_memory: proposed,
                        research_notes: item.researchNotes || [],
                    }),
                },
            ],
        });

        const parsed = parseModelJson(response.message?.content || response.response || '');
        return trimValue(parsed.memory || proposed);
    };

    async function submitResearchInfo() {
        const modal = ensureReviewModal();
        const infoModal = ensureResearchModal();
        const info = trimValue(infoModal.input instanceof HTMLTextAreaElement ? infoModal.input.value : '');
        if (info === '') {
            closeResearchModal();
            return;
        }

        try {
            closeResearchModal();
            setReviewBusy(true);
            setStatus(modal.status, modal.labels.researching);
            const item = reviewItems[reviewIndex] || {};
            const currentSolution = trimValue(modal.solution instanceof HTMLTextAreaElement ? modal.solution.value : item.solution || '');
            const currentMemory = trimValue(modal.memory instanceof HTMLTextAreaElement ? modal.memory.value : item.proposedMemory || '');
            const notes = [...(item.researchNotes || []), info];
            const response = await requestOllama(currentUrl(), '/api/chat', {
                model: currentModel(),
                keep_alive: '5m',
                think: false,
                format: 'json',
                options: {
                    temperature: 0.1,
                    num_predict: 900,
                },
                messages: [
                    {
                        role: 'system',
                        content: [
                            'Return JSON only: {"solution":"...","proposed_memory":"..."}',
                            'Improve the solution using the new user information.',
                            'The solution should be concrete and useful, 2-5 concise sentences.',
                            'The proposed_memory must be exactly one short durable MEMORY.md sentence, no longer than 180 characters.',
                            'Write the solution in the UI language, but always write proposed_memory in English.',
                        ].join('\n'),
                    },
                    {
                        role: 'user',
                        content: JSON.stringify({
                            title: item.title || '',
                            problem: item.problem || '',
                            previous_solution: currentSolution,
                            previous_memory: currentMemory,
                            research_notes: notes,
                        }),
                    },
                ],
            });
            const parsed = parseModelJson(response.message?.content || response.response || '');
            item.researchNotes = notes;
            item.solution = trimValue(parsed.solution || currentSolution);
            item.proposedMemory = trimValue(parsed.proposed_memory || parsed.proposedMemory || currentMemory);
            if (modal.solution instanceof HTMLTextAreaElement) {
                modal.solution.value = item.solution;
            }
            if (modal.memory instanceof HTMLTextAreaElement) {
                modal.memory.value = item.proposedMemory;
            }
            setStatus(modal.status, '');
        } catch (error) {
            setStatus(modal.status, error.message || modal.labels.failed);
        } finally {
            setReviewBusy(false);
        }
    }

    async function saveReviewMemory() {
        const modal = ensureReviewModal();
        try {
            setReviewBusy(true);
            setStatus(modal.status, modal.labels.saving);
            const sentence = await distillReviewMemory();
            if (sentence === '') {
                setStatus(modal.status, modal.labels.empty);
                return;
            }
            const sourceIds = Array.isArray(reviewItems[reviewIndex]?.sourceIds) ? reviewItems[reviewIndex].sourceIds : [];
            await apiJson('/api/admin/ai/memory', {
                method: 'POST',
                body: JSON.stringify({
                    memory: sentence,
                    resolvedMistakeIds: sourceIds,
                }),
            });
            reviewItems.splice(reviewIndex, 1);
            reviewIndex = Math.min(reviewIndex, Math.max(0, reviewItems.length - 1));
            setStatus(modal.status, modal.labels.saved);
            await refreshMistakeCount();
            if (reviewItems.length === 0) {
                renderReviewItem();
                return;
            }
            renderReviewItem();
        } catch (error) {
            setStatus(modal.status, error.message || modal.labels.failed);
        } finally {
            setReviewBusy(false);
        }
    }

    async function deleteReviewMistake() {
        const modal = ensureReviewModal();
        const sourceIds = Array.isArray(reviewItems[reviewIndex]?.sourceIds) ? reviewItems[reviewIndex].sourceIds : [];
        if (sourceIds.length === 0) {
            reviewItems.splice(reviewIndex, 1);
            reviewIndex = Math.min(reviewIndex, Math.max(0, reviewItems.length - 1));
            renderReviewItem();
            return;
        }

        try {
            setReviewBusy(true);
            setStatus(modal.status, modal.labels.deleting);
            await apiJson('/api/admin/ai/mistakes/delete', {
                method: 'POST',
                body: JSON.stringify({
                    mistakeIds: sourceIds,
                }),
            });
            reviewItems.splice(reviewIndex, 1);
            reviewIndex = Math.min(reviewIndex, Math.max(0, reviewItems.length - 1));
            setStatus(modal.status, modal.labels.deleted);
            await refreshMistakeCount();
            renderReviewItem();
        } catch (error) {
            setStatus(modal.status, error.message || modal.labels.failed);
        } finally {
            setReviewBusy(false);
        }
    }

    const reviewMistakes = async () => {
        const modal = ensureReviewModal();
        openReviewModal();
        setReviewBusy(true);
        setStatus(modal.status, modal.labels.loading);
        modal.body?.classList.add('hidden');

        try {
            if (typeInput instanceof HTMLSelectElement && typeInput.value !== 'local') {
                throw new Error('Switch AI type to Local Ollama first.');
            }

            const context = await refreshMistakeCount() || await apiJson('/api/admin/ai/mistakes/review');
            if (!context.hasMistakes) {
                reviewItems = [];
                renderReviewItem();
                return;
            }

            const mistakeEntries = Array.isArray(context.mistakeEntries) ? context.mistakeEntries : [];
            reviewItems = await analyzeMistakes(
                context.mistakes || '',
                context.memory || '',
                mistakeEntries,
            );
            reviewItems.forEach((item, index) => {
                if (item.sourceIds.length === 0 && mistakeEntries[index]?.id) {
                    item.sourceIds = [mistakeEntries[index].id];
                }
            });
            reviewIndex = 0;
            if (reviewItems.length === 0) {
                renderReviewItem();
                return;
            }
            renderReviewItem();
        } catch (error) {
            setStatus(modal.status, error.message || modal.labels.failed);
        } finally {
            setReviewBusy(false);
        }
    };

    let promptEditorModal = null;
    let promptFiles = [];
    let promptFileIndex = 0;

    const ensurePromptEditorModal = () => {
        if (promptEditorModal === null) {
            promptEditorModal = createPromptEditorModal(() => {
                promptEditorModal = null;
            });
            promptEditorModal.save?.addEventListener('click', savePromptFile);
        }

        return promptEditorModal;
    };

    const openPromptEditorModal = () => {
        ensurePromptEditorModal();
    };

    function closePromptEditorModal() {
        if (promptEditorModal === null) {
            return;
        }

        promptEditorModal.instance?.remove();
        promptEditorModal = null;
    }

    const setPromptEditorBusy = (busy) => {
        if (promptEditorModal === null) {
            return;
        }

        if (promptEditorModal.save instanceof HTMLButtonElement) {
            promptEditorModal.save.disabled = busy || promptFiles.length === 0;
            promptEditorModal.save.classList.toggle('cursor-wait', busy);
        }
    };

    const renderPromptTabs = () => {
        const modal = ensurePromptEditorModal();
        if (!(modal.tabs instanceof HTMLElement)) {
            return;
        }

        modal.tabs.innerHTML = '';
        promptFiles.forEach((file, index) => {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.className = index === promptFileIndex ? 'is-active' : '';
            tab.textContent = file.name;
            tab.addEventListener('click', () => {
                persistCurrentPromptDraft();
                promptFileIndex = index;
                renderPromptEditor();
            });
            modal.tabs.append(tab);
        });
    };

    const persistCurrentPromptDraft = () => {
        const modal = ensurePromptEditorModal();
        const current = promptFiles[promptFileIndex];
        if (current && modal.editor instanceof HTMLTextAreaElement) {
            current.content = modal.editor.value;
        }
    };

    const renderPromptEditor = () => {
        const modal = ensurePromptEditorModal();
        const file = promptFiles[promptFileIndex] || null;
        renderPromptTabs();

        if (!file) {
            if (modal.name) modal.name.textContent = '';
            if (modal.kind) modal.kind.textContent = '';
            if (modal.editor instanceof HTMLTextAreaElement) {
                modal.editor.value = '';
                modal.editor.disabled = true;
            }
            setPromptEditorBusy(false);
            return;
        }

        if (modal.name) {
            modal.name.textContent = file.name;
        }
        if (modal.kind) {
            modal.kind.textContent = file.inPrompt ? modal.labels.promptActive : modal.labels.promptSupport;
        }
        if (modal.editor instanceof HTMLTextAreaElement) {
            modal.editor.disabled = false;
            modal.editor.value = String(file.content || '');
        }
        setPromptEditorBusy(false);
    };

    async function savePromptFile() {
        const modal = ensurePromptEditorModal();
        const file = promptFiles[promptFileIndex] || null;
        if (!file || !(modal.editor instanceof HTMLTextAreaElement)) {
            return;
        }

        try {
            setPromptEditorBusy(true);
            setStatus(modal.status, modal.labels.editorSaving);
            const payload = await apiJson('/api/admin/ai/prompts', {
                method: 'POST',
                body: JSON.stringify({
                    file: file.name,
                    content: modal.editor.value,
                }),
            });
            file.content = payload.file?.content ?? modal.editor.value;
            setStatus(modal.status, modal.labels.editorSaved);
        } catch (error) {
            setStatus(modal.status, error.message || modal.labels.editorFailed);
        } finally {
            setPromptEditorBusy(false);
        }
    }

    const editPrompts = async () => {
        const modal = ensurePromptEditorModal();
        openPromptEditorModal();
        setPromptEditorBusy(true);
        setStatus(modal.status, modal.labels.editorLoading);

        try {
            const payload = await apiJson('/api/admin/ai/prompts');
            promptFiles = Array.isArray(payload.files) ? payload.files : [];
            promptFileIndex = 0;
            setStatus(modal.status, '');
            renderPromptEditor();
        } catch (error) {
            promptFiles = [];
            renderPromptEditor();
            setStatus(modal.status, error.message || modal.labels.editorFailed);
        } finally {
            setPromptEditorBusy(false);
        }
    };

    if (loadModelsButton instanceof HTMLButtonElement) {
        loadModelsButton.addEventListener('click', loadModels);
    }

    if (testButton instanceof HTMLButtonElement) {
        testButton.addEventListener('click', testLiveResponse);
    }

    if (reviewMistakesButton instanceof HTMLButtonElement) {
        reviewMistakesButton.addEventListener('click', reviewMistakes);
        window.addEventListener('nixcms:ai-mistake-recorded', refreshMistakeCount);
    }

    if (editPromptsButton instanceof HTMLButtonElement) {
        editPromptsButton.addEventListener('click', editPrompts);
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
    setReviewButtonState(0);
    refreshMistakeCount();
    if (!(typeInput instanceof HTMLSelectElement) || typeInput.value === 'local') {
        window.setTimeout(loadModels, 50);
    }
}
