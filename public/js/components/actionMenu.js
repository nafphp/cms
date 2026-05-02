const menuSelector = '.admin-action-menu';
const panelSelector = '.admin-action-menu-panel';
const summarySelector = '.admin-action-menu > summary';
const panelHiddenClasses = ['pointer-events-none', 'opacity-0', '-translate-y-1', 'scale-[.98]'];
const panelVisibleClasses = ['pointer-events-auto', 'opacity-100', 'translate-y-0', 'scale-100'];

const initializedRoots = new WeakSet();

export const actionMenuClass = 'admin-action-menu relative z-[90] inline-block cursor-pointer open:z-[1000]';
export const actionSummaryClass = 'grid h-[34px] w-[34px] cursor-pointer list-none place-items-center rounded-md border border-slate-900/10 bg-slate-900/[.055] text-slate-700 shadow-[inset_0_1px_0_rgba(255,255,255,.45)] transition hover:border-sky-400/45 hover:bg-sky-500/10 hover:text-sky-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-400 [&::-webkit-details-marker]:hidden dark:border-slate-400/15 dark:bg-slate-500/35 dark:text-white dark:shadow-[inset_0_1px_0_rgba(255,255,255,.08)] dark:hover:border-sky-300/45 dark:hover:bg-sky-400/15';
export const actionPanelClass = 'admin-action-menu-panel pointer-events-none fixed z-[1000] grid min-w-[188px] -translate-y-1 scale-[.98] gap-1 rounded-lg border border-[var(--border)] bg-white/95 p-1.5 opacity-0 shadow-[0_18px_44px_rgba(15,23,42,.16)] backdrop-blur transition duration-150 dark:bg-slate-900/96 dark:shadow-[0_20px_70px_rgba(0,0,0,.42)]';
export const actionButtonClass = 'admin-action-menu-item flex min-h-9 w-full items-center gap-2 rounded-md px-2.5 text-left text-sm font-semibold text-[var(--text)] transition hover:bg-surface-soft focus-visible:bg-surface-soft focus-visible:outline-none';
export const actionIconClass = 'grid h-6 w-6 shrink-0 place-items-center rounded-md border border-slate-900/10 bg-slate-900/[.045] text-[11px] text-slate-500 dark:border-slate-400/15 dark:bg-white/[.045] dark:text-slate-300';
export const actionDangerClass = 'text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40';

export function actionMenuMarkup({label = 'Actions', actions = []} = {}) {
    return `
        <details class="${actionMenuClass}" data-admin-action-menu>
            <summary class="${actionSummaryClass}" aria-label="${escapeAttr(label)}">
                <span class="icon-dots-horizontal-triple text-[15px]" aria-hidden="true"></span>
            </summary>
            <div class="${actionPanelClass}">
                ${actions.map((action) => actionMenuItemMarkup(action)).join('')}
            </div>
        </details>
    `;
}

export function setupActionMenus(root = document) {
    if (initializedRoots.has(root)) {
        return;
    }

    initializedRoots.add(root);
    root.addEventListener('click', (event) => handleSummaryClick(root, event), true);
    root.addEventListener('click', (event) => handleItemClick(root, event));
    root.addEventListener('toggle', (event) => handleToggle(root, event), true);
    root.addEventListener('keydown', (event) => handleKeydown(root, event));

    document.addEventListener('click', (event) => closeOutside(root, event));
    window.addEventListener('scroll', () => syncOpenMenus(root), true);
    window.addEventListener('resize', () => syncOpenMenus(root));
}

function actionMenuItemMarkup(action) {
    const label = String(action.label || '');
    if (!label) {
        return '';
    }

    const actionName = escapeAttr(action.action || '');
    const danger = action.danger ? actionDangerClass : '';
    const iconClass = actionIcon(action.icon || action.action || '');
    const icon = iconClass ? `<span class="${escapeAttr(iconClass)}"></span>` : '';
    const attrs = [
        'data-admin-action-menu-item',
        actionName ? `data-grid-action="${actionName}"` : '',
        ...(Array.isArray(action.attributes) ? action.attributes : []),
    ].filter(Boolean).join(' ');
    const href = String(action.href || '').trim();
    const tag = href !== '' ? 'a' : 'button';
    const type = href === '' ? ' type="button"' : '';
    const hrefAttribute = href !== '' ? ` href="${escapeAttr(href)}"` : '';

    return `
        <${tag} class="${actionButtonClass} ${danger}"${type}${hrefAttribute} ${attrs}>
            <span class="${actionIconClass}" aria-hidden="true">${icon}</span>
            <span class="min-w-0 flex-1 truncate">${escapeHtml(label)}</span>
        </${tag}>
    `;
}

