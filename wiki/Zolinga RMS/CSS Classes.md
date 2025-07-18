# RMS CSS Classes

When RMS is installed it will automatically integrate with HTML pages by adding CSS classes `rms-logged-in` and `rms-logged-out` to the `<html>` tag. This allows you to style your pages based on the user's login status.

RMS will also add `rms.js` script to the page that will watch for login and logout events and update the classes accordingly.

Following CSS classes are supported

- `for-users` - will be visible only for logged in users
- `for-guests` - will be visible only for guests
- `for-administrators` - will be visible only for administrators (e.g. users having the right `member of administrators`)
- `for-debuggers` - will be visible only for logged in users using IP that is listed in the config's `debug.allowedIps` setting.

Example:

```html
<div class="for-users">This is visible only for logged in users</div>
<div class="for-guests">This is visible only for guests</div>
<div class="for-administrators">This is visible only for administrators</div>
<div class="for-debuggers">Debugging is ON</div>
```