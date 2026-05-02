import prompt from './lib/notifyJs/Modules/Prompt.js';
import toast from './lib/notifyJs/Modules/Toast.js';
import { initDatePickers } from './components/datePicker.js';
import { initMultiSelects } from './components/multiSelect.js';
import { initStyledSelects } from './components/styledSelect.js';
import { initPageBuilders } from './admin/page-builder.js';

const nativeFetch = window.fetch.bind(window);
const configuredCsrfToken = window.NixCMS?.csrfToken;
const configuredCsrfHeader = window.NixCMS?.csrfHeader || 'X-CSRF-Token';
const flashToastKey = 'nixcms:flash-toast';

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

window.NixCMS = {
    ...(window.NixCMS || {}),
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

if (menu && trigger && layout) {
    const applyLayoutState = () => {
        layout.classList.toggle('is-menu-collapsed', !menu.classList.contains('open'));
    };

    if (sessionStorage.getItem('menu_state') === 'closed') {
        menu.classList.remove('open');
    }

    applyLayoutState();

    trigger.addEventListener('click', () => {
        menu.classList.toggle('open');
        sessionStorage.setItem('menu_state', menu.classList.contains('open') ? 'open' : 'closed');
        applyLayoutState();
    });
}

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
        try {
            localStorage.setItem(storageKey, group.open ? 'open' : 'closed');
        } catch (error) {}
    });
});

document.getElementById('darkmode-toggle')?.addEventListener('click', () => {
    root.classList.toggle('dark');
    const theme = root.classList.contains('dark') ? 'dark' : 'light';
    try {
        localStorage.setItem('theme', theme);
    } catch (error) {}
    root.style.colorScheme = theme;
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

initStyledSelects('select[data-styled-select]');
initMultiSelects('select[data-multiselect]');
initDatePickers('[data-date-picker]');
initPageBuilders();

document.querySelectorAll('[data-open-role-prompt]').forEach((element) => {
    element.addEventListener('click', () => {
        prompt(`
            <div>Create role</div>
            <div class="notify-form">
                <label class="notify-field">
                    <span class="notify-field-label">Role name</span>
                    <input type="text" placeholder="publisher">
                </label>
            </div>
        `, {
            confirmButtonText: 'Create',
            cancelButtonText: 'Cancel',
            showCancelButton: true,
        });
    });
});
