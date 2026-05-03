import {actionMenuMarkup, setupActionMenus} from './actionMenu.js';

export const dataGridClasses = {
    frame: 'relative overflow-x-auto rounded-lg border border-slate-300/70 bg-white/80 shadow-[0_16px_34px_rgba(15,23,42,.08)] dark:border-slate-400/25 dark:bg-slate-950/50 dark:shadow-none',
    table: 'admin-data-grid-table w-full min-w-[980px] table-fixed border-separate border-spacing-0 text-sm',
    cells: '[&_td]:h-[78px] [&_td]:border-b [&_td]:border-slate-900/10 [&_td]:px-[18px] [&_td]:py-4 [&_td]:text-left [&_td]:text-[rgba(48,48,48,.86)] dark:[&_td]:border-slate-400/15 dark:[&_td]:text-slate-100 [&_th]:h-[46px] [&_th]:border-b [&_th]:border-slate-900/10 [&_th]:bg-slate-200/70 [&_th]:px-[18px] [&_th]:py-[11px] [&_th]:text-left [&_th]:text-[13px] [&_th]:font-semibold [&_th]:leading-tight [&_th]:text-slate-700 dark:[&_th]:border-slate-400/15 dark:[&_th]:bg-slate-800/50 dark:[&_th]:text-slate-300',
    row: 'cursor-pointer hover:[&_td]:bg-slate-900/[.035] dark:hover:[&_td]:bg-white/[.035]',
    sortButton: 'admin-data-grid-sort-button inline-flex min-w-0 cursor-pointer items-center p-0 text-left font-inherit text-inherit',
    sortMark: 'admin-data-grid-sort-mark text-[11px] font-medium leading-none text-slate-500 dark:text-slate-400',
    state: 'py-8 text-center text-sm text-slate-500',
};

export class AdminDataGrid {
    constructor(root, options = {}) {
        this.root = root;
        this.config = parseConfig(root);
        this.options = options;
        this.frame = root.querySelector(options.frameSelector || '[data-admin-grid-frame]');
        this.search = root.querySelector(options.searchSelector || '[data-admin-grid-search]');
        this.columns = options.columns || this.config.columns || [];
        this.rows = Array.isArray(options.rows)
            ? options.rows
            : (Array.isArray(this.config.rows) ? this.config.rows : []);
        this.expanded = new Set();
        this.sort = {
            key: this.config.sort?.key || options.defaultSort?.key || this.columns.find((column) => column.sortable !== false)?.key || '',
            direction: this.config.sort?.direction || options.defaultSort?.direction || 'asc',
        };
    }

    async init() {
        setupActionMenus(this.root);
        this.search?.addEventListener('input', () => this.render());
        this.root.addEventListener('click', (event) => this.handleSortClick(event));
        this.root.addEventListener('click', (event) => this.handleActionClick(event));
        this.root.addEventListener('click', (event) => this.handleRowClick(event));

        if (this.config.endpoint || this.options.endpoint) {
            await this.refresh();
            return;
        }

        this.render();
    }

    async refresh() {
        this.renderState(this.label('loading'));

        try {
            const response = await fetch(this.options.endpoint || this.config.endpoint, {headers: {'Accept': 'application/json'}});
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            this.rows = Array.isArray(data) ? data : (Array.isArray(data?.rows) ? data.rows : []);
            this.render();
        } catch (error) {
            this.renderState(this.config.labels?.loadError || 'Could not load data.');
        }
    }

    render() {
        const rows = this.sortedRows(this.filteredRows());
        if (rows.length === 0) {
            this.renderState(this.searchTerm() === '' ? this.label('empty') : this.label('noResults'));
            return;
        }

        this.frame.style.overflowX = '';
        const table = document.createElement('table');
        table.className = `${this.options.tableClass || dataGridClasses.table} ${this.options.cellClass || dataGridClasses.cells}`;
        table.innerHTML = `
            ${this.colgroup()}
            <thead>
                <tr>${this.columns.map((column) => this.header(column)).join('')}</tr>
            </thead>
            <tbody></tbody>
        `;

        const body = table.querySelector('tbody');
        rows.forEach((row) => {
            body.appendChild(this.mainRow(row));
            const id = this.rowId(row);
            if (id !== '' && this.expanded.has(id) && typeof this.options.renderDetails === 'function') {
                body.appendChild(this.detailsRow(row));
            }
        });

        this.frame.replaceChildren(table);
        this.options.afterRender?.(this);
    }

