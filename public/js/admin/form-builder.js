import toast from '../lib/notifyJs/Modules/Toast.js';
import prompt from '../lib/notifyJs/Modules/Prompt.js';
import {initStyledSelects} from '../components/styledSelect.js';

const inputClass = 'min-h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition focus:border-[var(--admin-page-accent)] dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const buttonClass = 'inline-flex min-h-8 items-center rounded-md border border-slate-300 px-2.5 text-xs font-semibold text-slate-600 transition hover:border-[var(--admin-page-accent)] dark:border-slate-700 dark:text-slate-300';
const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
const slug = (value) => String(value || '').trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^[-._]+|[-._]+$/g, '');
const fieldTypes = ['text', 'email', 'textarea', 'select', 'checkbox'];
const actionMethods = ['POST', 'PUT', 'PATCH'];
const validatorRules = [
    {value: 'required', label: 'required'},
    {value: 'email', label: 'email'},
    {value: 'min', label: 'min'},
    {value: 'max', label: 'max'},
];
const suggestBox = document.createElement('div');
suggestBox.className = 'fixed z-50 hidden max-h-72 overflow-auto rounded-md border border-slate-300 bg-white p-1 shadow-xl dark:border-slate-700 dark:bg-slate-950';
document.body.appendChild(suggestBox);
let activeSuggestInput = null;

