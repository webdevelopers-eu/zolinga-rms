## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('google:login', { jwt: response.credential });
```

## Description

Authenticates a user via Google Sign-In JWT token. If the user doesn't exist yet, a new account is automatically created.

- **Event:** `google:login`
- **Class:** `Zolinga\Rms\Api\Google\GoogleApi`
- **Method:** `onLogin`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `jwt` | `string` | Google Sign-In JWT credential |

## Response

| Field | Type | Description |
|---|---|---|
| `user` | `object` | Public user data on success |
