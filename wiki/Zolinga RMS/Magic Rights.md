# Magic Rights

There are following rights that are always granted to specific users:

- `member of guests` - granted to all users that are not logged in
- `member of users` - granted to all users that are logged in

These two are resolved purely by code in `User::filterRights()` - no database record is needed or created for them.

## Important Distinction

`member of administrators` is **NOT** a magic right. Despite the similar name, it is an ordinary database-backed right:

- It must be explicitly granted to a user, which inserts a row into the `rmsRights` table.
- `User::isAdministrator()` simply checks `hasRight('member of administrators')`, which performs a real `rmsRights` DB lookup.
- A user without that DB row gets HTTP 403 on listeners protected with `"right": "member of administrators"` - even if the username suggests admin privileges.

## Granting Rights

The easiest way to grant or revoke a right is the CLI:

```bash
bin/zolinga rms:user --user="user@example.com" --grant="member of administrators"
```

See [Zolinga RMS CLI](:Zolinga RMS:CLI) for all options (`--grant`, `--revoke`, `--hasRight`, `--list`).

Programmatically the same is done via `$user->grant('member of administrators')` (or `grantAdministrator()`), which inserts a row into the `rmsRights` table.

## How Rights Are Resolved

Authorization flow for a listener with `"right": "..."`:

1. Core dispatches the `system:authorize` event before running the listener.
2. `UserService::onAuthorize()` (the RMS authorization provider) calls `User::filterRights()` with the required rights.
3. `filterRights()` grants a right if:
    - a matching `commandHash` exists in `rmsRights` for the user, **or**
    - the right is `member of users` and the user is logged in, **or**
    - the right is `member of guests` and the user is not logged in.
4. If none match, the listener is skipped and the event gets `UNAUTHORIZED` (HTTP 401/403).

## Storage

- `rmsCommands` - unique rights registry: `hash` (binary sha1 of the right text) and `command` (the right text).
- `rmsRights` - user-to-right assignments: `userId` + `commandHash`.

Granting a right that does not exist yet in `rmsCommands` creates it automatically (`Command::create()`).