document.querySelectorAll('[data-form-builder]').forEach((root) => {
    const data = JSON.parse(root.dataset.form || '{}');
    const translationSuggestions = normalizeTranslationSuggestions(JSON.parse(root.dataset.translationSuggestions || '[]'));
    const translationLanguage = String(root.dataset.translationLanguage || 'en');
    const translationEndpoint = String(root.dataset.translationEndpoint || '/api/admin/translations/{language}');
    const fieldsRoot = root.querySelector('[data-fields]');
    const actionsRoot = root.querySelector('[data-actions]');
    const state = root.querySelector('[data-save-state]');
    const overviewUrl = root.dataset.overviewUrl || '/admin/forms';
    let identifierTouched = Boolean(data.identifier && data.identifier !== 'contact');

    root.elements.title.value = data.settings?.titleKey || data.title || '';
    root.elements.identifier.value = data.identifier || '';
    root.elements.status.value = data.status || 'draft';
    root.elements.submitLabel.value = data.settings?.submitLabelKey || data.settings?.submitLabel || '';
    root.elements.successMessage.value = data.settings?.successMessageKey || data.settings?.successMessage || '';
    initStyledSelects(root.querySelectorAll('select[data-styled-select]'));
    root.elements.status.dispatchEvent(new Event('change'));

    const fields = Array.isArray(data.fields)
        ? data.fields.map((field) => ({
            ...field,
            validators: normalizeValidators(field),
            nameTouched: Boolean(field?.name),
        }))
        : [];
    const actions = normalizeActions(data.settings?.actions);
    const payload = () => ({
        id: Number(data.id || 0),
        title: root.elements.title.value,
        identifier: root.elements.identifier.value,
        status: root.elements.status.value,
        fields,
        settings: {
            titleKey: translationKeyOrEmpty(root.elements.title.value),
            submitLabel: root.elements.submitLabel.value,
            submitLabelKey: translationKeyOrEmpty(root.elements.submitLabel.value),
            successMessage: root.elements.successMessage.value,
            successMessageKey: translationKeyOrEmpty(root.elements.successMessage.value),
            actions,
        },
    });
    const initial = JSON.stringify(payload());

    const blankField = () => ({
        name: '',
        label: '',
        type: 'text',
        required: false,
        placeholder: '',
        labelKey: '',
        placeholderKey: '',
        options: [],
        validators: [],
        nameTouched: false,
    });
    const blankValidator = () => ({rule: 'required', param: ''});
    const blankWebhook = () => ({type: 'webhook', enabled: true, url: '', method: 'POST', failOnError: false});

    const render = () => {
        fieldsRoot.innerHTML = fields.map((field, index) => `
            <article class="rounded-lg border border-slate-300/70 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/55" style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:nowrap;" data-field-index="${index}">
                <div class="grid content-start gap-3" style="flex:1 1 0;min-width:0;">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="m-0 text-sm font-semibold text-slate-950 dark:text-white">Field</h3>
                        <button class="${buttonClass}" type="button" data-remove-field="${index}">Remove</button>
                    </div>
                    <label class="grid gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Label</span>
                        <span class="flex min-w-0 overflow-hidden rounded-md border border-slate-300 bg-white transition focus-within:border-[var(--admin-page-accent)] dark:border-slate-700 dark:bg-slate-900">
                            <input class="min-h-10 min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-slate-950 outline-none dark:text-white focus-visible:ring-2 focus-visible:ring-[var(--admin-page-accent)]" data-field-prop="label" value="${escapeHtml(field.labelKey || field.label || '')}" placeholder="${escapeHtml(defaultFieldKey(field, 'label'))}" data-translation-field data-translation-context="field.label" data-translation-fallback="Email address">
                            <button class="inline-flex min-h-10 w-14 shrink-0 items-center justify-center border-l border-slate-300 bg-slate-50/70 px-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-300" type="button" data-open-field-translation="${index}:label" title="Translation key" aria-label="Translation key">
                                ${translationIcon()}
                            </button>
                        </span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Key</span>
                        <input class="${inputClass} font-mono" data-field-prop="name" value="${escapeHtml(field.name || '')}" placeholder="email-address">
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Type</span>
                        <select class="${inputClass}" data-styled-select data-field-prop="type">
                            ${fieldTypes.map((type) => `<option value="${type}" ${field.type === type ? 'selected' : ''}>${type}</option>`).join('')}
                        </select>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Placeholder</span>
                        <span class="flex min-w-0 overflow-hidden rounded-md border border-slate-300 bg-white transition focus-within:border-[var(--admin-page-accent)] dark:border-slate-700 dark:bg-slate-900">
                            <input class="min-h-10 min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-slate-950 outline-none dark:text-white focus-visible:ring-2 focus-visible:ring-[var(--admin-page-accent)]" data-field-prop="placeholder" value="${escapeHtml(field.placeholderKey || field.placeholder || '')}" placeholder="${escapeHtml(defaultFieldKey(field, 'placeholder'))}" data-translation-field data-translation-context="field.placeholder" data-translation-fallback="jane@example.com">
                            <button class="inline-flex min-h-10 w-14 shrink-0 items-center justify-center border-l border-slate-300 bg-slate-50/70 px-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-300" type="button" data-open-field-translation="${index}:placeholder" title="Translation key" aria-label="Translation key">
                                ${translationIcon()}
                            </button>
                        </span>
                    </label>
                    ${field.type === 'select' ? `
                        <label class="grid gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Select options</span>
                            <textarea class="${inputClass} min-h-28 resize-y" data-field-prop="options" placeholder="Support&#10;Sales&#10;Billing">${escapeHtml((field.options || []).join('\n'))}</textarea>
                            <span class="text-xs text-slate-500">One option per line. Submissions must match one of these values.</span>
                        </label>
                    ` : ''}
                </div>
                <div class="grid content-start gap-3 rounded-md border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50" style="flex:0 0 520px;max-width:520px;min-width:340px;">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="m-0 text-sm font-semibold text-slate-950 dark:text-white">Validators</h3>
                            <p class="m-0 mt-1 text-xs text-slate-500">Applied as a NAF validator chain.</p>
                        </div>
                        <button class="${buttonClass}" type="button" data-add-validator="${index}">Add validator</button>
                    </div>
                    ${(field.validators || []).length === 0 ? '<div class="text-sm text-slate-500">No validators.</div>' : ''}
                    ${(field.validators || []).map((validator, validatorIndex) => `
                        <div class="grid gap-2 rounded-md border border-slate-200 bg-white/80 p-2 dark:border-slate-800 dark:bg-slate-900/70" data-validator-index="${validatorIndex}">
                            <div class="grid gap-2 md:grid-cols-[minmax(150px,1fr)_minmax(120px,.8fr)]">
                                <label class="grid gap-1.5">
                                    <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Rule</span>
                                    <select class="${inputClass}" data-styled-select data-validator-prop="rule">
                                        ${validatorRules.map((rule) => `<option value="${rule.value}" ${validator.rule === rule.value ? 'selected' : ''}>${escapeHtml(rule.label)}</option>`).join('')}
                                    </select>
                                </label>
                                <label class="grid gap-1.5">
                                    <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Parameter</span>
                                    <input class="${inputClass}" data-validator-prop="param" value="${escapeHtml(validator.param || '')}" placeholder="${validator.rule === 'min' || validator.rule === 'max' ? 'Length' : 'None'}" ${validator.rule === 'required' || validator.rule === 'email' ? 'disabled' : ''}>
                                </label>
                            </div>
                            <button class="${buttonClass}" type="button" data-remove-validator="${validatorIndex}">Remove validator</button>
                        </div>
                    `).join('')}
                </div>
            </article>
        `).join('');
        initStyledSelects(fieldsRoot.querySelectorAll('select[data-styled-select]'));
        renderActions();
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    };

    const renderActions = () => {
        if (!actionsRoot) {
            return;
        }

        const store = actions.find((action) => action.type === 'store') || {type: 'store', enabled: true};
        const webhooks = actions.filter((action) => action.type === 'webhook');
        actionsRoot.innerHTML = `
            <article class="rounded-lg border border-slate-300/70 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/55" data-action-type="store">
                <label class="flex items-start gap-3">
                    <input class="mt-1 h-4 w-4 accent-[var(--admin-page-accent)]" type="checkbox" data-store-action-enabled ${store.enabled !== false ? 'checked' : ''}>
                    <span class="grid gap-1">
                        <span class="text-sm font-semibold text-slate-950 dark:text-white">Store submission</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Save validated values in NafCMS form submissions.</span>
                    </span>
                </label>
            </article>
            ${webhooks.map((action, index) => `
                <article class="grid gap-3 rounded-lg border border-slate-300/70 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/55" data-webhook-index="${index}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="m-0 text-sm font-semibold text-slate-950 dark:text-white">Webhook</h3>
                            <p class="m-0 mt-1 text-xs text-slate-500">Send the validated submission as JSON to an external endpoint.</p>
                        </div>
                        <button class="${buttonClass}" type="button" data-remove-webhook-action="${index}">Remove webhook</button>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-[1fr_160px]">
                        <label class="grid gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Endpoint URL</span>
                            <input class="${inputClass}" data-webhook-prop="url" value="${escapeHtml(action.url || '')}" placeholder="https://api.example.com/leads">
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Method</span>
                            <select class="${inputClass}" data-styled-select data-webhook-prop="method">
                                ${actionMethods.map((method) => `<option value="${method}" ${action.method === method ? 'selected' : ''}>${method}</option>`).join('')}
                            </select>
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input class="h-4 w-4 accent-[var(--admin-page-accent)]" type="checkbox" data-webhook-prop="failOnError" ${action.failOnError === true ? 'checked' : ''}>
                        Fail the form submission if this webhook fails
                    </label>
                </article>
            `).join('')}
        `;
        initStyledSelects(actionsRoot.querySelectorAll('select[data-styled-select]'));
    };

    root.elements.title.addEventListener('input', () => {
        if (!identifierTouched && !translationKeyOrEmpty(root.elements.title.value)) {
            root.elements.identifier.value = slug(root.elements.title.value);
        }
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    });
    root.elements.identifier.addEventListener('input', () => {
        identifierTouched = true;
    });
    const handleFieldInput = (event) => {
        const target = event.target.closest('[data-field-prop], [data-validator-prop]');
        if (!target) {
            state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
            return;
        }

        const card = target.closest('[data-field-index]');
        const field = fields[Number(card?.dataset.fieldIndex || -1)];
        if (!field) return;

        const prop = target.dataset.fieldProp;
        const validatorRow = target.closest('[data-validator-index]');
        if (validatorRow) {
            const validator = field.validators?.[Number(validatorRow.dataset.validatorIndex || -1)];
            if (!validator) return;
            const validatorProp = target.dataset.validatorProp;
            if (validatorProp === 'rule') {
                validator.rule = target.value;
                if (validator.rule === 'required' || validator.rule === 'email') {
                    validator.param = '';
                }
                render();
                return;
            }
            if (validatorProp === 'param') {
                validator.param = target.value;
            }
            state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
            return;
        }

        if (prop === 'label') {
            field.label = target.value;
            field.labelKey = translationKeyOrEmpty(target.value);
            if (!field.nameTouched) {
                field.name = slug(target.value);
                card.querySelector('[data-field-prop="name"]').value = field.name;
            }
        } else if (prop === 'name') {
            field.nameTouched = true;
            field.name = slug(target.value);
            target.value = field.name;
        } else if (prop === 'placeholder') {
            field.placeholder = target.value;
            field.placeholderKey = translationKeyOrEmpty(target.value);
        } else if (prop === 'options') {
            field.options = target.value.split('\n').map((item) => item.trim()).filter(Boolean);
        } else {
            field[prop] = target.value;
        }
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    };
    root.addEventListener('input', handleFieldInput);
    root.addEventListener('change', handleFieldInput);
    const handleActionInput = (event) => {
        const storeToggle = event.target.closest('[data-store-action-enabled]');
        if (storeToggle) {
            const store = ensureStoreAction(actions);
            store.enabled = storeToggle.checked;
            state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
            return;
        }

        const target = event.target.closest('[data-webhook-prop]');
        if (!target) {
            return;
        }

        const row = target.closest('[data-webhook-index]');
        const webhook = actions.filter((action) => action.type === 'webhook')[Number(row?.dataset.webhookIndex || -1)];
        if (!webhook) {
            return;
        }

        const prop = target.dataset.webhookProp;
        if (prop === 'failOnError') {
            webhook.failOnError = target.checked;
        } else if (prop === 'method') {
            webhook.method = actionMethods.includes(target.value) ? target.value : 'POST';
        } else {
            webhook[prop] = target.value;
        }
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    };
    root.addEventListener('input', handleActionInput);
    root.addEventListener('change', handleActionInput);
    root.addEventListener('focusin', (event) => {
        const input = event.target.closest('[data-translation-key-suggest]');
        if (input) {
            showTranslationSuggestions(input, root, translationSuggestions);
        }
    });
    root.addEventListener('input', (event) => {
        const input = event.target.closest('[data-translation-key-suggest]');
        if (input) {
            showTranslationSuggestions(input, root, translationSuggestions);
        }
    });
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-add-field]')) {
            fields.push(blankField());
            render();
        }

        const addValidator = event.target.closest('[data-add-validator]');
        if (addValidator) {
            const field = fields[Number(addValidator.dataset.addValidator || -1)];
            if (field) {
                field.validators = Array.isArray(field.validators) ? field.validators : [];
                field.validators.push(blankValidator());
                render();
            }
        }

        const removeValidator = event.target.closest('[data-remove-validator]');
        if (removeValidator) {
            const card = removeValidator.closest('[data-field-index]');
            const field = fields[Number(card?.dataset.fieldIndex || -1)];
            if (field && Array.isArray(field.validators)) {
                field.validators.splice(Number(removeValidator.dataset.removeValidator), 1);
                render();
            }
        }

        const remove = event.target.closest('[data-remove-field]');
        if (remove) {
            fields.splice(Number(remove.dataset.removeField), 1);
            render();
        }

        if (event.target.closest('[data-add-webhook-action]')) {
            actions.push(blankWebhook());
            render();
        }

        const translationButton = event.target.closest('[data-open-translation-field], [data-open-field-translation]');
        if (translationButton) {
            openTranslationModal(translationButton);
            return;
        }

        const removeWebhook = event.target.closest('[data-remove-webhook-action]');
        if (removeWebhook) {
            const webhook = actions.filter((action) => action.type === 'webhook')[Number(removeWebhook.dataset.removeWebhookAction || -1)];
            const index = actions.indexOf(webhook);
            if (index >= 0) {
                actions.splice(index, 1);
                render();
            }
        }
    });

    async function openTranslationModal(button) {
        const target = translationTarget(button);
        if (!target?.input) {
            return;
        }

        const current = String(target.input.value || '');
        const key = translationKeyOrEmpty(current) || target.defaultKey;
        const existing = findTranslationSuggestion(translationSuggestions, key);
        const value = existing?.value || (!translationKeyOrEmpty(current) ? current : target.fallback);
        const promptResult = prompt(`
            <div>Translation key</div>
            <div class="form-translation-prompt translation-key-prompt notify-form">
                <p class="notify-form-intro">Search or create a translation key for this form text.</p>
                <div class="translation-key-grid">
                    <label class="notify-field">
                        <span class="notify-field-label">Key</span>
                        <input class="font-mono" type="text" value="${escapeHtml(key)}" placeholder="${escapeHtml(target.defaultKey)}" autocomplete="off" data-form-translation-key>
                    </label>
                    <label class="notify-field">
                        <span class="notify-field-label">Value</span>
                        <input type="text" value="${escapeHtml(value)}" placeholder="${escapeHtml(target.fallback)}" autocomplete="off" data-form-translation-value>
                    </label>
                </div>
                <p class="notify-form-intro">The form stores only the translation key for this text.</p>
            </div>
        `, {
            confirmButtonText: 'Apply',
            cancelButtonText: 'Cancel',
            showCancelButton: true,
            style: {width: 'min(980px, calc(100vw - 32px))', maxWidth: 'min(980px, calc(100vw - 32px))'},
            confirmCallback: async (promptInstance) => {
                const keyInput = promptInstance.container.querySelector('[data-form-translation-key]');
                const valueInput = promptInstance.container.querySelector('[data-form-translation-value]');
                const selectedKey = translationKey(String(keyInput?.value || ''));
                const selectedValue = String(valueInput?.value || '');
                if (selectedKey === '') {
                    toast('Please enter a translation key.');
                    keyInput?.focus();
                    return true;
                }

                if (selectedValue.trim() !== '' || !findTranslationSuggestion(translationSuggestions, selectedKey)) {
                    const saved = await saveTranslation(selectedKey, selectedValue);
                    if (!saved) {
                        return true;
                    }
                }

                applyTranslationTarget(target, selectedKey);
                promptInstance.promise.resolve(true);
                promptInstance.remove();
                return true;
            },
        });

        window.setTimeout(() => bindTranslationPrompt(document.querySelector('.form-translation-prompt')), 0);
        await promptResult;
        hideTranslationSuggestions();
    }

    function translationTarget(button) {
        const fieldTarget = button.dataset.openFieldTranslation;
        if (fieldTarget) {
            const [index, prop] = fieldTarget.split(':');
            const field = fields[Number(index)];
            const card = button.closest('[data-field-index]');
            const input = card?.querySelector(`[data-field-prop="${CSS.escape(prop)}"]`);
            if (!field || !(input instanceof HTMLInputElement)) {
                return null;
            }

            return {
                input,
                field,
                prop,
                defaultKey: defaultFieldKey(field, prop),
                fallback: String(field[prop] || input.dataset.translationFallback || ''),
            };
        }

        const name = button.dataset.openTranslationField;
        const input = name ? root.elements[name] : null;
        if (!(input instanceof HTMLInputElement)) {
            return null;
        }

        const suffix = name === 'submitLabel'
            ? 'submit'
            : (name === 'successMessage' ? 'success' : 'title');

        return {
            input,
            name,
            defaultKey: `forms.${slug(root.elements.identifier.value || 'contact') || 'contact'}.${suffix}`,
            fallback: String(input.dataset.translationFallback || ''),
        };
    }

    function applyTranslationTarget(target, key) {
        target.input.value = key;
        if (target.field && target.prop) {
            target.field[target.prop] = key;
            target.field[`${target.prop}Key`] = key;
        }
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    }

    function bindTranslationPrompt(modal) {
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        const keyInput = modal.querySelector('[data-form-translation-key]');
        const valueInput = modal.querySelector('[data-form-translation-value]');
        keyInput?.focus({preventScroll: true});
        keyInput?.addEventListener('focus', () => showTranslationSuggestions(keyInput, root, translationSuggestions));
        keyInput?.addEventListener('input', () => {
            const existing = findTranslationSuggestion(translationSuggestions, keyInput.value);
            if (existing && valueInput instanceof HTMLInputElement && valueInput.value.trim() === '') {
                valueInput.value = existing.value;
            }
            showTranslationSuggestions(keyInput, root, translationSuggestions);
        });
        valueInput?.addEventListener('focus', () => showTranslationSuggestions(valueInput, root, translationSuggestions));
        valueInput?.addEventListener('input', () => showTranslationSuggestions(valueInput, root, translationSuggestions));
    }

    async function saveTranslation(key, value) {
        const response = await (window.NafCMS?.json || fetch)(translationEndpoint.replace('{language}', encodeURIComponent(translationLanguage)), {
            method: 'POST',
            body: JSON.stringify({entries: {[key]: value}, mode: 'merge'}),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            toast(result.reason || 'Could not save translations.');
            return false;
        }

        setTranslationSuggestion(translationSuggestions, key, value);
        toast('Translation saved.');
        return true;
    }

    root.addEventListener('submit', async (event) => {
        event.preventDefault();
        root.querySelectorAll('[data-error-for]').forEach((node) => {
            node.textContent = '';
            node.classList.add('hidden');
        });

        const response = await (window.NafCMS?.json || fetch)(root.dataset.saveUrl, {
            method: 'POST',
            body: JSON.stringify({
                ...payload(),
                fields: fields.map(({nameTouched, ...field}) => field),
            }),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.success === false) {
            Object.entries(result.errors || {}).forEach(([key, messages]) => {
                const target = root.querySelector(`[data-error-for="${CSS.escape(key)}"]`);
                if (target) {
                    target.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages || '');
                    target.classList.remove('hidden');
                }
            });
            toast(result.reason || 'Could not save form.');
            return;
        }

        try {
            window.sessionStorage.setItem('nafcms:flash-toast', 'Form saved.');
        } catch (error) {}
        window.location.assign(overviewUrl);
    });

    render();
});