    header(column) {
        const sortable = column.sortable !== false && column.key !== 'actions';
        const headerClass = [column.width || '', column.headerClass || '', column.className || ''].filter(Boolean).join(' ');
        const label = this.columnLabel(column);

        if (!sortable) {
            return `<th class="${escapeAttr(headerClass)}">${escapeHtml(label)}</th>`;
        }

        const active = this.sort.key === column.key;
        const direction = active ? this.sort.direction : 'none';
        const mark = active ? (direction === 'asc' ? '↑' : '↓') : '↕';

        return `<th class="${escapeAttr(headerClass)}">
            <button class="${dataGridClasses.sortButton} ${active ? 'is-active' : ''}" type="button" data-admin-grid-sort="${escapeAttr(column.key)}" aria-sort="${escapeAttr(direction)}">
                <span class="inline-flex min-w-0 items-center gap-1">${escapeHtml(label)} <span class="${dataGridClasses.sortMark} ${active ? 'text-slate-800 dark:text-slate-50' : ''}" aria-hidden="true">${mark}</span></span>
            </button>
        </th>`;
    }

    mainRow(row) {
        const tr = document.createElement('tr');
        const id = this.rowId(row);
        tr.className = this.options.rowClass?.(row) || dataGridClasses.row;
        tr.dataset.adminGridRowId = id;
        tr.innerHTML = this.columns.map((column) => {
            const cellClass = [column.cellClass || '', column.className || ''].filter(Boolean).join(' ');
            return `<td class="${escapeAttr(cellClass)}">${this.cell(row, column)}</td>`;
        }).join('');
        return tr;
    }

    detailsRow(row) {
        const tr = document.createElement('tr');
        tr.className = this.options.detailsRowClass || 'admin-data-grid-details-row';
        tr.dataset.adminGridRowId = this.rowId(row);
        tr.innerHTML = `<td colspan="${this.columns.length}">${this.options.renderDetails(row, this)}</td>`;
        return tr;
    }

    cell(row, column) {
        if (typeof column.render === 'function') {
            return column.render(row, column, this);
        }

        if (typeof this.options.renderCell === 'function') {
            const rendered = this.options.renderCell(row, column, this);
            if (rendered !== undefined) {
                return rendered;
            }
        }

        return defaultCell(row, column, this);
    }

    handleSortClick(event) {
        const button = event.target.closest('[data-admin-grid-sort]');
        if (!button || !this.root.contains(button)) {
            return;
        }

        const key = String(button.dataset.adminGridSort || '');
        if (key === '') {
            return;
        }

        this.sort = {
            key,
            direction: this.sort.key === key && this.sort.direction === 'asc' ? 'desc' : 'asc',
        };
        this.render();
    }

    handleActionClick(event) {
        const target = event.target.closest('[data-grid-action]');
        if (!target || !this.root.contains(target)) {
            return;
        }

        this.options.onAction?.(event, target, this);
    }

    handleRowClick(event) {
        if (
            event.target.closest('.admin-data-grid-details-row')
            || event.target.closest('[data-admin-grid-details]')
            || event.target.closest('.article-version-table')
            || event.target.closest('.article-version-row')
            || event.target.closest('[data-grid-action]')
            || event.target.closest('a, button, details, summary, input, select, textarea')
        ) {
            return;
        }

        const rowElement = event.target.closest('[data-admin-grid-row-id]');
        if (!rowElement || !this.root.contains(rowElement)) {
            return;
        }

        const id = String(rowElement.dataset.adminGridRowId || '');
        if (id === '') {
            return;
        }

        if (typeof this.options.onRowClick === 'function') {
            this.options.onRowClick(id, this);
            return;
        }

        if (typeof this.options.renderDetails === 'function') {
            this.expanded.has(id) ? this.expanded.delete(id) : this.expanded.add(id);
            this.render();
        }
    }

    filteredRows() {
        const term = this.searchTerm().toLowerCase();
        if (term === '') {
            return this.rows;
        }

        return this.rows.filter((row) => this.searchValues(row).some((value) => String(value ?? '').toLowerCase().includes(term)));
    }

    sortedRows(rows) {
        const key = this.sort.key;
        const direction = this.sort.direction === 'desc' ? -1 : 1;

        return rows
            .map((row, index) => ({row, index}))
            .sort((left, right) => {
                const result = compareSortValues(this.sortValue(left.row, key), this.sortValue(right.row, key));
                return result === 0 ? left.index - right.index : result * direction;
            })
            .map((item) => item.row);
    }

    searchValues(row) {
        if (typeof this.options.searchableValues === 'function') {
            return this.options.searchableValues(row, this);
        }

        const keys = this.config.search || this.columns.map((column) => column.key).filter(Boolean);
        return keys.map((key) => valueByPath(row, key));
    }

