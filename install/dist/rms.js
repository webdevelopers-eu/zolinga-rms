function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) return parts.pop().split(";").shift();
}

setInterval(function() {
    const isLoggedIn = getCookie('rmsIn') === '1';
    document.documentElement.classList.toggle('rms-logged-in', isLoggedIn);
    document.documentElement.classList.toggle('rms-logged-out', !isLoggedIn);
}, 1000);