document.addEventListener('click', (event) => {
    const option = event.target.closest('[data-translation-suggestion-key]');
    if (option && activeSuggestInput) {
        const key = option.dataset.translationSuggestionKey || '';
        const promptContainer = activeSuggestInput.closest('.notify');
        const keyInput = promptContainer?.querySelector('[data-form-translation-key]');
        const valueInput = promptContainer?.querySelector('[data-form-translation-value]');
        const suggestionValue = option.dataset.translationSuggestionValue || '';

        if (keyInput instanceof HTMLInputElement) {
            keyInput.value = key;
            keyInput.dispatchEvent(new Event('input', {bubbles: true}));
        } else {
            activeSuggestInput.value = key;
            activeSuggestInput.dispatchEvent(new Event('input', {bubbles: true}));
        }
        if (valueInput instanceof HTMLInputElement) {
            valueInput.value = suggestionValue;
            valueInput.dispatchEvent(new Event('input', {bubbles: true}));
        }
        hideTranslationSuggestions();
        return;
    }

    if (!event.target.closest('[data-translation-key-suggest], [data-form-translation-key], [data-form-translation-value]') && !event.target.closest('[data-translation-suggest-box]')) {
        hideTranslationSuggestions();
    }
});

window.addEventListener('resize', hideTranslationSuggestions);
window.addEventListener('scroll', hideTranslationSuggestions, true);

