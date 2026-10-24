import toast, {ensureToastRegion} from './lib/notifyJs/Modules/Toast.js';
import { initColorScheme } from './components/colorScheme.js';
import { initDatePickers } from './components/datePicker.js';
import { initMultiSelects } from './components/multiSelect.js';
import { initStyledSelects } from './components/styledSelect.js';
import { initPageBuilders } from './admin/page-builder.js';

ensureToastRegion();

const nativeFetch = window.fetch.bind(window);
const configuredCsrfToken = window.NafCMS?.csrfToken;
const configuredCsrfHeader = window.NafCMS?.csrfHeader || 'X-CSRF-Token';
const flashToastKey = 'nafcms:flash-toast';

const csrfToken = () => {
    if (typeof configuredCsrfToken === 'function') {
        return String(configuredCsrfToken() || '');
    }

    return String(configuredCsrfToken || document.querySelector('input[name="_csrf"]')?.value || '');
};

const csrfFetch = (input, init = {}) => {
    const headers = new Headers(init.headers || {});
    const token = csrfToken();
    if (token !== '' && !headers.has(configuredCsrfHeader)) {
        headers.set(configuredCsrfHeader, token);
    }

    return nativeFetch(input, {...init, headers});
};

window.NafCMS = {
    ...(window.NafCMS || {}),
    csrfToken,
    fetch: csrfFetch,
    json: (input, init = {}) => csrfFetch(input, {
        ...init,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(init.headers || {}),
        },
    }),
};

const root = document.documentElement;
const menu = document.querySelector('.admin-menu');
const trigger = document.querySelector('.admin-menu-trigger');
const layout = document.querySelector('.admin-layout');
const menuStateKey = 'nafcms:admin-menu-state';

document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.admin-menu a[href]') : null;
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }

    const href = link.getAttribute('href') || '';
    if (
        href === ''
        || href.startsWith('#')
        || link.target !== ''
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
        || event.defaultPrevented
    ) {
        return;
    }

    root.classList.add('admin-menu-navigating');
    document.querySelectorAll('.admin-menu-group.is-flyout-open').forEach((group) => {
        group.classList.remove('is-flyout-open');
    });
}, true);

if (menu && trigger && layout) {
    const applyLayoutState = () => {
        const isCollapsed = !menu.classList.contains('open');
        layout.classList.toggle('is-menu-collapsed', isCollapsed);
        root.classList.toggle('admin-menu-collapsed', isCollapsed);
    };

    try {
        const storedState = localStorage.getItem(menuStateKey) || sessionStorage.getItem('menu_state');
        if (storedState === 'closed') {
            menu.classList.remove('open');
        }
    } catch (error) {}

    applyLayoutState();

    trigger.addEventListener('click', () => {
        menu.classList.toggle('open');
        try {
            localStorage.setItem(menuStateKey, menu.classList.contains('open') ? 'open' : 'closed');
            sessionStorage.removeItem('menu_state');
        } catch (error) {}
        applyLayoutState();
    });
}

const createAdminNavigationOrderStore = () => {
    const userKey = String(window.NafCMS?.adminUserKey || 'guest').replace(/[^a-zA-Z0-9:._-]/g, '_');
    const storageKey = `nafcms:admin-navigation-order:v1:${userKey}`;
    const configuredStore = window.NafCMS?.adminNavigationOrderStore;

    if (
        configuredStore
        && typeof configuredStore.load === 'function'
        && typeof configuredStore.save === 'function'
    ) {
        return configuredStore;
    }

    return {
        load: () => {
            try {
                const data = JSON.parse(localStorage.getItem(storageKey) || 'null');
                return data && typeof data.groups === 'object' && data.groups !== null ? data.groups : {};
            } catch (error) {
                return {};
            }
        },
        save: (groups) => {
            try {
                localStorage.setItem(storageKey, JSON.stringify({
                    version: 1,
                    userKey,
                    groups,
                    updatedAt: new Date().toISOString(),
                }));
            } catch (error) {}
        },
    };
};

