import toast from '../lib/notifyJs/Modules/Toast.js';
import {initStyledSelects} from '../components/styledSelect.js';

const inputClass = 'min-h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition focus:border-[var(--admin-page-accent)] dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const buttonClass = 'inline-flex min-h-8 items-center rounded-md border border-slate-300 px-2.5 text-xs font-semibold text-slate-600 transition hover:border-[var(--admin-page-accent)] dark:border-slate-700 dark:text-slate-300';
const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
const slug = (value) => String(value || '').trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^[-._]+|[-._]+$/g, '');
const fieldTypes = ['text', 'email', 'textarea', 'select', 'checkbox'];
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
    const fieldsRoot = root.querySelector('[data-fields]');
    const state = root.querySelector('[data-save-state]');
    const overviewUrl = root.dataset.overviewUrl || '/admin/forms';
    let identifierTouched = Boolean(data.identifier && data.identifier !== 'contact');

    root.elements.title.value = data.title || '';
    root.elements.titleKey.value = data.settings?.titleKey || '';
    root.elements.identifier.value = data.identifier || '';
    root.elements.status.value = data.status || 'draft';
    root.elements.submitLabel.value = data.settings?.submitLabel || '';
    root.elements.submitLabelKey.value = data.settings?.submitLabelKey || '';
    root.elements.successMessage.value = data.settings?.successMessage || '';
    root.elements.successMessageKey.value = data.settings?.successMessageKey || '';
    initStyledSelects(root.querySelectorAll('select[data-styled-select]'));
    root.elements.status.dispatchEvent(new Event('change'));

    const fields = Array.isArray(data.fields)
        ? data.fields.map((field) => ({
            ...field,
            validators: normalizeValidators(field),
            nameTouched: Boolean(field?.name),
        }))
        : [];
    const payload = () => ({
        id: Number(data.id || 0),
        title: root.elements.title.value,
        identifier: root.elements.identifier.value,
        status: root.elements.status.value,
        fields,
        settings: {
            titleKey: root.elements.titleKey.value,
            submitLabel: root.elements.submitLabel.value,
            submitLabelKey: root.elements.submitLabelKey.value,
            successMessage: root.elements.successMessage.value,
            successMessageKey: root.elements.successMessageKey.value,
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
                        <input class="${inputClass}" data-field-prop="label" value="${escapeHtml(field.label || '')}" placeholder="Email address">
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Label key</span>
                        <input class="${inputClass} font-mono" data-field-prop="labelKey" value="${escapeHtml(field.labelKey || '')}" placeholder="${escapeHtml(defaultFieldKey(field, 'label'))}" data-translation-key-suggest data-translation-context="field.label" data-translation-source-prop="label">
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
                        <input class="${inputClass}" data-field-prop="placeholder" value="${escapeHtml(field.placeholder || '')}" placeholder="jane@example.com">
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Placeholder key</span>
                        <input class="${inputClass} font-mono" data-field-prop="placeholderKey" value="${escapeHtml(field.placeholderKey || '')}" placeholder="${escapeHtml(defaultFieldKey(field, 'placeholder'))}" data-translation-key-suggest data-translation-context="field.placeholder" data-translation-source-prop="placeholder">
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
                            <p class="m-0 mt-1 text-xs text-slate-500">Applied as a NixPHP validator chain.</p>
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
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    };

    root.elements.title.addEventListener('input', () => {
        if (!identifierTouched) {
            root.elements.identifier.value = slug(root.elements.title.value);
        }
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    });
    root.elements.identifier.addEventListener('input', () => {
        identifierTouched = true;
    });
    root.elements.titleKey.addEventListener('input', (event) => {
        event.target.value = translationKey(event.target.value);
    });
    root.elements.submitLabelKey.addEventListener('input', (event) => {
        event.target.value = translationKey(event.target.value);
    });
    root.elements.successMessageKey.addEventListener('input', (event) => {
        event.target.value = translationKey(event.target.value);
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
            if (!field.nameTouched) {
                field.name = slug(target.value);
                card.querySelector('[data-field-prop="name"]').value = field.name;
            }
        } else if (prop === 'name') {
            field.nameTouched = true;
            field.name = slug(target.value);
            target.value = field.name;
        } else if (prop === 'labelKey' || prop === 'placeholderKey') {
            field[prop] = translationKey(target.value);
            target.value = field[prop];
        } else if (prop === 'options') {
            field.options = target.value.split('\n').map((item) => item.trim()).filter(Boolean);
        } else {
            field[prop] = target.value;
        }
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    };
    root.addEventListener('input', handleFieldInput);
    root.addEventListener('change', handleFieldInput);
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
    });
    root.addEventListener('submit', async (event) => {
        event.preventDefault();
        root.querySelectorAll('[data-error-for]').forEach((node) => {
            node.textContent = '';
            node.classList.add('hidden');
        });

        const response = await (window.NixCMS?.json || fetch)(root.dataset.saveUrl, {
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
            window.sessionStorage.setItem('nixcms:flash-toast', 'Form saved.');
        } catch (error) {}
        window.location.assign(overviewUrl);
    });

    render();
});

document.addEventListener('click', (event) => {
    const option = event.target.closest('[data-translation-suggestion-key]');
    if (option && activeSuggestInput) {
        activeSuggestInput.value = option.dataset.translationSuggestionKey || '';
        activeSuggestInput.dispatchEvent(new Event('input', {bubbles: true}));
        hideTranslationSuggestions();
        return;
    }

    if (!event.target.closest('[data-translation-key-suggest]') && !event.target.closest('[data-translation-suggest-box]')) {
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

function translationKey(value) {
    return String(value || '').trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '.').replace(/[.]{2,}/g, '.').replace(/^[._-]+|[._-]+$/g, '');
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
        <button type="button" class="grid w-full gap-0.5 rounded px-2.5 py-2 text-left transition hover:bg-slate-100 dark:hover:bg-slate-900" data-translation-suggestion-key="${escapeHtml(suggestion.key)}">
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
