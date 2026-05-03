import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';
import {actionMenuMarkup} from '../components/actionMenu.js';
import {AdminDataGrid, formatDate, statusChip as dataGridStatusChip} from '../components/dataGrid.js';
import {initStyledSelects} from '../components/styledSelect.js';

const reviewableStatuses = new Set(['draft', 'declined']);
const languageTitles = {
    de: 'German (DE)',
    en: 'English (EN)',
    fr: 'French (FR)',
    es: 'Spanish (ES)',
    it: 'Italian (IT)',
};
const languageFlags = {
    de: '🇩🇪',
    en: '🇬🇧',
    fr: '🇫🇷',
    es: '🇪🇸',
    it: '🇮🇹',
};
const tableCellClass = '[&_td]:h-[64px] [&_td]:border-b [&_td]:border-slate-900/10 [&_td]:px-[18px] [&_td]:py-3 [&_td]:text-left [&_td]:text-[rgba(48,48,48,.84)] dark:[&_td]:border-slate-400/10 dark:[&_td]:text-slate-200 [&_tbody_tr:last-child_td]:border-b-0';
const rowClass = 'article-grid-row cursor-pointer hover:[&_td]:bg-slate-900/[.035] dark:hover:[&_td]:bg-white/[.035]';
const titleCellClass = '!pl-6 font-semibold text-slate-950 dark:text-white';
const wideCellClass = 'article-grid-wide wide:table-cell max-wide:hidden';
const languageBadgeWrapClass = 'article-language-badges inline-flex flex-nowrap items-center gap-1.5';
const languageFlagClass = 'article-language-flag inline-flex rounded-md bg-slate-900/5 px-2 py-1 text-xs font-bold uppercase text-slate-700 dark:bg-white/10 dark:text-slate-200';
const languageSummaryClass = 'article-language-summary relative inline-flex items-center justify-center whitespace-nowrap rounded-md bg-slate-900/5 px-2 py-1 text-xs font-bold text-slate-700 dark:bg-white/10 dark:text-slate-200';
const languagePopoverClass = 'article-language-popover pointer-events-none fixed z-[1001] grid min-w-45 -translate-y-0.5 gap-1 rounded-lg border border-slate-300 bg-white p-2 opacity-0 shadow-[0_20px_70px_rgba(0,0,0,.18)] transition dark:border-slate-400/30 dark:bg-slate-900 dark:shadow-[0_20px_70px_rgba(0,0,0,.38)]';
const detailsClass = 'bg-slate-200/45 px-4 py-4 dark:bg-[#111a2a]/70';
const nestedTableClass = 'article-version-table w-full min-w-[1180px] table-fixed border-separate border-spacing-0 bg-transparent text-sm';
const nestedRowClass = 'bg-transparent hover:bg-slate-900/[.025] dark:hover:bg-white/[.025]';
const stateClass = 'py-8 text-center text-sm text-slate-500';

