import prompt from './lib/notifyJs/Modules/Prompt.js';
import toast from './lib/notifyJs/Modules/Toast.js';

const csrfToken = () => {
    const configured = window.NixCMS?.csrfToken;
    if (typeof configured === 'function') {
        return String(configured() || '');
    }

    return String(configured || document.querySelector('input[name="_csrf"]')?.value || '');
};

const csrfFetch = (input, init = {}) => {
    const headers = new Headers(init.headers || {});
    const token = csrfToken();
    if (token !== '' && !headers.has(window.NixCMS?.csrfHeader || 'X-CSRF-Token')) {
        headers.set(window.NixCMS?.csrfHeader || 'X-CSRF-Token', token);
    }

    return fetch(input, {...init, headers});
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

document.getElementById('darkmode-toggle')?.addEventListener('click', () => {
    root.classList.toggle('dark');
    const theme = root.classList.contains('dark') ? 'dark' : 'light';
    localStorage.setItem('theme', theme);
    root.style.colorScheme = theme;
});

document.querySelectorAll('[data-toast]').forEach((element) => {
    element.addEventListener('click', () => {
        toast(element.getAttribute('data-toast') || 'Done');
    });
});

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