function normalizeValidators(field) {
    if (Array.isArray(field?.validators) && field.validators.length > 0) {
        return field.validators.map((validator) => ({
            rule: String(validator?.rule || 'required'),
            param: String(validator?.param || ''),
        }));
    }

    const validators = [];
    if (field?.required) {
        validators.push({rule: 'required', param: ''});
    }
    if (field?.type === 'email' || field?.validation === 'email') {
        validators.push({rule: 'email', param: ''});
    }
    if (Number(field?.minLength || 0) > 0) {
        validators.push({rule: 'min', param: String(Number(field.minLength))});
    }
    if (Number(field?.maxLength || 0) > 0) {
        validators.push({rule: 'max', param: String(Number(field.maxLength))});
    }

    return validators;
}

function normalizeActions(actions) {
    const normalized = Array.isArray(actions)
        ? actions
            .filter((action) => action && typeof action === 'object')
            .map((action) => ({
                type: String(action.type || ''),
                enabled: action.enabled !== false,
                url: String(action.url || ''),
                method: actionMethods.includes(String(action.method || '').toUpperCase()) ? String(action.method).toUpperCase() : 'POST',
                failOnError: action.failOnError === true,
            }))
            .filter((action) => action.type === 'store' || action.type === 'webhook')
        : [];

    if (!normalized.some((action) => action.type === 'store')) {
        normalized.unshift({type: 'store', enabled: true});
    }

    return normalized;
}

