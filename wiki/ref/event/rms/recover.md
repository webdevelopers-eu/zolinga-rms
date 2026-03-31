## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('rms:recover', { username: 'user@example.com', referrer: '/dashboard' });
```

## Description

Initiates password recovery by sending a recovery email with a reset link to the user.

- **Event:** `rms:recover`
- **Class:** `Zolinga\Rms\Api\UserApi`
- **Method:** `onRecover`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `username` | `string` | User email address |
| `referrer` | `string` | URL to redirect to after password reset |

## Response

| Field | Type | Description |
|---|---|---|
| `showCard` | `string` | UI hint — set to `sign-in` |