const initAdminNavigationSorting = () => {
    const lists = [...document.querySelectorAll('[data-admin-navigation-list]')]
        .filter((list) => list instanceof HTMLElement);

    if (lists.length === 0) {
        return;
    }

    const itemSelector = '[data-admin-navigation-item]';
    const store = createAdminNavigationOrderStore();
    let groups = store.load();
    let draggedItem = null;
    let draggedParent = '';
    let placeholder = null;
    let dragPreview = null;
    let dragMoved = false;
    let draggedPlaceholderHeight = 0;
    let previewFrame = 0;
    let previewX = 0;
    let previewY = 0;

    const parentKey = (list) => String(list.dataset.adminNavigationParent || '');
    const directItems = (list) => [...list.children].filter((element) => (
        element instanceof HTMLElement
        && element.matches(itemSelector)
    ));
    const itemKey = (item) => String(item.dataset.adminNavigationItem || '');
    const listForParent = (key) => lists.find((list) => parentKey(list) === key) || null;
    const orderForList = (list) => directItems(list).map(itemKey).filter((key) => key !== '');
    const animatedChildren = (list) => [...list.children].filter((element) => (
        element instanceof HTMLElement
        && !element.classList.contains('admin-navigation-drag-source')
    ));

    const animateListChange = (list, change, duration = 140) => {
        const previousRects = new Map(animatedChildren(list).map((element) => [element, element.getBoundingClientRect()]));
        change();
        animatedChildren(list).forEach((element) => {
            const previousRect = previousRects.get(element);
            if (!previousRect) {
                return;
            }

            const nextRect = element.getBoundingClientRect();
            const deltaY = previousRect.top - nextRect.top;
            if (Math.abs(deltaY) < 1) {
                return;
            }

            element.animate([
                {transform: `translateY(${deltaY}px)`},
                {transform: 'translateY(0)'},
            ], {
                duration,
                easing: 'cubic-bezier(.2, .8, .2, 1)',
            });
        });
    };

    const createPlaceholder = () => {
        const element = document.createElement('div');
        element.className = 'admin-navigation-placeholder';
        element.setAttribute('aria-hidden', 'true');
        return element;
    };

    const measuredItemHeight = (item) => {
        const rect = item.getBoundingClientRect();
        const style = window.getComputedStyle(item);
        const marginTop = Number.parseFloat(style.marginTop) || 0;
        const marginBottom = Number.parseFloat(style.marginBottom) || 0;
        const measured = Math.max(rect.height, item.offsetHeight, item.scrollHeight);

        return Math.max(34, Math.ceil(measured + marginTop + marginBottom));
    };

    const ensurePlaceholder = () => {
        if (!placeholder) {
            placeholder = createPlaceholder();
        }

        if (draggedPlaceholderHeight > 0) {
            const height = draggedPlaceholderHeight;
            placeholder.style.setProperty('display', 'block', 'important');
            placeholder.style.setProperty('height', `${height}px`, 'important');
            placeholder.style.setProperty('min-height', `${height}px`, 'important');
            placeholder.style.setProperty('max-height', `${height}px`, 'important');
        }

        return placeholder;
    };

    const removeDragPreview = () => {
        dragPreview?.remove();
        dragPreview = null;
    };

    const transparentDragImage = () => {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas;
    };

    const previewTransform = (x, y) => {
        if (!(dragPreview instanceof HTMLElement)) {
            return '';
        }

        const width = Number(dragPreview.dataset.previewWidth || 0);
        const height = Number(dragPreview.dataset.previewHeight || 0);
        return `translate3d(${Math.round(x - (width / 2))}px, ${Math.round(y - (height / 2))}px, 0)`;
    };

    const positionDragPreviewNow = (event) => {
        if (!(dragPreview instanceof HTMLElement) || event.clientX <= 0 || event.clientY <= 0) {
            return;
        }

        previewX = event.clientX;
        previewY = event.clientY;
        dragPreview.style.transform = previewTransform(previewX, previewY);
    };

    const moveDragPreview = (event) => {
        if (!(dragPreview instanceof HTMLElement) || event.clientX <= 0 || event.clientY <= 0) {
            return;
        }

        previewX = event.clientX;
        previewY = event.clientY;

        if (previewFrame !== 0) {
            return;
        }

        previewFrame = window.requestAnimationFrame(() => {
            previewFrame = 0;
            if (!(dragPreview instanceof HTMLElement)) {
                return;
            }

            dragPreview.style.transform = previewTransform(previewX, previewY);
        });
    };

    const applyCenteredDragPreview = (event, item) => {
        removeDragPreview();

        const rect = item.getBoundingClientRect();
        const preview = item.cloneNode(true);
        if (!(preview instanceof HTMLElement)) {
            return;
        }

        preview.classList.remove('admin-navigation-drag-source');
        preview.removeAttribute('aria-grabbed');
        preview.classList.add('admin-navigation-drag-preview');
        preview.style.width = `${Math.round(rect.width)}px`;
        preview.style.height = `${Math.round(rect.height)}px`;
        preview.style.minHeight = `${Math.round(rect.height)}px`;
        preview.dataset.previewWidth = String(Math.round(rect.width));
        preview.dataset.previewHeight = String(Math.round(rect.height));
        preview.style.transform = `translate3d(${Math.round(event.clientX - (rect.width / 2))}px, ${Math.round(event.clientY - (rect.height / 2))}px, 0)`;
        document.body.appendChild(preview);

        dragPreview = preview;
        positionDragPreviewNow(event);

        if (event.dataTransfer) {
            event.dataTransfer.setDragImage(transparentDragImage(), 0, 0);
        }
    };

    const saveListOrder = (list) => {
        groups = {
            ...groups,
            [parentKey(list)]: orderForList(list),
        };
        store.save(groups);
    };

    const applyStoredOrder = () => {
        Object.entries(groups).forEach(([parent, order]) => {
            if (!Array.isArray(order)) {
                return;
            }

            const list = listForParent(parent);
            if (!list) {
                return;
            }

            const items = directItems(list);
            const itemsByKey = new Map(items.map((item) => [itemKey(item), item]));
            const orderedKeys = order.map((key) => String(key));
            const orderedItems = orderedKeys
                .map((key) => itemsByKey.get(key))
                .filter((item) => item instanceof HTMLElement);
            const newItems = items.filter((item) => !orderedKeys.includes(itemKey(item)));

            [...orderedItems, ...newItems].forEach((item) => list.appendChild(item));
        });
    };

    const insertionReference = (list, pointerY) => {
        return directItems(list)
            .filter((item) => item !== draggedItem)
            .find((item) => {
                const box = item.getBoundingClientRect();
                return pointerY < box.top + (box.height / 2);
            }) || null;
    };

    const updatePlaceholder = (list, beforeElement) => {
        const target = ensurePlaceholder();
        if (target.parentElement === list && target.nextElementSibling === beforeElement) {
            return;
        }

        animateListChange(list, () => {
            list.insertBefore(target, beforeElement);
        }, 125);
    };

    const removePlaceholder = () => {
        placeholder?.remove();
        placeholder = null;
    };

    const clearDragState = () => {
        document.body.classList.remove('is-admin-navigation-dragging');
        draggedItem?.removeAttribute('aria-grabbed');
        draggedItem?.classList.remove('admin-navigation-drag-source');
        if (previewFrame !== 0) {
            window.cancelAnimationFrame(previewFrame);
            previewFrame = 0;
        }
        removePlaceholder();
        removeDragPreview();
        draggedItem = null;
        draggedParent = '';
        draggedPlaceholderHeight = 0;
    };

    applyStoredOrder();

    document.addEventListener('dragover', moveDragPreview);

    lists.forEach((list) => {
        list.addEventListener('dragover', (event) => {
            if (!(draggedItem instanceof HTMLElement) || parentKey(list) !== draggedParent) {
                return;
            }

            event.preventDefault();
            moveDragPreview(event);
            const beforeElement = insertionReference(list, event.clientY);
            if (placeholder?.parentElement !== list || placeholder.nextElementSibling !== beforeElement) {
                updatePlaceholder(list, beforeElement);
                dragMoved = true;
            }
        });

        list.addEventListener('drop', (event) => {
            if (!(draggedItem instanceof HTMLElement) || parentKey(list) !== draggedParent) {
                return;
            }

            event.preventDefault();
            animateListChange(list, () => {
                list.insertBefore(draggedItem, placeholder);
                draggedItem.classList.remove('admin-navigation-drag-source');
                removePlaceholder();
            });
            saveListOrder(list);
        });
    });

    document.querySelectorAll(itemSelector).forEach((item) => {
        if (!(item instanceof HTMLElement)) {
            return;
        }

        item.addEventListener('dragstart', (event) => {
            event.stopPropagation();
            draggedItem = item;
            draggedParent = String(item.dataset.adminNavigationParent || '');
            draggedPlaceholderHeight = measuredItemHeight(item);
            dragMoved = false;
            document.body.classList.add('is-admin-navigation-dragging');
            item.parentElement?.insertBefore(ensurePlaceholder(), item);
            item.setAttribute('aria-grabbed', 'true');
            event.dataTransfer?.setData('text/plain', itemKey(item));
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
            }
            applyCenteredDragPreview(event, item);
            item.classList.add('admin-navigation-drag-source');
        });

        item.addEventListener('dragend', (event) => {
            event.stopPropagation();
            const currentList = listForParent(draggedParent);
            if (currentList && dragMoved) {
                saveListOrder(currentList);
            }

            window.setTimeout(() => {
                dragMoved = false;
            }, 0);
            clearDragState();
        });

        item.addEventListener('click', (event) => {
            if (dragMoved) {
                event.preventDefault();
            }
        }, true);
    });
};