function ensureStoreAction(actions) {
    let store = actions.find((action) => action.type === 'store');
    if (!store) {
        store = {type: 'store', enabled: true};
        actions.unshift(store);
    }

    return store;
}

function translationKey(value) {
    return String(value || '').trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '.').replace(/[.]{2,}/g, '.').replace(/^[._-]+|[._-]+$/g, '');
}

function translationKeyOrEmpty(value) {
    const text = String(value || '').trim();
    const key = translationKey(text);
    return key === text && key.includes('.') ? key : '';
}

function findTranslationSuggestion(suggestions, key) {
    const normalizedKey = String(key || '').trim();
    return suggestions.find((suggestion) => suggestion.key === normalizedKey) || null;
}

function setTranslationSuggestion(suggestions, key, value) {
    const existing = suggestions.find((suggestion) => suggestion.key === key);
    if (existing) {
        existing.value = value;
        return;
    }

    suggestions.push({key, value});
    suggestions.sort((a, b) => a.key.localeCompare(b.key));
}

function defaultFieldKey(field, property) {
    const base = field?.name || slug(field?.label || 'field');
    return base ? `forms.${base}.${property}` : `forms.field.${property}`;
}

function normalizeTranslationSuggestions(suggestions) {
    if (!Array.isArray(suggestions)) {
        return [];
    }

    return suggestions
        .map((suggestion) => ({
            key: String(suggestion?.key || ''),
            value: String(suggestion?.value || ''),
        }))
        .filter((suggestion) => suggestion.key !== '');
}

