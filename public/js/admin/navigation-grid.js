import prompt from '../lib/notifyJs/Modules/Prompt.js';
import toast from '../lib/notifyJs/Modules/Toast.js';
import {actionMenuMarkup} from '../components/actionMenu.js';
import {AdminDataGrid} from '../components/dataGrid.js';

class NavigationGrid {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config') || '{}');
        this.grid = new AdminDataGrid(root, {
            renderCell: (row, column) => column.key === 'actions' ? this.actionMenu(row) : undefined,
            onAction: async (event, target) => {
                event.preventDefault();
                await this.handleAction(target);
            },
            onRowClick: (id) => {
                window.location.href = this.url('edit', {id});
            },
        });
    }

    async init() {
        await this.grid.init();
    }

    actionMenu(row) {
        const actions = [
            {action: 'edit', label: this.label('edit'), icon: 'icon-pencil'},
        ];
        if (String(row.status || '').toLowerCase() !== 'published') {
            actions.push({action: 'publish', label: this.label('publish'), icon: 'icon-circle-right'});
        }
        actions.push({action: 'delete', label: this.label('delete'), icon: 'icon-cross', danger: true});

        return actionMenuMarkup({
            label: this.label('actions'),
            actions: actions.map((action) => ({
                ...action,
                attributes: [`data-navigation-id="${escapeAttr(row.id)}"`],
            })),
        });
    }

    async handleAction(target) {
        const id = String(target.dataset.navigationId || '');
        const action = target.dataset.gridAction;
        if (!id) {
            return;
        }

        if (action === 'edit') {
            window.location.href = this.url('edit', {id});
            return;
        }

        if (action === 'publish') {
            if (!await prompt(`<div class="my-5">${escapeHtml(this.label('publishConfirm'))}</div>`, {
                confirmButtonText: this.label('publish'),
                cancelButtonText: this.label('cancel'),
                showCancelButton: true,
            })) {
                return;
            }

            await this.post(this.url('publish', {id}));
            toast(this.label('published'));
            window.location.reload();
            return;
        }

        if (action === 'delete') {
            if (!await prompt(`<div class="my-5">${escapeHtml(this.label('deleteConfirm'))}</div>`, {
                confirmButtonText: this.label('delete'),
                cancelButtonText: this.label('cancel'),
                showCancelButton: true,
            })) {
                return;
            }

            const data = await this.post(this.url('delete', {id}));
            if (data?.success === false) {
                return;
            }
            this.grid.rows = this.grid.rows.filter((row) => String(row.id) !== id);
            this.grid.render();
            toast(this.label('deleted'));
        }
    }

    async post(url) {
        const response = await (window.NixCMS?.fetch || fetch)(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': String(window.NixCMS?.csrfToken?.() || this.config.csrfToken || ''),
            },
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || data?.success === false) {
            toast(data?.reason || this.label('error'));
        }
        return data;
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

document.querySelectorAll('[data-navigation-grid]').forEach((root) => {
    new NavigationGrid(root).init();
});
