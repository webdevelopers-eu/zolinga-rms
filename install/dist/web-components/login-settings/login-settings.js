import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';
import zTemplate from '/dist/system/js/z-template.js';
import api from '/dist/system/js/api.js';

export default class LoginSettings extends WebComponent {
    #root;
    #popup;
    #form;

    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        this.#root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
            mode: "closed",
            allowScripts: true,
            inheritStyles: true
        });
        this.#form = this.#root.querySelector('form');
        this.#popup = this.#root.querySelector('popup-container');

        this.#form.addEventListener('submit', async (e) => {
            this.#save();
        });

        this.listen('rms:login-changed', async (status) => {
            if (!status.loggedIn) {
                this.close();
            } else {
                this.#load();
            }
        });
        this.listen('rms:settings-changed', async () => this.#load());
        await this.#load();
    }

    close() {
        this.remove();
    }

    async #save() {
        try {
            const data = Object.fromEntries((new FormData(this.#form)).entries());
            const resp = await api.dispatchEvent('rms:settings', { "op": "set", ...data });
            if (!resp.ok) {
                throw new Error(resp.message);
            }
            this.broadcast('message', { type: 'success', message: resp.message, id: 'rms:settings', timeout: 3000 });
            this.broadcast('rms:settings-changed', {}, true);
            this.#popup.close();
        } catch (e) {
            this.broadcast('message', { type: 'error', message: e.message, id: 'rms:settings', timeout: 10000 });
        }
    }

    async #load() {
        try {
            const resp = await api.dispatchEvent('rms:settings', { "op": "get" });
            if (!resp.ok) {
                throw new Error(resp.message);
            }
            zTemplate(this.#form, resp.response.data);
        } catch (e) {
            this.broadcast('message', { type: 'error', message: e.message });
        }
    }
}