import translate from "../Translator.js";
import Notify from "../Notify.js";
import {mergeNotifyOptions} from "../Config.js";

class Toast extends Notify {

    constructor(message, options, promise = null) {
        super(message, mergeNotifyOptions({
            lockScroll: false,
            role: 'status',
            animation: 'slide',
        }, options || {}, {
            overlay: {
                enabled: false,
            },
            lockScroll: false,
            role: 'status',
        }), promise);
    }

    decorate() {
        this.container.classList.add(`notify-animation-${this.animation}`);
    }

    bind() {
        // Nothing to do
    }

    render() {

        const timeout = this.options.timeout || 3000;

        const translatedMessage = translate(this.message);

        const content = `<div class="toast notify top-right">${translatedMessage}</div>`;
        this.container = (new DOMParser()).parseFromString(content, 'text/html').body.firstElementChild;

        if (this.options.targetContainer instanceof HTMLDivElement) {
            this.options.targetContainer.prepend(this.container);
        } else {
            document.body.appendChild(this.container);
        }

        setTimeout(() => {
            this.remove();
        }, timeout);

    }

    remove() {
        this.animateRemove();

    }

}

export function ensureToastRegion() {
    let wrap = document.querySelector('.notify-toast-container');
    if (!(wrap instanceof HTMLDivElement)) {
        wrap = document.createElement('div');
        wrap.className = 'notify-toast-container';
        wrap.setAttribute('role', 'status');
        wrap.setAttribute('aria-live', 'polite');
        wrap.setAttribute('aria-atomic', 'false');
        document.body.appendChild(wrap);
    }
    return wrap;
}

export default function toast(message, options) {
    const wrap = ensureToastRegion();

    if (typeof message === 'string') {
        return new Toast(message, {...options, targetContainer: wrap});
    }

    for (let i in Object.keys(message)) {

        const item = message[i];

        setTimeout(function() {
            new Toast(item, {...options, targetContainer: wrap});
        }, 50);

    }

};
