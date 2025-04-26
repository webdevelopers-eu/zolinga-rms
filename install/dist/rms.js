import api from '/dist/system/js/api.js';
import WebComponent from '/dist/system/js/web-component.js';

let lastLoginState = getCookie('rmsIn') === '1';
updateTags(lastLoginState ? null : ['logged-out']);

function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) {
    return parts.pop().split(";").shift();
  } else if (parts.length > 2) {
    console.warn('RMS: Multiple cookies with the same name found: %s. Cookies: %s', name, value);
  } else {
    return null;
  }
}

function updateTags(tags) {
  tags = tags || JSON.parse(sessionStorage.getItem('rms-tags') || '["empty"]');
  sessionStorage.setItem('rms-tags', JSON.stringify(tags));
  document.documentElement.dataset.userTags = tags.join(' ');
}

function updateLoginState(loginState, data) {
  updateTags(loginState ? data.tags : ['logged-out']);

  if (loginState === lastLoginState) {
    return;
  }
  console.log('RMS: Login state changed from %s to %s', lastLoginState, loginState);
  lastLoginState = loginState;

  api.broadcast('rms:login-changed', {
    ...data || {},
    loggedIn: loginState
  }, true);

  document.documentElement.classList.toggle('rms-logged-in', loginState);
  document.documentElement.classList.toggle('rms-logged-out', !loginState);
}

setInterval(() => {
  const loginState = getCookie('rmsIn') === '1';
  if (lastLoginState != loginState) {
    updateLoginState(loginState, {
       message: 'Your session expired.', 
       type: 'warning', 
       id: 'login-box-message',
       tags: ['session-expired']
      });
  }
}, 2000);

api
  .listen('event-response:rms:logout', (resp) => {
    api.broadcast('message', {
      message: resp.message,
      type: resp.ok ? 'success' : 'error',
      id: 'login-box-message',
      timeout: resp.ok ? 5000 : 20000,
      tags: [...resp.user?.tags || [], 'logout']
    }, true);
    updateLoginState(false, resp);
  })
  .listen('rms:login-changed', (data) => {
    updateLoginState(data.loggedIn, data);
  });

// Password reset request
function checkPasswordReset() {
  const passwordReset = window.location.hash.match(/[&#!]recover=([a-z0-9]+-[a-z0-9]+-[a-z0-9]+)/i);
  if (passwordReset) {
    const hash = passwordReset[1];
    // Try to reuse existing box if possible 
    let login = document.querySelector('login-box[role~="password-reset"]');
    if (!login) {
      login = document.body.appendChild(document.createElement('login-box'));
    }
    login.setAttribute('role', 'password-reset');
    login.setAttribute('layer', 'maximized');
    login.setAttribute('click-outside-to-close', '');
    login.setAttribute('show-card', 'password-reset');
    login.setAttribute('password-reset-hash', hash);
  }
}
checkPasswordReset();
window.addEventListener('hashchange', checkPasswordReset);


// Usefull API
window.rms = new class {
  isLoggedIn() {
    return lastLoginState;
  }

  /**
   * Brings up the login box and when user logs in or is already logged in resolves the returned Promise.
   * @returns {Promise}
   */
  async login() {
    if (this.isLoggedIn()) {
      return Promise.resolve();
    }

    // Is there a login box in <template id="login-box">?
    console.log('RMS: Opening login box - searching for template#loging-box...');
    const template = document.querySelector('template#login-box');
    let box = template ? template.content.firstElementChild.cloneNode(true) : document.createElement('login-box');
    box.setAttribute('style', 'position: fixed;');
    box.setAttribute('layer', 'maximized');
    box.setAttribute('click-outside-to-close', 'true');
    box.setAttribute('remove-on-close', 'true');
    document.body.appendChild(box);
    
    return WebComponent.watchModal(box);
  }

  async logout() {
    return api.dispatchEvent('rms:logout', {});
  }

};