class ArticleGrid {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config') || '{}');
        this.grid = new AdminDataGrid(root, {
            frameSelector: '[data-article-grid-frame], [data-admin-grid-frame]',
            searchSelector: '[data-article-search], [data-admin-grid-search]',
            defaultSort: {key: 'title', direction: 'asc'},
            columns: this.columns(),
            rowId: (row) => row.id,
            rowClass: (row) => `${rowClass} admin-data-grid-row`,
            renderCell: (row, column) => this.renderCell(row, column),
            renderDetails: (row) => this.renderDetailsContent(row),
            detailsRowClass: 'admin-data-grid-details-row [&>td]:!h-auto [&>td]:!border-b [&>td]:!border-slate-900/10 [&>td]:!p-0 dark:[&>td]:!border-slate-400/15',
            searchableValues: (row) => this.searchValues(row),
            sortValue: (row, key) => sortValue(row, key),
            afterRender: () => {
                this.syncDetailColumnGeometry();
                requestAnimationFrame(() => this.syncDetailColumnGeometry());
            },
            onAction: async (event, target) => {
                event.preventDefault();
                await this.handleAction(target);
            },
        });
    }

    async init() {
        this.root.addEventListener('mouseover', (event) => this.showLanguagePopover(event));
        this.root.addEventListener('mouseout', (event) => this.hideLanguagePopover(event));
        this.root.addEventListener('focusin', (event) => this.showLanguagePopover(event));
        this.root.addEventListener('focusout', (event) => this.hideLanguagePopover(event));
        window.addEventListener('resize', () => {
            this.syncDetailColumnGeometry();
        });
        await this.grid.init();
    }

    columns() {
        return [
            {key: 'title', labelKey: 'title', colClass: 'w-[23%]', cellClass: titleCellClass, headerClass: '!pl-6'},
            {key: 'slug', label: 'Slug', colClass: 'w-[17%]', className: wideCellClass},
            {key: 'author', label: 'Author', colClass: 'w-[13%]'},
            {key: 'publishedAt', labelKey: 'publishedAt', colClass: 'w-[16%]', className: wideCellClass},
            {key: 'lastVersion', labelKey: 'version', colClass: 'w-[7%]'},
            {key: 'status', labelKey: 'status', colClass: 'w-[9%]'},
            {key: 'languages', labelKey: 'languages', sortable: false, colClass: 'w-[8%]'},
            {key: 'actions', labelKey: 'actions', sortable: false, colClass: 'w-[7%]', headerClass: 'text-right'},
        ];
    }

    renderCell(row, column) {
        const key = column.key;
        const renderers = {
            title: () => escapeHtml(row.title || '-'),
            slug: () => `<code class="rounded bg-slate-900/5 px-2 py-1 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-200">${escapeHtml(row.slug || '-')}</code>`,
            author: () => `<span class="text-slate-600 dark:text-slate-300">${escapeHtml(row.author || '-')}</span>`,
            publishedAt: () => escapeHtml(formatDate(row.publishedAt)),
            lastVersion: () => escapeHtml(row.lastVersion ? `v${Number(row.lastVersion)}` : '-'),
            status: () => statusChip(row.status),
            languages: () => this.languageBadges(this.languages(row)),
            actions: () => this.actionMenu(row),
        };

        return renderers[key]?.() ?? escapeHtml(row[key] || '-');
    }

    renderDetailsContent(row) {
        const versions = this.versions(row);
        const cells = versions.length === 0
            ? `<div class="${stateClass}">${escapeHtml(this.label('versions'))}: 0</div>`
            : `<table class="${nestedTableClass} ${tableCellClass}">
                ${tableColumnGroup()}
                <tbody>
                    ${versions.map((version) => this.versionRow(row, version)).join('')}
                </tbody>
            </table>`;

        return `<div class="${detailsClass}">${cells}</div>`;
    }

    syncDetailColumnGeometry() {
        this.root.querySelectorAll('.admin-data-grid-details-row').forEach((detailRow) => {
            const id = String(detailRow.dataset.adminGridRowId || '');
            const parentRow = id === '' ? null : this.root.querySelector(`[data-admin-grid-row-id="${CSS.escape(id)}"]`);
            const nestedTable = detailRow.querySelector('.article-version-table');
            if (!parentRow || !nestedTable) {
                return;
            }

            this.applyParentColumnGeometry(parentRow, nestedTable);
        });
    }

    applyParentColumnGeometry(parentRow, nestedTable) {
        const parentCells = Array.from(parentRow.children).filter((cell) => cell instanceof HTMLTableCellElement);
        if (parentCells.length === 0) {
            return;
        }

        const detailBox = nestedTable.getBoundingClientRect();
        const widths = parentCells.map((cell, index) => {
            const rect = cell.getBoundingClientRect();
            const nextRect = parentCells[index + 1]?.getBoundingClientRect();
            const measuredWidth = nextRect ? nextRect.left - rect.left : rect.width;
            const left = Math.max(rect.left, detailBox.left);
            const right = Math.min(rect.left + measuredWidth, detailBox.right);
            return Math.max(index === 0 ? 24 : 64, Math.round(right - left));
        });

        let colgroup = nestedTable.querySelector('colgroup');
        if (!colgroup) {
            colgroup = document.createElement('colgroup');
            nestedTable.prepend(colgroup);
        }

        colgroup.innerHTML = widths.map((width) => `<col style="width:${width}px">`).join('');
        nestedTable.style.tableLayout = 'fixed';
    }

    versionRow(row, version) {
        const editable = isReviewable(version.status);
        const editUrl = this.url('editVersion', {
            articleId: row.id,
            language: version.language,
            version: version.version,
        });

        return `
            <tr class="${nestedRowClass}">
                <td class="!pl-6">${editable ? `<a class="font-semibold text-blue-700 hover:text-blue-900 dark:text-slate-100 dark:hover:text-blue-300" href="${escapeAttr(editUrl)}">${escapeHtml(version.title || '-')}</a>` : `<span class="font-medium text-slate-800 dark:text-slate-200">${escapeHtml(version.title || '-')}</span>`}</td>
                <td><code class="rounded bg-slate-900/5 px-2 py-1 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-200">${escapeHtml(version.slug || '-')}</code></td>
                <td><span class="text-slate-600 dark:text-slate-300">${escapeHtml(row.author || '-')}</span></td>
                <td class="whitespace-nowrap">${escapeHtml(formatDate(version.publishedAt))}</td>
                <td class="whitespace-nowrap">v${Number(version.version || 0)}</td>
                <td class="whitespace-nowrap">${statusChip(version.status)}</td>
                <td class="whitespace-nowrap">${this.languageBadges([version.language])}</td>
                <td class="text-right">
                    ${this.versionActionMenu(row, version)}
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

        return actionMenuMarkup({
            label: this.label('actions'),
            actions: actions.map(([action, label]) => ({
                action,
                label,
                danger: action.includes('delete'),
                attributes: [`data-article-id="${id}"`],
                icon: actionIcon(action),
            })),
        });
    }

    versionActionMenu(row, version) {
        const id = escapeAttr(row.id);
        const language = escapeAttr(version.language);
        const versionNumber = escapeAttr(version.version);
        const actions = [
            ['edit-version', this.label('edit'), isReviewable(version.status)],
            ['new-version', this.label('newVersion'), String(version.language || '').trim() !== ''],
            ['delete-version', this.label('delete'), String(version.language || '').trim() !== ''],
        ].filter((item) => item[2]);

        if (actions.length === 0) {
            return '';
        }

        return actionMenuMarkup({
            label: this.label('actions'),
            actions: actions.map(([action, label]) => ({
                action,
                label,
                danger: action.includes('delete'),
                attributes: [
                    `data-article-id="${id}"`,
                    `data-language="${language}"`,
                    `data-version="${versionNumber}"`,
                ],
                icon: actionIcon(action),
            })),
        });
    }

    showLanguagePopover(event) {
        const summary = event.target.closest?.('.article-language-summary');
        if (!summary || !this.root.contains(summary)) {
            return;
        }

        this.positionLanguagePopover(summary);
        const popover = summary.querySelector('.article-language-popover');
        popover?.classList.add('opacity-100', 'pointer-events-auto', 'translate-y-0');
    }

    hideLanguagePopover(event) {
        const summary = event.target.closest?.('.article-language-summary');
        if (!summary || !this.root.contains(summary)) {
            return;
        }

        if (event.relatedTarget && summary.contains(event.relatedTarget)) {
            return;
        }

        const popover = summary.querySelector('.article-language-popover');
        popover?.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0');
    }

    positionLanguagePopover(summary) {
        const popover = summary.querySelector('.article-language-popover');
        if (!popover) {
            return;
        }

        const rect = summary.getBoundingClientRect();
        const width = Math.max(popover.offsetWidth || 180, 180);
        const left = Math.max(8, Math.min(window.innerWidth - width - 8, rect.left));
        const top = Math.min(window.innerHeight - 8, rect.bottom + 8);

        summary.style.setProperty('--article-language-popover-left', `${left}px`);
        summary.style.setProperty('--article-language-popover-top', `${top}px`);
        popover.style.left = `${left}px`;
        popover.style.top = `${top}px`;
    }

    async handleAction(target) {
        const row = this.rowById(target.dataset.articleId);
        const action = target.dataset.gridAction;

        if (action === 'toggle') {
            const id = String(target.dataset.articleId ?? '');
            this.grid.expanded.has(id) ? this.grid.expanded.delete(id) : this.grid.expanded.add(id);
            this.grid.render();
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
        await this.grid.refresh();
    }

    async delete(row) {
        const language = await this.askLanguage(this.label('chooseLanguage'), this.languages(row), this.label('delete'));
        if (!language || !await this.confirm(this.label('deleteConfirm'), this.label('delete'))) {
            return;
        }

        await this.post(this.url('delete', {articleId: row.id, language}));
        toast('Deleted.');
        await this.grid.refresh();
    }

    async deleteVersion(row, language, version) {
        if (!await this.confirm(this.label('deleteConfirm'), this.label('delete'))) {
            return;
        }

        await this.post(this.url('deleteVersion', {articleId: row.id, language, version}));
        toast('Deleted.');
        await this.grid.refresh();
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
        const promptResult = prompt(`
            <div class="notify-form">
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(this.label('targetMode'))}</span>
                    <select id="article-target-mode" data-styled-select>
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
        window.setTimeout(() => initStyledSelects(document.querySelectorAll('#article-target-mode[data-styled-select]')), 0);
        const result = await promptResult;

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
        const promptResult = prompt(`
            <div class="notify-form">
                <label class="notify-field">
                    <span class="notify-field-label">${escapeHtml(title)}</span>
                    <select id="article-language-select" data-styled-select>
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
        window.setTimeout(() => initStyledSelects(document.querySelectorAll('#article-language-select[data-styled-select]')), 0);
        const result = await promptResult;

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
                <div class="grid gap-2">
                    ${available.map((language) => `
                        <label class="flex min-h-10 items-center gap-2 rounded-lg border border-[var(--border)] bg-admin-field px-3 text-sm font-semibold text-[var(--text)]">
                            <input class="h-4 w-4 accent-[var(--admin-page-accent)]" type="checkbox" value="${escapeAttr(language)}" checked>
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

    searchValues(row) {
        return [
            row.title,
            row.slug,
            row.author,
            row.status,
            ...this.versions(row).flatMap((version) => [version.title, version.slug, version.language, version.status]),
        ];
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
        return this.grid.rowById(id);
    }

    languageBadges(languages) {
        const normalized = [...new Set((languages || []).filter(Boolean))];
        if (normalized.length === 0) {
            return '-';
        }

        if (normalized.length > 2) {
            const label = this.label('languageCount').replace('%s', String(normalized.length));
            return `<span class="${languageBadgeWrapClass}">
                <span class="${languageSummaryClass}" tabindex="0" aria-label="${escapeAttr(normalized.map(languageLabel).join(', '))}">
                    ${escapeHtml(label)}
                    <span class="${languagePopoverClass}">
                        ${normalized.map((language) => `<span class="flex min-h-[26px] items-center gap-2 whitespace-nowrap text-[13px] font-semibold text-[var(--text)]"><span aria-hidden="true">${escapeHtml(languageFlags[language] || language.toUpperCase())}</span><span>${escapeHtml(languageLabel(language))}</span></span>`).join('')}
                    </span>
                </span>
            </span>`;
        }

        return `<span class="${languageBadgeWrapClass}">
            ${normalized.map((language) => `<span class="${languageFlagClass}" title="${escapeAttr(languageLabel(language))}">${escapeHtml(language.toUpperCase())}</span>`).join('')}
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
        this.grid.renderState(message);
    }
}

function isReviewable(status) {
    return reviewableStatuses.has(String(status || '').trim().toLowerCase());
}

function languageLabel(language) {
    const normalized = String(language || '').toLowerCase();
    return languageTitles[normalized] || normalized.toUpperCase();
}

function statusChip(status) {
    return dataGridStatusChip(status);
}

function sortValue(row, key) {
    if (key === 'lastVersion') {
        return Number(row?.[key] ?? 0);
    }

    if (key === 'publishedAt') {
        const parsed = Date.parse(String(row?.publishedAt || '').replace(' ', 'T'));
        return Number.isNaN(parsed) ? 0 : parsed;
    }

    return String(row?.[key] ?? '').trim().toLowerCase();
}

function compareSortValues(left, right) {
    if (typeof left === 'number' && typeof right === 'number') {
        return left === right ? 0 : (left < right ? -1 : 1);
    }

    return String(left).localeCompare(String(right), 'de', {
        numeric: true,
        sensitivity: 'base',
    });
}

function tableColumnGroup() {
    return `
        <colgroup>
            <col class="w-[23%]">
            <col class="w-[17%]">
            <col class="w-[13%]">
            <col class="w-[16%]">
            <col class="w-[7%]">
            <col class="w-[9%]">
            <col class="w-[8%]">
            <col class="w-[7%]">
        </colgroup>
    `;
}

function actionIcon(action) {
    if (action.includes('delete')) {
        return 'icon-cross';
    }
    if (action.includes('publish') || action.includes('preview')) {
        return 'icon-circle-right';
    }
    if (action.includes('translation') || action.includes('translate')) {
        return 'icon-earth';
    }
    if (action.includes('version')) {
        return 'icon-file-empty';
    }

    return 'icon-checkmark';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

document.querySelectorAll('[data-article-grid]').forEach((root) => {
    new ArticleGrid(root).init();
});
