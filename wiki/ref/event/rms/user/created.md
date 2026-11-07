# rms:user:created

Dispatched internally by the RMS service after a user record is created.

## Description

`$api->rms->createUser()` dispatches this event once the new row is in `rmsUsers`
and the user is in the service cache. The request carries a single key:

| Key | Type | Description |
|---|---|---|
| `userId` | integer | ID of the newly created user. |

It fires for every creation path, because they all go through `createUser()`:
front-end registration, Google login, the MCP `userCreate` tool and the
`rms:user` CLI. It does not fire for users created by direct SQL.

Listeners are expected to provision per-user data of their own module (for
example an application account record). RMS knows nothing about them, which is
why this exists instead of an RMS-side hook.

Handling this event must not throw: the user already exists, so a failure would
leave the caller with a user it believes was not created. Log and continue.

## Emit

Declared in `zolinga.json` under `emit`:

```json
{
  "event": "rms:user:created",
  "description": "Dispatched internally after a user record is created...",
  "class": "\\Zolinga\\System\\Events\\RequestEvent",
  "origin": ["internal"]
}
```

## Listen

```json
{
  "event": "rms:user:created",
  "class": "\\My\\Module\\UserCreatedListener",
  "method": "onUserCreated",
  "origin": ["internal"]
}
```

```php
use Zolinga\System\Events\{ListenerInterface, RequestEvent};

class UserCreatedListener implements ListenerInterface
{
    public function onUserCreated(RequestEvent $event): void
    {
        $userId = (int) ($event->request['userId'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        // Provision this module's per-user record.
    }
}
```

## Testing

Create a throwaway user and watch the listener run. The CLI path goes through
the same `createUser()` method:

```bash
bin/zolinga rms:user --login=admin@example.com \
  mcp@userCreate --username=probe@example.com
```

## Related

- `rms:register` - browser-originated registration request (self-service sign-up only).
- `zolinga-rms` wiki "Zolinga RMS" - user management API.
