import {getNotifyConfig, mergeNotifyOptions} from "./Config.js";

const modalStack = [];
let overlay = null;
let previousBodyOverflow = null;

function cssVariable(name) {
    const candidates = [
        document.documentElement,
        document.body,
        document.querySelector(`[style*="${name}"]`),
        document.querySelector('.admin-main-shell'),
    ].filter(Boolean);

    for (const element of candidates) {
        const value = getComputedStyle(element).getPropertyValue(name).trim();
        if (value) {
            return value;
        }
    }

    return '';
}

function sanitizeToken(value, fallback = 'pop') {
    const token = String(value || fallback).trim().toLowerCase();
    return /^[a-z0-9_-]+$/.test(token) ? token : fallback;
}

function currentTopModal() {
    return modalStack[modalStack.length - 1] || null;
}

function ensureOverlay() {
    if (overlay instanceof HTMLDivElement) {
        return overlay;
    }

    overlay = document.createElement('div');
    overlay.className = 'notify-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.appendChild(overlay);

    overlay.addEventListener('click', () => {
        const modal = currentTopModal();
        if (!modal || !modal.options.overlay.closeOnClick) {
            return;
        }

        modal.promise?.resolve(false);
        modal.remove();
    });

    return overlay;
}

function syncOverlay() {
    if (!(overlay instanceof HTMLDivElement)) {
        return;
    }

    const modal = currentTopModal();
    if (!modal) {
        let removed = false;
        const removeOverlay = () => {
            if (removed) {
                return;
            }

            removed = true;
            if (modalStack.length === 0 && overlay?.parentNode) {
                overlay.parentNode.removeChild(overlay);
                overlay = null;
            }
        };

        overlay.classList.remove('is-visible');
        overlay.addEventListener('transitionend', removeOverlay, {once: true});
        window.setTimeout(removeOverlay, 240);
        return;
    }

    const overlayOptions = modal.options.overlay;
    overlay.className = `notify-overlay notify-overlay-${sanitizeToken(overlayOptions.mode, 'darken')} is-visible`;
    overlay.style.setProperty('--notify-overlay-opacity', String(overlayOptions.opacity ?? 0.56));
    overlay.style.setProperty('--notify-overlay-blur', String(overlayOptions.blur || '8px'));
}

export default class Notify {

    defaultOptions = {
        style: {},
    };

    constructor(message, options, promise = null) {
        this.message   = message;
        this.options   = mergeNotifyOptions(getNotifyConfig(), this.defaultOptions, options || {});
        this.promise   = promise;
        this.container = null;
        this.animation = sanitizeToken(this.options.animation);
        this.isClosing = false;
        this.escapeHandler = null;

        this.render();
        this.decorate();
        this.applyTheme();
        this.mountOverlay();
        Object.assign(this.container.style, this.options.style || {});
        this.bindEscape();
        this.bind();
    }

    decorate() {
        if (!(this.container instanceof HTMLElement)) {
            return;
        }

        this.container.classList.add(`notify-animation-${this.animation}`);
        this.container.setAttribute('role', this.options.role || 'dialog');
        this.container.setAttribute('aria-modal', this.options.overlay?.enabled === false ? 'false' : 'true');
    }

    applyTheme() {
        if (!(this.container instanceof HTMLElement)) {
            return;
        }

        const theme = this.options.theme || {};
        const accent = theme.accent
            || this.options.accentColor
            || cssVariable('--notify-accent')
            || cssVariable('--admin-page-accent')
            || cssVariable('--accent')
            || '#ff3b30';

        const surface = theme.surface
            || cssVariable('--notify-surface')
            || cssVariable('--panel')
            || '#29303c';

        const text = theme.text
            || cssVariable('--notify-text')
            || cssVariable('--text')
            || '#ffffff';

        const muted = theme.muted
            || cssVariable('--notify-muted')
            || cssVariable('--muted')
            || 'rgba(255, 255, 255, .68)';

        const border = theme.border
            || cssVariable('--notify-border')
            || cssVariable('--border')
            || 'rgba(255, 255, 255, .14)';

        this.container.style.setProperty('--notify-accent', accent);
        this.container.style.setProperty('--notify-surface', surface);
        this.container.style.setProperty('--notify-text', text);
        this.container.style.setProperty('--notify-muted', muted);
        this.container.style.setProperty('--notify-border', border);
    }

    mountOverlay() {
        if (this.options.overlay?.enabled === false) {
            return;
        }

        if (!modalStack.includes(this)) {
            modalStack.push(this);
        }

        ensureOverlay();

        if (this.options.lockScroll !== false && modalStack.length === 1) {
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        }

        document.body.classList.add('notify-modal-open');
        syncOverlay();
    }

    releaseOverlay() {
        const index = modalStack.indexOf(this);
        if (index !== -1) {
            modalStack.splice(index, 1);
        }

        if (modalStack.length === 0) {
            document.body.classList.remove('notify-modal-open');
            document.body.style.overflow = previousBodyOverflow || '';
            previousBodyOverflow = null;
        }

        syncOverlay();
    }

    bindEscape() {
        if (this.options.overlay?.enabled === false || this.options.closeOnEscape === false) {
            return;
        }

        this.escapeHandler = (event) => {
            if (event.key !== 'Escape' || currentTopModal() !== this) {
                return;
            }

            event.preventDefault();
            this.promise?.resolve(false);
            this.remove();
        };

        document.addEventListener('keydown', this.escapeHandler);
    }

    unbindEscape() {
        if (this.escapeHandler) {
            document.removeEventListener('keydown', this.escapeHandler);
            this.escapeHandler = null;
        }
    }

    animateRemove(container = this.container) {
        if (this.isClosing) {
            return;
        }

        this.isClosing = true;
        this.unbindEscape();

        let finished = false;
        const finish = () => {
            if (finished) {
                return;
            }

            finished = true;
            if (container?.parentNode) {
                container.parentNode.removeChild(container);
            }
            this.releaseOverlay();
        };

        if (!(container instanceof HTMLElement)) {
            finish();
            return;
        }

        container.classList.add('closing');
        container.addEventListener('animationend', finish, {once: true});
        window.setTimeout(finish, 520);
    }

    bind() {
        throw new Error('May be overridden by sub classes.');
    }

    render() {
        throw new Error('May be overridden by sub classes.');
    }

    remove() {
        throw new Error('May be overridden by sub classes.');
    }

};
