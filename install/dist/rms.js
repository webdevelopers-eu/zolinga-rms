import api from '/dist/system/api.js';

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

function updateLoginState(loginState, message, severity) {
  if (loginState === lastLoginState) {
    return;
  }
  console.log('RMS: Login state changed from %s to %s', lastLoginState, loginState);
  lastLoginState = loginState;

  api.broadcast('rms:login-changed', { loggedIn: loginState, message }, true);
  api.broadcast('message', {
    message,
    type: severity || (loginState ? 'success' : 'info'),
    id: 'login-box-message',
    timeout: severity.match(/info|success/) ? 5000 : 30000
  }, false);

  document.documentElement.classList.toggle('rms-logged-in', loginState);
  document.documentElement.classList.toggle('rms-logged-out', !loginState);
}

let lastLoginState = getCookie('rmsIn') === '1';

setInterval(() => updateLoginState(getCookie('rmsIn') === '1', 'Your session expired.', 'warning'), 10000);

api
  .listen('event-response:rms:login', (resp) => {
    updateLoginState(resp.ok, resp.message, resp.ok ? 'success' : 'error');
  })
  .listen('event-response:rms:logout', (resp) => {
    updateLoginState(false, resp.message, resp.ok ? 'success' : 'error');
  })
  .listen('rms:login-changed', (data) => {
    updateLoginState(data.loggedIn, data.message, 'info');
  });