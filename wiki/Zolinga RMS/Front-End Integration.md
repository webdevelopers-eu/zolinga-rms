# RMS Front-End Integration

When RMS is installed it automatically integrates with the front-end of your website by including an `rms.js` file on every page. 

## Object window.rms

This file expose simple `window.rms` object with following methods

- `rms.isLoggedIn()` - returns `true` if user is logged in, `false` otherwise
- `rms.logout()` - logs out the user
- `rms.login():Promise` - opens login modal and returns promise that resolves when user logs in or rejects when user closes modal.

## Classes

The script also sets `rms-logged-in` or `rms-logged-out` class on the `<html>` element depending on the user's login status and adds CSS
that will hide or show elements with following class names based on the user's login status:

- `for-guests` - shown only to users who are not logged in
- `for-users` - shown only to users who are logged in
