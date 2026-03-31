## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('google:get', {});
// resp.response.clientId → Google OAuth client ID
```

## Description

Retrieves the Google OAuth client ID for rendering the Google Sign-In button.

- **Event:** `google:get`
- **Class:** `Zolinga\Rms\Api\Google\GoogleApi`
- **Method:** `onGet`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

None.

## Response

| Field | Type | Description |
|---|---|---|
| `clientId` | `string` | Google API client ID for sign-in |
