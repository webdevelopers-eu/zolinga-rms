## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('rms:login', { username: 'user@example.com', password: 'secret', remember: true });
```

## Description

Authenticates a user with username (email) and password. On success, establishes a session and optionally sets a "remember me" cookie.

- **Event:** `rms:login`
- **Class:** `Zolinga\Rms\Api\UserApi`
- **Method:** `onLogin`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `username` | `string` | User email address |
| `password` | `string` | User password |
| `remember` | `bool` | Whether to set a persistent "remember me" cookie |

## Response

| Field | Type | Description |
|---|---|---|
| `user` | `object` | Public user data on success |
