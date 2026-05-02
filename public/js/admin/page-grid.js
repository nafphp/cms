import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';
import {actionMenuMarkup} from '../components/actionMenu.js';
import {AdminDataGrid} from '../components/dataGrid.js';

class PageGrid {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config') || '{}');
        this.grid = new AdminDataGrid(root, {
            frameSelector: '[data-page-grid-frame], [data-admin-grid-frame]',
            searchSelector: '[data-page-search], [data-admin-grid-search]',
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
            return this.actionMenu(row);
        }

        return undefined;
    }

    actionMenu(row) {
        const id = escapeAttr(row.id);

        return actionMenuMarkup({
            label: this.label('actions'),
            actions: [
                ...(this.canPreview(row) ? [{
                    action: 'preview',
                    label: this.label('preview'),
                    attributes: [`data-page-id="${id}"`],
                    icon: 'icon-circle-right',
                }] : []),
                {
                    action: 'edit',
                    label: this.label('edit'),
                    attributes: [`data-page-id="${id}"`],
                    icon: 'icon-pencil',
                },
                {
                    action: 'delete',
                    label: this.label('delete'),
                    danger: true,
                    attributes: [`data-page-id="${id}"`],
                    icon: 'icon-cross',
                },
            ],
        });
    }

    async handleAction(target) {
        const row = this.grid.rowById(target.dataset.pageId);
        if (!row) {
            return;
        }

        if (target.dataset.gridAction === 'edit') {
            window.location.href = this.url('edit', row);
            return;
        }

        if (target.dataset.gridAction === 'preview') {
            window.location.href = this.previewUrl(row);
            return;
        }

        if (target.dataset.gridAction === 'delete') {
            await this.delete(row);
        }
    }

    async delete(row) {
        if (!await this.confirm(this.label('deleteConfirm'), this.label('delete'))) {
            return;
        }

        const response = await (window.NixCMS?.fetch || fetch)(this.url('delete', row), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': String(window.NixCMS?.csrfToken?.() || ''),
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

    async confirm(message, confirmButtonText) {
        return await prompt(`<div class="my-5">${escapeHtml(message)}</div>`, {
            confirmButtonText,
            cancelButtonText: this.label('cancel'),
            showCancelButton: true,
        });
    }

    url(key, row) {
        return templateUrl(this.config.urls?.[key] || '#', row);
    }

    label(key) {
        return String(this.config.labels?.[key] || key);
    }

    canPreview(row) {
        return this.config.permissions?.canPreview === true && String(row.path || '').trim() !== '';
    }

    previewUrl(row) {
        const path = String(row.path || '/').trim();
        return path.startsWith('/') ? path : `/${path}`;
    }
}

function templateUrl(template, row) {
    return String(template || '#').replace(/\{([a-zA-Z0-9_.-]+)\}/g, (_, key) => encodeURIComponent(String(valueByPath(row, key) ?? '')));
}

function valueByPath(row, path) {
    return String(path || '').split('.').reduce((value, key) => value?.[key], row);
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

document.querySelectorAll('[data-page-grid]').forEach((root) => {
    new PageGrid(root).init();
});
