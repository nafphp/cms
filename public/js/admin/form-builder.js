import toast from '../lib/notifyJs/Modules/Toast.js';

const inputClass = 'min-h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition focus:border-[var(--admin-page-accent)] dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const buttonClass = 'inline-flex min-h-8 items-center rounded-md border border-slate-300 px-2.5 text-xs font-semibold text-slate-600 transition hover:border-[var(--admin-page-accent)] dark:border-slate-700 dark:text-slate-300';
const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
const slug = (value) => String(value || '').trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^[-._]+|[-._]+$/g, '');

document.querySelectorAll('[data-form-builder]').forEach((root) => {
    const data = JSON.parse(root.dataset.form || '{}');
    const fieldsRoot = root.querySelector('[data-fields]');
    const state = root.querySelector('[data-save-state]');
    const overviewUrl = root.dataset.overviewUrl || '/admin/forms';
    let identifierTouched = Boolean(data.identifier && data.identifier !== 'contact');

    root.elements.title.value = data.title || '';
    root.elements.identifier.value = data.identifier || '';
    root.elements.status.value = data.status || 'draft';
    root.elements.submitLabel.value = data.settings?.submitLabel || 'Send message';
    root.elements.successMessage.value = data.settings?.successMessage || 'Thank you.';

    const fields = Array.isArray(data.fields) ? data.fields : [];
    const payload = () => ({
        id: Number(data.id || 0),
        title: root.elements.title.value,
        identifier: root.elements.identifier.value,
        status: root.elements.status.value,
        fields,
        settings: {
            submitLabel: root.elements.submitLabel.value,
            successMessage: root.elements.successMessage.value,
        },
    });
    const initial = JSON.stringify(payload());

    const blankField = () => ({
        name: `field-${fields.length + 1}`,
        label: '',
        type: 'text',
        required: false,
        placeholder: '',
        options: [],
    });

    const render = () => {
        fieldsRoot.innerHTML = fields.map((field, index) => `
            <article class="grid gap-3 rounded-lg border border-slate-300/70 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/55" data-field-index="${index}">
                <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px_auto]">
                    <input class="${inputClass}" data-field-prop="label" value="${escapeHtml(field.label || '')}" placeholder="Label">
                    <input class="${inputClass} font-mono" data-field-prop="name" value="${escapeHtml(field.name || '')}" placeholder="field-name">
                    <select class="${inputClass}" data-field-prop="type">
                        ${['text', 'email', 'textarea', 'select', 'checkbox'].map((type) => `<option value="${type}" ${field.type === type ? 'selected' : ''}>${type}</option>`).join('')}
                    </select>
                    <label class="inline-flex min-h-10 items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" data-field-prop="required" ${field.required ? 'checked' : ''}>
                        Required
                    </label>
                </div>
                <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                    <input class="${inputClass}" data-field-prop="placeholder" value="${escapeHtml(field.placeholder || '')}" placeholder="Placeholder">
                    <textarea class="${inputClass} min-h-10 resize-y" data-field-prop="options" placeholder="Options, one per line">${escapeHtml((field.options || []).join('\n'))}</textarea>
                    <button class="${buttonClass}" type="button" data-remove-field="${index}">Remove</button>
                </div>
            </article>
        `).join('');
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
    root.addEventListener('input', (event) => {
        const target = event.target.closest('[data-field-prop]');
        if (!target) {
            state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
            return;
        }

        const card = target.closest('[data-field-index]');
        const field = fields[Number(card?.dataset.fieldIndex || -1)];
        if (!field) return;

        const prop = target.dataset.fieldProp;
        field[prop] = prop === 'required'
            ? target.checked
            : prop === 'options'
                ? target.value.split('\n').map((item) => item.trim()).filter(Boolean)
                : prop === 'name'
                    ? slug(target.value)
                    : target.value;
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    });
    root.addEventListener('change', () => {
        state.textContent = JSON.stringify(payload()) === initial ? 'No unsaved changes.' : 'Unsaved changes.';
    });
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-add-field]')) {
            fields.push(blankField());
            render();
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
            body: JSON.stringify(payload()),
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
