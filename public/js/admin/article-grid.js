import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';

const reviewableStatuses = new Set(['draft', 'review', 'declined']);
const languageTitles = {
    de: 'German (DE)',
    en: 'English (EN)',
    fr: 'French (FR)',
    es: 'Spanish (ES)',
    it: 'Italian (IT)',
};
const languageFlags = {
    de: 'DE',
    en: 'EN',
    fr: 'FR',
    es: 'ES',
    it: 'IT',
};

class ArticleGrid {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config') || '{}');
        this.frame = root.querySelector('[data-article-grid-frame]');
        this.search = root.querySelector('[data-article-search]');
        this.rows = [];
        this.expanded = new Set();
    }

    async init() {
        this.search?.addEventListener('input', () => this.render());
        this.root.addEventListener('click', (event) => this.handleClick(event));
        this.root.addEventListener('click', (event) => this.handleRowClick(event));
        await this.refresh();
    }

    async refresh() {
        this.renderState(this.label('loading'));

        try {
            const response = await fetch(this.config.endpoint, {headers: {'Accept': 'application/json'}});
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            this.rows = Array.isArray(data) ? data : [];
            this.render();
        } catch (error) {
            this.renderState('Could not load articles.');
        }
    }

    render() {
        const rows = this.filteredRows();
        if (rows.length === 0) {
            this.renderState(this.searchTerm() === '' ? this.label('empty') : this.label('noResults'));
            return;
        }

        const table = document.createElement('table');
        table.className = 'article-grid-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>${sortableHeader('#')}</th>
                    <th>${sortableHeader(this.label('title'))}</th>
                    <th class="article-grid-wide">${sortableHeader('Slug')}</th>
                    <th>${sortableHeader('Author')}</th>
                    <th class="article-grid-wide">${sortableHeader(this.label('publishedAt'))}</th>
                    <th>${sortableHeader(this.label('version'))}</th>
                    <th>${sortableHeader(this.label('status'))}</th>
                    <th>${escapeHtml(this.label('languages'))}</th>
                    <th class="article-grid-actions-head">${escapeHtml(this.label('actions'))}</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;

        const body = table.querySelector('tbody');
        rows.forEach((row) => {
            body.appendChild(this.renderMainRow(row));
            if (this.expanded.has(String(row.id))) {
                body.appendChild(this.renderDetailsRow(row));
            }
        });

        this.frame.replaceChildren(table);
    }

    renderMainRow(row) {
        const tr = document.createElement('tr');
        tr.className = 'article-grid-row';
        tr.dataset.articleId = String(row.id ?? '');
        tr.innerHTML = `
            <td>${escapeHtml(row.id || '-')}</td>
            <td class="article-grid-title">${escapeHtml(row.title || '-')}</td>
            <td class="article-grid-wide">${escapeHtml(row.slug || '-')}</td>
            <td>${escapeHtml(row.author || '-')}</td>
            <td class="article-grid-wide">${escapeHtml(formatDate(row.publishedAt))}</td>
            <td>${escapeHtml(row.lastVersion ? `v${Number(row.lastVersion)}` : '-')}</td>
            <td>${statusChip(row.status)}</td>
            <td>${this.languageBadges(this.languages(row))}</td>
            <td>${this.actionMenu(row)}</td>
        `;

        return tr;
    }

    handleRowClick(event) {
        if (
            event.target.closest('[data-grid-action]')
            || event.target.closest('a, button, details, summary, input, select, textarea')
        ) {
            return;
        }

        const rowElement = event.target.closest('.article-grid-row');
        if (!rowElement) {
            return;
        }

        const id = String(rowElement.dataset.articleId || '');
        if (id === '') {
            return;
        }

        this.expanded.has(id) ? this.expanded.delete(id) : this.expanded.add(id);
        this.render();
    }

    renderDetailsRow(row) {
        const tr = document.createElement('tr');
        tr.className = 'article-grid-details-row';
        const versions = this.versions(row);
        const cells = versions.length === 0
            ? `<div class="article-grid-state">${escapeHtml(this.label('versions'))}: 0</div>`
            : `<table class="article-version-table">
                <thead>
                    <tr>
                        <th>${escapeHtml(this.label('language'))}</th>
                        <th>${escapeHtml(this.label('title'))}</th>
                        <th>Slug</th>
                        <th>${escapeHtml(this.label('version'))}</th>
                        <th>${escapeHtml(this.label('status'))}</th>
                        <th>${escapeHtml(this.label('publishedAt'))}</th>
                        <th>${escapeHtml(this.label('actions'))}</th>
                    </tr>
                </thead>
                <tbody>
                    ${versions.map((version) => this.versionRow(row, version)).join('')}
                </tbody>
            </table>`;

        tr.innerHTML = `<td colspan="9"><div class="article-grid-details">${cells}</div></td>`;
        return tr;
    }

    versionRow(row, version) {
        const editable = isReviewable(version.status);
        const editUrl = this.url('editVersion', {
            articleId: row.id,
            language: version.language,
            version: version.version,
        });

        return `
            <tr>
                <td>${this.languageBadges([version.language])}</td>
                <td>${editable ? `<a href="${escapeAttr(editUrl)}">${escapeHtml(version.title || '-')}</a>` : escapeHtml(version.title || '-')}</td>
                <td>${escapeHtml(version.slug || '-')}</td>
                <td>v${Number(version.version || 0)}</td>
                <td>${statusChip(version.status)}</td>
                <td>${escapeHtml(formatDate(version.publishedAt))}</td>
                <td>
                    <div class="article-inline-actions">
                        ${editable ? `<button type="button" data-grid-action="edit-version" data-article-id="${escapeAttr(row.id)}" data-language="${escapeAttr(version.language)}" data-version="${escapeAttr(version.version)}">${escapeHtml(this.label('edit'))}</button>` : ''}
                        <button type="button" data-grid-action="new-version" data-article-id="${escapeAttr(row.id)}" data-language="${escapeAttr(version.language)}" data-version="${escapeAttr(version.version)}">${escapeHtml(this.label('newVersion'))}</button>
                        ${String(version.status) !== 'published' ? `<button type="button" data-grid-action="delete-version" data-article-id="${escapeAttr(row.id)}" data-language="${escapeAttr(version.language)}" data-version="${escapeAttr(version.version)}" class="danger">${escapeHtml(this.label('delete'))}</button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }

    actionMenu(row) {
        const id = escapeAttr(row.id);
        const actions = [
            ['preview', this.label('preview'), this.draftLanguages(row).length > 0],
            ['edit-draft', this.label('editDraft'), this.draftLanguages(row).length > 0],
            ['new-version', this.label('newVersion'), true],
            ['new-translation', this.label('newTranslation'), this.availableTranslationLanguages(row).length > 0],
            ['publish', this.label('publish'), this.draftLanguages(row).length > 0],
            ['auto-translate', this.label('autoTranslate'), this.languages(row).length > 0],
            ['delete', this.label('delete'), this.languages(row).length > 0],
        ].filter((item) => item[2]);

        return `
            <details class="article-action-menu">
                <summary aria-label="${escapeAttr(this.label('actions'))}">...</summary>
                <div>
                    ${actions.map(([action, label]) => `<button type="button" data-grid-action="${action}" data-article-id="${id}">${escapeHtml(label)}</button>`).join('')}
                </div>
            </details>
        `;
    }

    async handleClick(event) {
        const target = event.target.closest('[data-grid-action]');
        if (!target) {
            return;
        }

        const row = this.rowById(target.dataset.articleId);
        const action = target.dataset.gridAction;

        if (action === 'toggle') {
            const id = String(target.dataset.articleId ?? '');
            this.expanded.has(id) ? this.expanded.delete(id) : this.expanded.add(id);
            this.render();
            return;
        }

        if (!row) {
            return;
        }

        if (action === 'preview') {
            await this.goToDraftLanguage(row, 'preview');
        } else if (action === 'edit-draft') {
            await this.goToDraftLanguage(row, 'edit');
        } else if (action === 'new-version') {
            this.createVersion(row, target.dataset.language, target.dataset.version);
        } else if (action === 'new-translation') {
            await this.createTranslation(row);
        } else if (action === 'publish') {
            await this.publish(row);
        } else if (action === 'auto-translate') {
            await this.autoTranslate(row);
        } else if (action === 'delete') {
            await this.delete(row);
        } else if (action === 'edit-version') {
            window.location.href = this.url('editVersion', {
                articleId: row.id,
                language: target.dataset.language,
                version: target.dataset.version,
            });
        } else if (action === 'delete-version') {
            await this.deleteVersion(row, target.dataset.language, target.dataset.version);
        }
    }

    async goToDraftLanguage(row, urlKey) {
        const language = await this.askLanguage(this.label('chooseLanguage'), this.draftLanguages(row));
        if (!language) {
            return;
        }

        window.location.href = this.url(urlKey, {articleId: row.id, language});
    }

    createVersion(row, language = '', version = '') {
        const url = this.url('createVersion', {articleId: row.id});
        const query = String(language || '').trim() !== ''
            ? `?language=${encodeURIComponent(language)}${Number(version) > 0 ? `&source_version=${Number(version)}` : ''}`
            : '';

        window.location.href = `${url}${query}`;
    }

    async createTranslation(row) {
        const language = await this.askLanguage(this.label('chooseLanguage'), this.availableTranslationLanguages(row));
        if (!language) {
            return;
        }

        window.location.href = this.url('createTranslation', {articleId: row.id, language});
    }

    async publish(row) {
        const languages = await this.askLanguages(this.label('chooseLanguages'), this.draftLanguages(row), this.label('publish'));
        if (languages.length === 0 || !await this.confirm(this.label('publishConfirm'), this.label('publish'))) {
            return;
        }

        for (const language of languages) {
            await this.post(this.url('publish', {articleId: row.id, language}));
        }

        toast('Published.');
        await this.refresh();
    }

    async delete(row) {
        const language = await this.askLanguage(this.label('chooseLanguage'), this.languages(row), this.label('delete'));
        if (!language || !await this.confirm(this.label('deleteConfirm'), this.label('delete'))) {
            return;
        }

        await this.post(this.url('delete', {articleId: row.id, language}));
        toast('Deleted.');
        await this.refresh();
    }

    async deleteVersion(row, language, version) {
        if (!await this.confirm(this.label('deleteConfirm'), this.label('delete'))) {
            return;
        }

        await this.post(this.url('deleteVersion', {articleId: row.id, language, version}));
        toast('Deleted.');
        await this.refresh();
    }

    async autoTranslate(row) {
        const sourceLanguage = await this.askLanguage(this.label('sourceLanguage'), this.languages(row), this.label('continue'));
        if (!sourceLanguage) {
            return;
        }

        const mode = await this.askMode();
        if (!mode) {
            return;
        }

        const targets = this.config.languages.filter((language) => language !== sourceLanguage);
        const targetLanguages = mode === 'all'
            ? targets
            : await this.askLanguages(this.label('targetLanguage'), targets, this.label('queue'));

        if (targetLanguages.length === 0) {
            return;
        }

        await this.post(this.url('translate', {articleId: row.id}), {
            source_language: sourceLanguage,
            target_languages: targetLanguages,
        });
        toast(`Translation queued for ${targetLanguages.length} language(s).`);
    }

    async askMode() {
        let selected = 'one';
        const result = await prompt(`
            <div class="notify-form">
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(this.label('targetMode'))}</span>
                    <select id="article-target-mode">
                        <option value="one">${escapeHtml(this.label('oneLanguage'))}</option>
                        <option value="all">${escapeHtml(this.label('allLanguages'))}</option>
                    </select>
                </label>
            </div>
        `, {
            confirmButtonText: this.label('continue'),
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
            confirmCallback: async (instance) => {
                selected = instance.container.querySelector('#article-target-mode')?.value || 'one';
                instance.promise.resolve();
                instance.remove();
                return true;
            },
        });

        return result === false ? null : selected;
    }

    async askLanguage(title, languages, confirmButtonText = this.label('select')) {
        const available = [...new Set(languages.filter(Boolean))];
        if (available.length === 0) {
            toast(this.label('noLanguage'));
            return null;
        }

        if (available.length === 1) {
            return available[0];
        }

        let selected = available[0];
        const result = await prompt(`
            <div class="notify-form">
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(title)}</span>
                    <select id="article-language-select">
                        ${available.map((language) => `<option value="${escapeAttr(language)}">${escapeHtml(languageLabel(language))}</option>`).join('')}
                    </select>
                </label>
            </div>
        `, {
            confirmButtonText,
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
            confirmCallback: async (instance) => {
                selected = instance.container.querySelector('#article-language-select')?.value || '';
                instance.promise.resolve();
                instance.remove();
                return true;
            },
        });

        return result === false ? null : selected;
    }

    async askLanguages(title, languages, confirmButtonText = this.label('select')) {
        const available = [...new Set(languages.filter(Boolean))];
        if (available.length === 0) {
            toast(this.label('noLanguage'));
            return [];
        }

        let selected = [...available];
        const result = await prompt(`
            <div class="notify-form">
                <div class="notify-field-label">${escapeHtml(title)}</div>
                <div class="article-language-choice-list">
                    ${available.map((language) => `
                        <label>
                            <input type="checkbox" value="${escapeAttr(language)}" checked>
                            <span>${escapeHtml(languageLabel(language))}</span>
                        </label>
                    `).join('')}
                </div>
            </div>
        `, {
            confirmButtonText,
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
            confirmCallback: async (instance) => {
                selected = Array.from(instance.container.querySelectorAll('input[type="checkbox"]:checked'))
                    .map((input) => input.value)
                    .filter(Boolean);
                if (selected.length === 0) {
                    toast(this.label('noLanguage'));
                    return false;
                }
                instance.promise.resolve();
                instance.remove();
                return true;
            },
        });

        return result === false ? [] : selected;
    }

    async confirm(message, confirmButtonText) {
        return await prompt(`<div class="my-5">${escapeHtml(message)}</div>`, {
            confirmButtonText,
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
        });
    }

    async post(url, payload = null) {
        const response = await (window.NixCMS?.fetch || fetch)(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': String(window.NixCMS?.csrfToken?.() || this.config.csrfToken || ''),
            },
            body: payload === null ? undefined : JSON.stringify(payload),
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || data?.success === false) {
            toast(data?.reason || 'Action failed.');
        }
        return data;
    }

    filteredRows() {
        const term = this.searchTerm().toLowerCase();
        if (term === '') {
            return this.rows;
        }

        return this.rows.filter((row) => [
            row.id,
            row.title,
            row.slug,
            row.author,
            row.status,
            ...this.versions(row).flatMap((version) => [version.title, version.slug, version.language, version.status]),
        ].some((value) => String(value ?? '').toLowerCase().includes(term)));
    }

    searchTerm() {
        return String(this.search?.value ?? '').trim();
    }

    versions(row) {
        return Array.isArray(row?.versions) ? row.versions : [];
    }

    languages(row) {
        return [...new Set(this.versions(row).map((version) => String(version.language || '').trim()).filter(Boolean))];
    }

    draftLanguages(row) {
        return [...new Set(this.versions(row)
            .filter((version) => isReviewable(version.status))
            .map((version) => String(version.language || '').trim())
            .filter(Boolean))];
    }

    availableTranslationLanguages(row) {
        const existing = this.languages(row);
        return (this.config.languages || []).filter((language) => !existing.includes(language));
    }

    rowById(id) {
        return this.rows.find((row) => String(row.id) === String(id)) || null;
    }

    languageBadges(languages) {
        const normalized = [...new Set((languages || []).filter(Boolean))];
        if (normalized.length === 0) {
            return '-';
        }

        return `<span class="article-language-badges">
            ${normalized.map((language) => `<span class="article-language-flag" title="${escapeAttr(languageLabel(language))}">${escapeHtml(languageFlags[language] || language.toUpperCase())}</span>`).join('')}
        </span>`;
    }

    url(key, params = {}) {
        let value = String(this.config.urls?.[key] || '#');
        Object.entries(params).forEach(([name, replacement]) => {
            value = value.replace(`{${name}}`, encodeURIComponent(String(replacement ?? '')));
        });
        return value;
    }

    label(key) {
        return String(this.config.labels?.[key] || key);
    }

    renderState(message) {
        this.frame.innerHTML = `<div class="article-grid-state">${escapeHtml(message)}</div>`;
    }
}

function isReviewable(status) {
    return reviewableStatuses.has(String(status || '').trim().toLowerCase());
}

function languageLabel(language) {
    const normalized = String(language || '').toLowerCase();
    return languageTitles[normalized] || normalized.toUpperCase();
}

function formatDate(value) {
    const raw = String(value || '').trim();
    if (raw === '') {
        return '-';
    }

    const parsed = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return raw;
    }

    return parsed.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusChip(status) {
    const value = String(status || '-').trim().toLowerCase();
    const label = escapeHtml(value || '-');
    return `<span class="article-status-chip" data-status="${escapeAttr(value)}">${label}</span>`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function sortableHeader(label) {
    return `<span class="article-grid-th-content">${escapeHtml(label)} <span class="article-grid-sort-mark" aria-hidden="true">↕</span></span>`;
}

function escapeAttr(value) {
    return escapeHtml(value);
}

document.querySelectorAll('[data-article-grid]').forEach((root) => {
    new ArticleGrid(root).init();
});
