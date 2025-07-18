import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';
import api from '/dist/system/js/api.js';

export default class LoginBox extends WebComponent {
    #root;
    #curtain;
    #deck;

    static observedAttributes = [...WebComponent.observedAttributes, 'show-card', 'password-reset-hash'];

    constructor() {
        super();

        this.listen('rms:login-changed', (resp) => {
            if (this.hasAttribute('layer')) {
                this.setAttribute('layer', resp.loggedIn ? 'minimized' : 'maximized');
            }
            setTimeout(this.#reset.bind(this), resp.loggedIn ? 1000 : 0);

            if (resp.loggedIn) {
                this.resolveModal();
            }
        });

        this.ready(this.#init());
    }

    async #init() {
        const contentURL = import.meta.url.replace('login-box.js', 'login-box.html');
        this.#root = await this.loadContent(contentURL, {
            mode: 'closed'
        });

        // Must be in async #init as this is nota allowed in constructor
        if (!this.hasAttribute('layer')) {
            this.classList.add('for-guests');
        }

        this.#curtain = this.#root.querySelector('.curtain');
        this.#deck = this.#root.querySelector('card-deck');

        if (this.hasAttribute('show-card')) {
            this.#deck.setAttribute('show-card', this.getAttribute('show-card'));
        }
        this.#deck.addEventListener('show-card', (event) => this.setAttribute('show-card', event.detail.cardName));
        this.#root.querySelector('input[name="hash"]').value = this.getAttribute('password-reset-hash') || '';

        this.#root.querySelectorAll('form[data-event]')
            .forEach(form => form.addEventListener('submit', this.#submitForm.bind(this)));

        this.#curtain.addEventListener('click', () => {
            if (this.hasAttribute('click-outside-to-close')) {
                this.setAttribute('layer', 'minimized');
                if (this.hasAttribute('remove-on-close')) {
                    setTimeout(() => this.remove(), 2000); // let the animation finish
                }
                this.rejectModal();
            }
        });

        // Wait for c-resources to load before appending the shadow root
        const resources = this.#root.querySelector('c-resources');
        await new Promise(resolve => {
            if (resources.dataset.ready) {
                resolve();
            } else {
                resources.addEventListener('web-component-ready', resolve);
            }
        });
    }

    async #submitForm(event) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        const eventName = form.dataset.event;

        // Check if there are any validation errors in slotted content
        const slotsNames = Array.from(form.querySelectorAll('slot')).map(slot => slot.name);
        const slotted = Array.from(this.querySelectorAll('input, select, textarea'))
            .filter(input => slotsNames.includes(input.closest('[slot]')?.slot));

        const validationError = slotted.find(input => !input.checkValidity());
        if (validationError) {
            validationError.reportValidity();
            return;
        }

        let data = Object.fromEntries(formData.entries());
        data.referrer = window.location.href;
        data.origin = window.location.origin;
        
        // Append slotted values
        slotted.forEach(input => {
            data[input.name] = input.value;
        });

        const resp = await api.dispatchEvent(eventName, data);

        this.broadcast('message', {
            message: resp.message || 'No server response message provided.',
            type: resp.message && resp.ok ? 'success' : 'error',
            id: 'login-box-message',
            timeout: resp.ok ? 5000 : 20000
        }, true);

        if (['rms:login', 'rms:register'].includes(eventName) && resp.ok) {
            this.broadcast('rms:login-changed', { 
                loggedIn: resp.ok,
                tags: resp.response.user.tags 
            });
        }

        if (resp.response.showCard) { // after password reset we may want to go back to login
            this.setAttribute('show-card', resp.response.showCard);
            this.#deck.setAttribute('show-card', resp.response.showCard);
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        super.attributeChangedCallback(name, oldValue, newValue);
        if (name === 'show-card') {
            this.#deck?.setAttribute('show-card', newValue);
        } else if (name === 'password-reset-hash') {
            this.#root.querySelector('input[name="hash"]').value = newValue;
        }
    }

    #reset() {
        this.#root?.querySelectorAll('form').forEach(form => form.reset());
    }
}