function showTranslationSuggestions(input, root, suggestions) {
    const matches = matchingTranslationSuggestions(input, root, suggestions);
    if (matches.length === 0) {
        hideTranslationSuggestions();
        return;
    }

    activeSuggestInput = input;
    const rect = input.getBoundingClientRect();
    suggestBox.dataset.translationSuggestBox = 'true';
    suggestBox.style.left = `${rect.left}px`;
    suggestBox.style.top = `${rect.bottom + 6}px`;
    suggestBox.style.width = `${Math.max(rect.width, 320)}px`;
    suggestBox.innerHTML = matches.map((suggestion) => `
        <button type="button" class="grid w-full gap-0.5 rounded px-2.5 py-2 text-left transition hover:bg-slate-100 dark:hover:bg-slate-900" data-translation-suggestion-key="${escapeHtml(suggestion.key)}" data-translation-suggestion-value="${escapeHtml(suggestion.value)}">
            <span class="font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">${highlight(suggestion.key, input.value)}</span>
            <span class="truncate text-xs text-slate-500 dark:text-slate-400">${escapeHtml(suggestion.value)}</span>
        </button>
    `).join('');
    suggestBox.classList.remove('hidden');
}

function hideTranslationSuggestions() {
    activeSuggestInput = null;
    suggestBox.classList.add('hidden');
    suggestBox.innerHTML = '';
}

