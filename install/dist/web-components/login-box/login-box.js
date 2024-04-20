import WebComponent from '/dist/system/lib/web-component.js';
import api from '/dist/system/api.js';

export default class LoginBox extends WebComponent {
    #root;
    #curtain;

    constructor() {
        super();
        this.ready(this.#init());

        this.listen('rms:login-changed', (resp) => {
            this.setAttribute('layer', resp.loggedIn ? 'minimized' : 'maximized');
            setTimeout(this.#reset.bind(this), resp.loggedIn ? 1000 : 0);
        });
    }

    #reset() {
        this.#root.querySelectorAll('form').forEach(form => form.reset());
    }

    async #init() {
        const contentURL = import.meta.url.replace('login-box.js', 'login-box.html');
        this.#root = await this.loadContent(contentURL, {
            mode: 'closed'
        });

        this.#curtain = this.#root.querySelector('.curtain');

        this.#root.querySelectorAll('form[data-event]')
            .forEach(form => form.addEventListener('submit', this.#submitForm.bind(this)));

        this.#curtain.addEventListener('click', () => this.hasAttribute('click-outside-to-close') && this.setAttribute('layer', 'minimized'));
    }

    async #submitForm(event) {
        event.preventDefault();
        const form = event.target;
        const data = new FormData(form);
        const eventName = form.dataset.event;
        const resp = await api.dispatchEvent(eventName, Object.fromEntries(data.entries()));
        this.#showMessage(resp.message, resp.isOK ? 'success' : 'error', 'login-box-message', 20000);
    }

    #showMessage(message, type = 'info', id = null, timeout = 0) {
        this.broadcast('message', {
            message,
            type,
            id,
            timeout
        }, false);
    }
}