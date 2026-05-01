import Validator from "../Validator.js";
import NotEmpty from "../Validators/NotEmpty.js";
import toast from "./Toast.js";
import Notify from "../Notify.js";

class Prompt extends Notify {

    defaultOptions = {
        role: 'dialog',
        showInput: false,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        showCancelButton: true,
        confirmCallback: null,
        validators: [
            new NotEmpty()
        ]
    };

    bind() {

        const inputElement  = this.container.querySelector('.input');
        const buttonConfirm = this.container.querySelector('.confirm');
        const buttonCancel  = this.container.querySelector('.cancel');
        const closeButton   = this.container.querySelector('.close');

        this.container.addEventListener('click', (event) => {
            if (!(event.target instanceof Element) || !event.target.closest('.close')) {
                return;
            }

            event.preventDefault();
            this.promise.resolve(false);
            this.remove();
        });

        if (inputElement) {
            inputElement.addEventListener('keydown', (e) => {
                if (e.keyCode === 13) {
                    e.preventDefault();
                    buttonConfirm.click();
                }
            });
        }

        buttonConfirm.addEventListener('click', () => {

            const inputElementValue = (inputElement instanceof HTMLDivElement)
                ? inputElement.textContent
                : null;

            const validatorResult = this.runValidators(inputElementValue, inputElement);

            if (validatorResult.length) {
                inputElement.classList.add('error');
                inputElement.focus();
                const range = document.createRange();
                const sel = window.getSelection();
                if (inputElement.textContent.length) {
                    range.setStart(inputElement.childNodes[0], inputElementValue.length);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                toast(validatorResult);
                return;
            }

            let callbackResult = false;

            if (this.options.confirmCallback instanceof Function) {
                callbackResult = this.options.confirmCallback(this);
            }

            if (callbackResult) {
                return;
            }

            this.promise.resolve(inputElementValue || true);
            this.remove();

        });

        if (buttonCancel instanceof HTMLDivElement) {
            buttonCancel.addEventListener('click', () => {
                this.promise.resolve(false);
                this.remove();
            });
        }

        closeButton?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.promise.resolve(false);
            this.remove();
        });

    }

    runValidators(value) {

        const errors = [];

        if (this.options.showInput && Object.keys(this.options.validators).length) {
            for (let index in this.options.validators) {
                const validator = this.options.validators[index];
                if (validator instanceof Validator) {
                    if (false === validator.validate(value)) {
                        errors.push(validator.errorMessage);
                        return errors;
                    }
                }
            }
        }
        return errors;
    }

    render() {

        const inputElement = (true === this.options.showInput)
            ? `<div class="input" contenteditable="true"></div>`
            : '';

        const cancelButton = (true === this.options.showCancelButton)
            ? `<div class="button cancel">${this.options.cancelButtonText}</div>`
            : '';

        const isSingle = (!cancelButton) ? ' single' : '';

        const content = `
            <div class="notify prompt">
                <button class="close" type="button" aria-label="Close"></button>
                <div class="message">${this.message}</div>
                ${inputElement}
                <div class="container${isSingle}">
                    <div class="button confirm">${this.options.confirmButtonText}</div>
                    ${cancelButton}
                </div>
            </div>`;

        this.container = (new DOMParser()).parseFromString(content, 'text/html').body.firstElementChild;
        document.body.appendChild(this.container);

        const focusFirstField = () => {
            const autofocusTarget = this.container?.querySelector('[autofocus]')
                || this.container?.querySelector('input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [contenteditable="true"], button:not([disabled])');

            if (autofocusTarget instanceof HTMLElement) {
                autofocusTarget.focus({preventScroll: true});
                if (autofocusTarget instanceof HTMLInputElement || autofocusTarget instanceof HTMLTextAreaElement) {
                    autofocusTarget.select();
                }
            }
        };

        requestAnimationFrame(() => {
            focusFirstField();
            window.setTimeout(focusFirstField, 80);
        });

    }

    remove() {
        this.animateRemove();
    }

}

export default function prompt(message, options) {

    return new Promise((resolve, reject) => {
        new Prompt(message, options, {resolve, reject});
    });

}
