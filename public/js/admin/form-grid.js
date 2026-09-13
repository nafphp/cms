import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';
import {actionMenuMarkup} from '../components/actionMenu.js';
import {AdminDataGrid} from '../components/dataGrid.js';

class FormGrid {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config') || '{}');
        this.grid = new AdminDataGrid(root, {
            frameSelector: '[data-form-grid-frame], [data-admin-grid-frame]',
            searchSelector: '[data-admin-grid-search]',
            defaultSort: {key: 'title', direction: 'asc'},
            renderCell: (row, column) => this.renderCell(row, column),
            onAction: async (event, target) => {
                event.preventDefault();
                await this.handleAction(target);
            },
        });
    }

    async init() {
        await this.grid.init();
    }

    renderCell(row, column) {
        if (column.key === 'actions') {
            return actionMenuMarkup({
                label: this.label('actions'),
                actions: [
                    {action: 'edit', label: this.label('edit'), attributes: [`data-form-id="${escapeAttr(row.id)}"`], icon: 'icon-pencil'},
                    {action: 'submissions', label: this.label('submissions'), attributes: [`data-form-id="${escapeAttr(row.id)}"`], icon: 'icon-list'},
                    {action: 'delete', label: this.label('delete'), danger: true, attributes: [`data-form-id="${escapeAttr(row.id)}"`], icon: 'icon-cross'},
                ],
            });
        }

        return undefined;
    }

    async handleAction(target) {
        const row = this.grid.rowById(target.dataset.formId);
        if (!row) return;

        if (target.dataset.gridAction === 'edit' || target.dataset.gridAction === 'submissions') {
            window.location.href = this.url(target.dataset.gridAction, row);
            return;
        }

        if (target.dataset.gridAction === 'delete') {
            await this.delete(row);
        }
    }

    async delete(row) {
        if (!await prompt(`<div class="my-5">${escapeHtml(this.label('deleteConfirm'))}</div>`, {
            confirmButtonText: this.label('delete'),
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
        })) {
            return;
        }

        const response = await (window.NafCMS?.fetch || fetch)(this.url('delete', row), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': String(window.NafCMS?.csrfToken?.() || ''),
            },
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || data?.success === false) {
            toast(data?.reason || this.label('error'));
            return;
        }

        this.grid.rows = this.grid.rows.filter((item) => String(item.id) !== String(row.id));
        this.grid.render();
        toast(this.label('deleted'));
    }

    url(key, row) {
        return String(this.config.urls?.[key] || '#').replace(/\{([a-zA-Z0-9_.-]+)\}/g, (_, name) => encodeURIComponent(String(row[name] ?? '')));
    }

    label(key) {
        return String(this.config.labels?.[key] || key);
    }
}

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

document.querySelectorAll('[data-form-grid]').forEach((root) => {
    new FormGrid(root).init();
});
