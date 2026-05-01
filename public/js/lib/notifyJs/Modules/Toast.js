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

export default function toast(message, options) {

    let wrap = document.querySelector('.notify-toast-container');

    if (!(wrap instanceof HTMLDivElement)) {
        const wrapTemplate = `<div class="notify-toast-container"></div>`;
        wrap = (new DOMParser()).parseFromString(wrapTemplate, 'text/html').body.firstElementChild;
        document.body.appendChild(wrap);
    }

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