initAdminNavigationSorting();

document.querySelectorAll('.admin-menu-group[data-menu-key]').forEach((group) => {
    if (!(group instanceof HTMLDetailsElement)) {
        return;
    }

    const key = group.dataset.menuKey || '';
    if (key === '') {
        return;
    }

    const storageKey = `admin_submenu_${key}`;
    const isActive = group.classList.contains('active') || group.querySelector('.admin-submenu-link.active') !== null;
    let ignoreFlyoutToggle = false;
    let flyoutWasOpen = false;

    const isMenuCollapsed = () => root.classList.contains('admin-menu-collapsed');
    const openFlyout = () => {
        if (!isMenuCollapsed()) {
            return;
        }

        flyoutWasOpen = group.open;
        ignoreFlyoutToggle = true;
        group.open = true;
        group.classList.add('is-flyout-open');
        window.setTimeout(() => {
            ignoreFlyoutToggle = false;
        }, 0);
    };
    const closeFlyout = () => {
        if (!group.classList.contains('is-flyout-open')) {
            return;
        }

        group.classList.remove('is-flyout-open');
        if (!flyoutWasOpen) {
            ignoreFlyoutToggle = true;
            group.open = false;
            window.setTimeout(() => {
                ignoreFlyoutToggle = false;
            }, 0);
        }
    };

    if (isActive) {
        group.open = true;
    } else {
        try {
            const stored = localStorage.getItem(storageKey);
            if (stored === 'open') {
                group.open = true;
            } else if (stored === 'closed') {
                group.open = false;
            }
        } catch (error) {}
    }

    group.addEventListener('toggle', () => {
        if (ignoreFlyoutToggle) {
            return;
        }

        try {
            localStorage.setItem(storageKey, group.open ? 'open' : 'closed');
        } catch (error) {}
    });

    group.addEventListener('mouseenter', openFlyout);
    group.addEventListener('mouseleave', closeFlyout);
    group.addEventListener('focusin', openFlyout);
    group.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (!group.contains(document.activeElement)) {
                closeFlyout();
            }
        }, 0);
    });
});

document.querySelectorAll('[data-toast]').forEach((element) => {
    element.addEventListener('click', () => {
        toast(element.getAttribute('data-toast') || 'Done');
    });
});

try {
    const flashToast = sessionStorage.getItem(flashToastKey);
    if (flashToast) {
        sessionStorage.removeItem(flashToastKey);
        toast(flashToast);
    }
} catch (error) {}

initColorScheme();
initStyledSelects('select[data-styled-select]');
initMultiSelects('select[data-multiselect]');
initDatePickers('[data-date-picker]');
initPageBuilders();