function matchingTranslationSuggestions(input, root, suggestions) {
    const query = searchQueryForInput(input, root);
    if (query === '') {
        return [];
    }

    const parts = query.split(/\s+/).filter(Boolean);
    return suggestions
        .map((suggestion) => ({
            ...suggestion,
            score: translationSuggestionScore(suggestion, parts),
        }))
        .filter((suggestion) => suggestion.score > 0)
        .sort((a, b) => b.score - a.score || a.key.localeCompare(b.key))
        .slice(0, 8);
}

function searchQueryForInput(input, root) {
    const direct = String(input.value || '').trim();
    if (direct !== '') {
        return direct.toLowerCase();
    }

    const sourceName = input.dataset.translationSourceName;
    if (sourceName && root.elements[sourceName]) {
        return String(root.elements[sourceName].value || '').trim().toLowerCase();
    }

    const sourceProp = input.dataset.translationSourceProp;
    const card = input.closest('[data-field-index]');
    if (sourceProp && card) {
        const source = card.querySelector(`[data-field-prop="${CSS.escape(sourceProp)}"]`);
        return String(source?.value || '').trim().toLowerCase();
    }

    return '';
}

function translationSuggestionScore(suggestion, parts) {
    const key = suggestion.key.toLowerCase();
    const value = suggestion.value.toLowerCase();
    let score = 0;

    for (const part of parts) {
        if (key === part) {
            score += 120;
        } else if (key.startsWith(part)) {
            score += 80;
        } else if (key.includes(part)) {
            score += 45;
        }

        if (value === part) {
            score += 100;
        } else if (value.startsWith(part)) {
            score += 70;
        } else if (value.includes(part)) {
            score += 55;
        }
    }

    if (key.startsWith('forms.')) {
        score += 12;
    }

    return score;
}

function highlight(value, query) {
    const safe = escapeHtml(value);
    const term = String(query || '').trim().split(/\s+/).find(Boolean);
    if (!term) {
        return safe;
    }

    const index = value.toLowerCase().indexOf(term.toLowerCase());
    if (index < 0) {
        return safe;
    }

    return `${escapeHtml(value.slice(0, index))}<mark class="rounded bg-yellow-200 px-0.5 text-slate-950">${escapeHtml(value.slice(index, index + term.length))}</mark>${escapeHtml(value.slice(index + term.length))}`;
}
