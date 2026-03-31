## Syntax

```javascript
// Front-end (remote origin, requires "member of users" right)
const resp = await api.dispatchEvent('rms:settings', { op: 'get' });
const resp = await api.dispatchEvent('rms:settings', { op: 'set', currentPassword: '...', password: '...', confirmPassword: '...', username: '...' });
```

## Description

User account settings API — allows reading and updating the user's login credentials (email, password).

- **Event:** `rms:settings`
- **Class:** `Zolinga\Rms\Api\SettingsApi`
- **Method:** `onSettings`
- **Origin:** `remote`
- **Right:** `member of users`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Operations

### `get`

Retrieves the user's current settings (username, etc.).

**Response:** `data` — User settings object.

### `set`

Updates the user's credentials. Requires current password for verification.

**Parameters:**

| Field | Type | Description |
|---|---|---|
| `currentPassword` | `string` | Current password for verification |
| `password` | `string` | New password (optional) |
| `confirmPassword` | `string` | New password confirmation |
| `username` | `string` | New email/username (optional) |
