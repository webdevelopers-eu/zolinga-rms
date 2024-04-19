import WebComponent from '/dist/system/lib/web-component.js';
import api from '/dist/system/api.js';

export default class LoginBox extends WebComponent {
    #root;
    #messages;
    #curtain;

    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        const contentURL = import.meta.url.replace('login-box.js', 'login-box.html');
        this.#root = await this.loadContent(contentURL, {
            mode: 'closed'
        });

        this.#messages = this.#root.querySelector('.messages');
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
        this.#showMessage(resp.message, resp.isOK ? 'success' : 'error', 'form-message', 20000);

        if (resp.ok) {
            form.reset(); // remove form data
        }

        switch (eventName) {
            case 'rms:login':
                //this.setAttribute('layer', resp.ok ? 'minimized' : 'maximized');
                document.documentElement.classList.toggle('rms-logged-in', resp.ok);
                document.documentElement.classList.toggle('rms-logged-out', !resp.ok);
                break;
        }
    }

    #showMessage(message, type = 'info', id = null, timeout = 0) {
        const msgNode = document.createElement('div');
        msgNode.classList.add('message', type);

        const textNode = msgNode.appendChild(document.createElement('span'));
        textNode.textContent = message;

        const closeNode = msgNode.appendChild(document.createElement('button'));
        closeNode.addEventListener('click', () => msgNode.remove());

        if (id) {
            msgNode.id = id;
            this.#messages.querySelector(`#${id}`)?.remove();
        }

        this.#messages.appendChild(msgNode);

        if (timeout) {
            setTimeout(() => msgNode.remove(), timeout);
        }
    }
}