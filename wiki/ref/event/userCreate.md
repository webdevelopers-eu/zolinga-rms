# userCreate

MCP tool for creating a new RMS (Rights Management System) user.

## Description

Creates a new user account. `username` must be a valid e-mail address that is
not already taken. `password` is optional: when omitted the server generates a
random password that is never returned, so the account cannot be logged into
until the owner resets the password. When supplied, the password must be at
least 6 characters.

Requires the `member of administrators` right. Users without it do not see this
tool in `tools/list` and cannot call it.

Optionally the tool sets `canLogin` and `lang`, stores `givenName`/`familyName`
meta data, and grants a list of rights.

## Arguments

| Argument | Type | Required | Description |
|---|---|---|---|
| `username` | string (email) | yes | Login e-mail of the new user. Must not already exist. |
| `password` | string, min 6 | no | Plain-text password. Omit to have a random, unknowable password generated - the user then has to reset it. |
| `canLogin` | boolean | no | Whether the user may log in. Default `true`. |
| `lang` | string `ll_CC` | no | Preferred locale, e.g. `en_US`. |
| `givenName` | string | no | Given (first) name stored as user meta. |
| `familyName` | string | no | Family (last) name stored as user meta. |
| `grant` | array of string | no | Rights to grant, e.g. `["member of administrators"]`. |

## Response

```json
{
  "user": {
    "id": 2042,
    "username": "jane@example.com",
    "canLogin": true,
    "lang": "en_US",
    "created": 1794000000,
    "givenName": "Jane",
    "familyName": "Doe",
    "permissions": []
  }
}
```

## Errors

- `username` missing or empty - `BAD_REQUEST`.
- `username` not a valid e-mail - `BAD_REQUEST`.
- `password` supplied but not a string or empty - `BAD_REQUEST`.
- `password` shorter than 6 characters - `BAD_REQUEST`.
- `grant` not an array of non-empty strings - `BAD_REQUEST`.
- User already exists - `CONFLICT`.

## Examples

Minimal creation with an auto-generated, unknowable password:

```bash
bin/zolinga rms:user --login=admin@example.com mcp@userCreate \
  '{"username": "jane@example.com"}'
```

Creation with an explicit password:

```bash
bin/zolinga rms:user --login=admin@example.com mcp@userCreate \
  '{"username": "jane@example.com", "password": "secret123"}'
```

Create an administrator:

```bash
bin/zolinga rms:user --login=admin@example.com mcp@userCreate \
  '{"username": "jane@example.com", "password": "secret123", \
    "givenName": "Jane", "familyName": "Doe", \
    "grant": ["member of administrators"]}'
```
