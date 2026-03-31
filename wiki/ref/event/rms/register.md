## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('rms:register', {
    username: 'user@example.com',
    password: 'secret',
    password2: 'secret',
    givenName: 'John',
    familyName: 'Doe'
});
```

## Description

Registers a new user account with email, password, and name. On success, the user is automatically logged in.

- **Event:** `rms:register`
- **Class:** `Zolinga\Rms\Api\UserApi`
- **Method:** `onRegister`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `username` | `string` | User email address |
| `password` | `string` | Password |
| `password2` | `string` | Password confirmation |
| `givenName` | `string` | User's first name |
| `familyName` | `string` | User's last name |

## Response

| Field | Type | Description |
|---|---|---|
| `user` | `object` | Public user data on success |
