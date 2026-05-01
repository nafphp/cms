import Notify from "../Notify.js";

class Alert extends Notify{

    defaultOptions = {
        role: 'alertdialog',
        confirmButtonText: 'Okay'
    };

    bind() {

        const buttonOk = this.container.querySelector('.confirm');

        this.enterHandler = (e) => {

            switch(e.code) {
                case 'Enter':
                    e.preventDefault();
                    this.promise.resolve(true);
                    this.remove();
                    break;
                default:
                    break;
            }

        };

        document.addEventListener('keydown', this.enterHandler);

        buttonOk.addEventListener('click', () => {
            this.promise.resolve(true);
            this.remove();
        });

    };

    render () {

        const content = `
                <div class="notify alert">
                    <div class="message">${this.message}</div>
                    <div class="container single">
                        <div class="button confirm">${this.options.confirmButtonText}</div>
                    </div>
                </div>`;

        this.container = (new DOMParser()).parseFromString(content, 'text/html').body.firstElementChild;

        document.body.appendChild(this.container);

    };

    remove () {
        document.removeEventListener('keydown', this.enterHandler);
        this.animateRemove();
    };

}

export default function alert(message, options) {

    return new Promise((resolve, reject) => {
        new Alert(message, options,{resolve, reject});
    });

}