export function actionIcon(value) {
    const name = String(value || '').trim();
    const aliases = {
        'icon-check': 'icon-checkmark',
        'icon-pencil': 'icon-file-empty',
        'icon-list': 'icon-file-empty',
        check: 'icon-checkmark',
        publish: 'icon-circle-right',
        preview: 'icon-circle-right',
        edit: 'icon-file-empty',
        pencil: 'icon-file-empty',
        delete: 'icon-cross',
        remove: 'icon-cross',
        version: 'icon-file-empty',
        submissions: 'icon-list',
        translation: 'icon-earth',
        translate: 'icon-earth',
    };

    if (name.startsWith('icon-')) {
        return aliases[name] || name;
    }

    if (name.includes('delete') || name.includes('remove')) {
        return aliases.delete;
    }
    if (name.includes('publish') || name.includes('preview')) {
        return aliases.publish;
    }
    if (name.includes('translation') || name.includes('translate')) {
        return aliases.translation;
    }
    if (name.includes('version')) {
        return aliases.version;
    }

    return aliases[name] || 'icon-checkmark';
}

function handleItemClick(root, event) {
    const item = event.target.closest?.('[data-admin-action-menu-item]');
    if (!item || item.hasAttribute('disabled') || !root.contains(item)) {
        return;
    }

    const menu = item.closest(menuSelector);
    if (menu) {
        requestAnimationFrame(() => closeActionMenu(menu));
    }
}

function handleSummaryClick(root, event) {
    const summary = event.target.closest?.(summarySelector);
    if (!summary || !root.contains(summary)) {
        return;
    }

    const menu = summary.parentElement;
    if (!(menu instanceof HTMLDetailsElement)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (menu.open) {
        closeActionMenu(menu);
        return;
    }

    closeOpenMenus(root, menu);
    menu.classList.remove('is-positioned');
    menu.open = true;
    positionActionMenu(menu);
    revealActionMenu(menu);
}

function handleToggle(root, event) {
    const menu = event.target.closest?.(menuSelector);
    if (!menu || !root.contains(menu)) {
        return;
    }

    if (!menu.open) {
        menu.classList.remove('is-positioned');
        return;
    }

    closeOpenMenus(root, menu);
    positionActionMenu(menu);
    revealActionMenu(menu);
}

function handleKeydown(root, event) {
    if (event.key !== 'Escape') {
        return;
    }

    const menu = event.target.closest?.(`${menuSelector}[open]`);
    if (!menu || !root.contains(menu)) {
        return;
    }

    closeActionMenu(menu);
    menu.querySelector('summary')?.focus();
}

function closeOutside(root, event) {
    if (event.target.closest?.(menuSelector)) {
        return;
    }

    closeOpenMenus(root);
}

function closeOpenMenus(root, except = null) {
    root.querySelectorAll(`${menuSelector}[open]`).forEach((menu) => {
        if (menu !== except) {
            closeActionMenu(menu);
        }
    });
}

function syncOpenMenus(root) {
    root.querySelectorAll(`${menuSelector}[open]`).forEach((menu) => {
        positionActionMenu(menu);
        revealActionMenu(menu);
    });
}

function positionActionMenu(menu) {
    const summary = menu.querySelector('summary');
    const panel = menu.querySelector(panelSelector);
    if (!summary || !panel) {
        return;
    }

    const rect = summary.getBoundingClientRect();
    const width = Math.max(panel.offsetWidth || 204, 188);
    const left = Math.max(8, Math.min(window.innerWidth - width - 8, rect.right - width));
    const top = Math.min(window.innerHeight - 8, rect.bottom + 6);

    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
}

function revealActionMenu(menu) {
    requestAnimationFrame(() => {
        if (menu.open) {
            menu.classList.add('is-positioned');
            const panel = menu.querySelector(panelSelector);
            panel?.classList.remove(...panelHiddenClasses);
            panel?.classList.add(...panelVisibleClasses);
        }
    });
}

function closeActionMenu(menu) {
    menu.classList.remove('is-positioned');
    const panel = menu.querySelector(panelSelector);
    panel?.classList.remove(...panelVisibleClasses);
    panel?.classList.add(...panelHiddenClasses);
    menu.open = false;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}
