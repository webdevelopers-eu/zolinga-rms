## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('rms:reset', { password: 'newPass', password2: 'newPass', hash: 'recoveryHash' });
```

## Description

Resets the user's password using a recovery hash received via email from `rms:recover`.

- **Event:** `rms:reset`
- **Class:** `Zolinga\Rms\Api\UserApi`
- **Method:** `onReset`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `password` | `string` | New password |
| `password2` | `string` | Password confirmation |
| `hash` | `string` | Recovery hash from the email link |

## Response

| Field | Type | Description |
|---|---|---|
| `showCard` | `string` | UI hint — set to `sign-in` |
