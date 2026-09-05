---
name: zolinga-rms-rights
description: How Zolinga RMS rights work - magic rights (member of users/guests) are code-based, member of administrators is DB-backed. Use when implementing or debugging authorization, rights checks, or testing rights-protected listeners.
argument-hint: "[right-name or topic]"
---

# Zolinga RMS Rights

## Use When

- Adding a `right` to a `zolinga.json` listener and wondering which right to use.
- Debugging why a user gets HTTP 401/403 on a rights-protected listener.
- Testing rights-protected listeners from CLI.
- Granting or revoking rights programmatically.

## The Two Kinds of Rights

### 1. Magic rights (code-based, no DB)

Resolved purely by logic in `User::filterRights()` (`modules/zolinga-rms/src/User.php`):

- `member of users` - automatically granted to every logged-in user.
- `member of guests` - automatically granted to every anonymous (not logged in) caller.

No `rmsRights` row is needed or created for these. They cannot be revoked.

### 2. Ordinary rights (DB-backed)

Everything else, including `member of administrators`:

- **`member of administrators` is NOT magic** despite the similar name. It requires an actual row in `rmsRights`.
- Grant via `$user->grant('member of administrators')` or `$user->grantAdministrator()` - this inserts into `rmsRights` (and auto-creates the right in `rmsCommands` if missing).
- `User::isAdministrator()` = `hasRight('member of administrators')` = real DB lookup. No code fallback.

## Authorization Flow

For a listener with `"right": "..."`:

1. Core dispatches `system:authorize` before running the listener.
2. `UserService::onAuthorize()` (RMS authorization provider) calls `User::filterRights()` with the required rights.
3. `filterRights()` grants a right if:
    - a matching `commandHash` exists in `rmsRights` for the user, OR
    - the right is `member of users` and the user is logged in, OR
    - the right is `member of guests` and the user is not logged in.
4. If none match: listener is skipped, event gets `UNAUTHORIZED` (HTTP 401/403).

## Storage Schema

- `rmsCommands` - rights registry: `hash` (binary sha1 of right text), `command` (right text).
- `rmsRights` - assignments: `userId` + `commandHash`.

## Granting Rights

The easiest way is the CLI:

```bash
bin/zolinga rms:user --user="user@example.com" --grant="member of administrators"
```

Options: `--grant`, `--revoke`, `--hasRight`, `--list`, `--setPassword`. See wiki `Zolinga RMS/CLI.md`.

Programmatically: `$user->grant('member of administrators')` or `$user->grantAdministrator()` - inserts into `rmsRights` (auto-creates the right in `rmsCommands` if missing).

## Testing Rights-Protected Listeners from CLI

- The user used for testing must genuinely hold the required right in `rmsRights` - there is no code fallback for anything except the magic rights.
- Verify first (read-only): `bin/zolinga rms:user --user=<id> --hasRight="member of administrators"` or `--list`.
- **NEVER insert/delete `rmsRights` rows just to make a test pass.** Ask the user to grant the right via the CLI above, or test with a user who already holds it.

## Common Pitfalls

- Assuming `admin@...` usernames are administrators - the right is DB content, not username-based.
- Assuming `member of administrators` behaves like `member of users` - it does not.
- Adding `right` to a `system:authorize` listener - creates an authorization loop.

## References

- Wiki: `modules/zolinga-rms/wiki/Zolinga RMS/Magic Rights.md`
- `User::filterRights()` in `modules/zolinga-rms/src/User.php`
- `UserService::onAuthorize()` in `modules/zolinga-rms/src/UserService.php`
- Skill `system-security-practices` - manifest right conventions.
- Skill `system-create-handler` - listener authorization wiring.