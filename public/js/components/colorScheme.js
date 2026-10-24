/*
 * Light/dark switching for the admin and login screens.
 *
 * The inline script in views/layouts/admin.phtml and auth.phtml applies the
 * stored scheme before the first paint; this module only changes it later,
 * through the toggle button or the keyboard shortcut, and keeps open tabs in
 * sync.
 *
 * F9 was chosen because Chrome and Safari do not bind it. Edge (Immersive
 * Reader) and Firefox (Reader View) do; the handler cancels the event, so on
 * these screens F9 switches the scheme instead of opening the reader.
 */

export const COLOR_SCHEME_STORAGE_KEY = 'theme';
export const COLOR_SCHEME_SHORTCUT = 'F9';
export const COLOR_SCHEME_EVENT = 'nafcms:color-scheme';

const boundDocuments = new WeakSet();

export const currentColorScheme = (root = document.documentElement) => (
    root.classList.contains('dark') ? 'dark' : 'light'
);

export const applyColorScheme = (scheme, root = document.documentElement) => {
    const resolved = scheme === 'light' ? 'light' : 'dark';
    root.classList.toggle('dark', resolved === 'dark');
    root.style.colorScheme = resolved;

    return resolved;
};

export const setColorScheme = (scheme, root = document.documentElement, storage = window.localStorage) => {
    const resolved = applyColorScheme(scheme, root);

    try {
        storage?.setItem(COLOR_SCHEME_STORAGE_KEY, resolved);
    } catch (error) {}

    const view = root.ownerDocument?.defaultView || window;
    root.dispatchEvent(new view.CustomEvent(COLOR_SCHEME_EVENT, {bubbles: true, detail: {scheme: resolved}}));

    return resolved;
};

export const toggleColorScheme = (root = document.documentElement, storage = window.localStorage) => (
    setColorScheme(currentColorScheme(root) === 'dark' ? 'light' : 'dark', root, storage)
);

/** F9 without modifiers; held keys and IME composition do not repeat the switch. */
export const isColorSchemeShortcut = (event) => (
    event.key === COLOR_SCHEME_SHORTCUT
    && !event.altKey
    && !event.ctrlKey
    && !event.metaKey
    && !event.shiftKey
    && !event.repeat
    && !event.isComposing
);

export const initColorScheme = (doc = document) => {
    if (boundDocuments.has(doc)) {
        return;
    }

    boundDocuments.add(doc);
    const root = doc.documentElement;
    const view = doc.defaultView || window;

    doc.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || !isColorSchemeShortcut(event)) {
            return;
        }

        event.preventDefault();
        toggleColorScheme(root, view.localStorage);
    });

    doc.addEventListener('click', (event) => {
        const toggle = event.target instanceof view.Element
            ? event.target.closest('[data-color-scheme-toggle]')
            : null;

        if (toggle) {
            toggleColorScheme(root, view.localStorage);
        }
    });

    const systemScheme = view.matchMedia?.('(prefers-color-scheme: dark)');
    systemScheme?.addEventListener('change', (event) => {
        let storedTheme = null;
        try {
            storedTheme = view.localStorage.getItem(COLOR_SCHEME_STORAGE_KEY);
        } catch (error) {}
        if (storedTheme === null) {
            applyColorScheme(event.matches ? 'dark' : 'light', root);
        }
    });

    // Another tab switched: follow it without writing back.
    view.addEventListener('storage', (event) => {
        if (event.key === COLOR_SCHEME_STORAGE_KEY && (event.newValue === 'light' || event.newValue === 'dark')) {
            applyColorScheme(event.newValue, root);
        }
    });
};
