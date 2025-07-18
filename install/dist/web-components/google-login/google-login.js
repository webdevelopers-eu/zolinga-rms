import api from "/dist/system/js/api.js";
import WebComponent  from "/dist/system/js/web-component.js";

/**
 * This is the Google login button.
 * 
 * @author Daniel Ševčík <danny@zolinga.net>
 * @since 2024-04-22
 */
export default class GoogleLogin extends WebComponent {
    button;
    clientId;
    #root;

    constructor() {
        super();
        this.scriptApi = "https://accounts.google.com/gsi/client";
        this.ready(this.#init());
    }

    async #init() {
        this.clientId = (await api.dispatchEvent("google:get", {})).response.clientId;

        if (!this.clientId) {
            console.log("FederatedLogin: GoogleLogin: No client ID provided.");
            this.setAttribute("hidden", "");
            return;
        }

        this.#root = await this.loadContent(import.meta.url.replace(".js", ".html"), {"mode": "closed", "allowScripts": "true"});
        this.button = this.#root.querySelector(".g_id_signin");    

        await this.#loadApi();
    }

    async #callback(response) {
        console.log('Google: callback', response);
        const event = await api.dispatchEvent("google:login", {"jwt": response.credential});

        this.broadcast("message", {
            "message": event.message, 
            "type": event.ok ? "success" : "error", 
            "id": 'login-box-message',
            "timeout": event.ok ? 5000 : 20000,
            "tags": event.response.user.tags
        }, true); 
        this.broadcast("rms:login-changed", {
            "loggedIn": event.ok,
            "tags": event.response.user.tags
        });
    }

    async #loadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.async = true;
            script.src = url;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    // see https://developers.google.com/identity/gsi/web/guides/display-button#javascript_2
    async #loadApi() {
        const preferedLang = (document.documentElement?.lang?.substr(0, 2) || "en");
        await this.#loadScript(this.scriptApi + (preferedLang == "en" ? "" : "?hl=" + preferedLang));

        if (!google?.accounts?.oauth2?.initCodeClient) {
            console.error("FederatedLogin: GoogleLogin: Google API not loaded.");
            return;
        }

        google.accounts.id.initialize({
            client_id: this.clientId,
            callback: this.#callback.bind(this),
            cancel_on_tap_outside: true
        });

        // const width = this.button.clientWidth || this.clientWidth || 256;
        const width = 256;

        // https://developers.google.com/identity/gsi/web/reference/js-reference#google.accounts.id.renderButton
        google.accounts.id.renderButton(
            this.button,
            { theme: "outline", size: "large" , width: width}  // customization attributes
        );
        //google.accounts.id.prompt(); // also display the One Tap dialog
    }
}

