# userRecover

MCP tool for sending a password recovery e-mail to an RMS (Rights Management System) user.

## Description

Sends the same recovery e-mail as the "Forgot password" form, but to any account
instead of only the one currently typing in the browser. The e-mail contains a
time-limited link (one hour) that lets the account owner choose a new password.

No password is returned to the caller. When the e-mail cannot be delivered the
call fails with an error. When the account does not exist the call still reports
success, so the answer cannot be used to find out which e-mails are registered.

Requires the `member of administrators` right. Users without it do not see this
tool in `tools/list` and cannot call it.

## Arguments

| Argument | Type | Required | Description |
|---|---|---|---|
| `username` | string (email) | yes | Login e-mail of the account that should receive the recovery e-mail. |
| `referrer` | string | no | Page URL used as the base of the reset link, so the owner lands on a known page. Defaults to the site's configured base URL. |

## Response

```json
{
  "showCard": "sign-in"
}
```

## Example

```bash
bin/zolinga rms:user --login=admin@example.com mcp@userRecover \
  --username=jane@example.com
```

The owner then opens the link in the e-mail and submits a new password, which
resets the account through the ordinary password reset form.

## Related

- `rms:recover` - the same recovery e-mail requested by the visitor from the web form.
- `userCreate` - creating the account in the first place.

## Technical Details

- Event: `userRecover`
- Origin: `mcp`
- Right: `member of administrators`
- Class: `Zolinga\Rms\Api\UserApi`, method `onRecover` (shared with `rms:recover`)
- Schemas: `module://zolinga-rms/schema/mcp/userRecover-request.json`, `module://zolinga-rms/schema/mcp/userRecover-response.json`