    sortValue(row, key) {
        if (typeof this.options.sortValue === 'function') {
            const value = this.options.sortValue(row, key, this);
            if (value !== undefined) {
                return value;
            }
        }

        const value = valueByPath(row, key);
        if (typeof value === 'number') {
            return value;
        }

        if (key.toLowerCase().includes('date') || key.toLowerCase().includes('at') || key.toLowerCase().includes('updated')) {
            const parsed = Date.parse(String(value || '').replace(' ', 'T'));
            return Number.isNaN(parsed) ? 0 : parsed;
        }

        return String(value ?? '').trim().toLowerCase();
    }

    colgroup() {
        const columns = this.columns
            .map((column) => column.colClass || column.width || '')
            .map((className) => `<col${className !== '' ? ` class="${escapeAttr(className)}"` : ''}>`)
            .join('');

        return columns !== '' ? `<colgroup>${columns}</colgroup>` : '';
    }

    rowById(id) {
        return this.rows.find((row) => String(this.rowId(row)) === String(id)) || null;
    }

    rowId(row) {
        if (typeof this.options.rowId === 'function') {
            return String(this.options.rowId(row) ?? '');
        }

        return String(row?.id ?? '');
    }

    searchTerm() {
        return String(this.search?.value ?? '').trim();
    }

    label(key) {
        return String(this.config.labels?.[key] || key);
    }

    columnLabel(column) {
        return column.label || this.label(column.labelKey || column.key || '');
    }

    renderState(message) {
        this.frame.style.overflowX = 'hidden';
        this.frame.innerHTML = `<div class="${dataGridClasses.state}">${escapeHtml(message)}</div>`;
    }
}

export function defaultCell(row, column, grid) {
    const value = valueByPath(row, column.key);

    if (column.type === 'title') {
        const title = String(value || column.fallback || 'Untitled');
        const subtitle = column.subtitle ? valueByPath(row, column.subtitle) : '';
        return `<div class="font-semibold text-slate-950 dark:text-white">${escapeHtml(title)}</div>${
            subtitle ? `<div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">${escapeHtml(subtitle)}</div>` : ''
        }`;
    }

    if (column.type === 'code') {
        return `<code class="rounded bg-slate-900/5 px-2 py-1 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-200">${escapeHtml(value || column.fallback || '-')}</code>`;
    }

    if (column.type === 'badge') {
        return `<span class="rounded-md bg-slate-900/5 px-2 py-1 text-xs font-bold uppercase text-slate-700 dark:bg-white/10 dark:text-slate-200">${escapeHtml(value || column.fallback || '-')}</span>`;
    }

    if (column.type === 'status') {
        return statusChip(value, column.tones || grid.config.statusTones || {});
    }

    if (column.type === 'date') {
        return escapeHtml(formatDate(value));
    }

    if (column.type === 'actions') {
        return actionsCell(row, column, grid);
    }

    return escapeHtml(value || column.fallback || '-');
}

export function actionsCell(row, column, grid) {
    const actions = (Array.isArray(column.actions) ? column.actions : [])
        .filter((action) => action && action.visible !== false)
        .map((action) => ({
            ...action,
            label: action.label || grid.label(action.labelKey || action.action || ''),
            href: action.href ? templateUrl(action.href, row) : '',
            attributes: [
                ...(Array.isArray(action.attributes) ? action.attributes : []),
            ],
        }));

    return actionMenuMarkup({
        label: grid.label(column.labelKey || 'actions'),
        actions,
    });
}

export function statusChip(status, tones = {}) {
    const value = String(status || '-').trim().toLowerCase();
    const label = value === '-' ? '-' : value.charAt(0).toUpperCase() + value.slice(1);
    const classes = tones[value] || {
        draft: 'bg-slate-900/5 text-slate-600 dark:bg-white/10 dark:text-slate-300',
        review: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        published: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        scheduled: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        declined: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
        archived: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    }[value] || 'bg-slate-900/5 text-slate-600 dark:bg-white/10 dark:text-slate-300';

    return `<span class="inline-flex rounded-md px-2 py-1 text-xs font-bold ${classes}">${escapeHtml(label)}</span>`;
}

export function formatDate(value) {
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

export function templateUrl(template, row) {
    return String(template || '#').replace(/\{([a-zA-Z0-9_.-]+)\}/g, (_, key) => encodeURIComponent(String(valueByPath(row, key) ?? '')));
}

export function valueByPath(row, path) {
    if (!path) {
        return '';
    }

    return String(path).split('.').reduce((value, key) => value?.[key], row);
}

export function compareSortValues(left, right) {
    if (typeof left === 'number' && typeof right === 'number') {
        return left === right ? 0 : (left < right ? -1 : 1);
    }

    return String(left).localeCompare(String(right), 'de', {
        numeric: true,
        sensitivity: 'base',
    });
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function escapeAttr(value) {
    return escapeHtml(value);
}

function parseConfig(root) {
    try {
        return JSON.parse(root.getAttribute('data-config') || '{}');
    } catch (error) {
        return {};
    }
}
