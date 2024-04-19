import api from '/dist/system/api.js';

function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) return parts.pop().split(";").shift();
}

function updateLoginState(loginState) {
  if (loginState === lastLoginState) {
    return;
  }
  console.log('RMS: Login state changed from %s to %s', lastLoginState, loginState);
  lastLoginState = loginState;
  api.broadcast('rms:login-changed', { loggedIn: loginState });
  document.documentElement.classList.toggle('rms-logged-in', loginState);
  document.documentElement.classList.toggle('rms-logged-out', !loginState);
}

let lastLoginState = getCookie('rmsIn') === '1';

setInterval(() => updateLoginState(getCookie('rmsIn') === '1'), 5000);

api
  .listen('event-response:rms:login', (resp) => {
    document.cookie = 'rmsIn=' + (resp.ok ? '1' : '0');
    updateLoginState(resp.ok);
  })
  .listen('event-response:rms:logout', (resp) => {
    document.cookie = 'rmsIn=0';
    updateLoginState(false);
  })
  .listen('rms:login-changed', (data) => {
    document.cookie = 'rmsIn=' + (data.loggedIn ? '1' : '0');
    updateLoginState(data.loggedIn);
  });