# userSearch

MCP tool for searching RMS (Rights Management System) users.

## Description

Returns an array of user records matching the given criteria. All criteria are optional; when none are provided all users are returned (up to the limit).

Requires the `manage rms` right.

## Arguments

| Argument | Type | Match Type | Description |
|---|---|---|---|
| `username` | string | LIKE | Partial username (e-mail) to search for. Wildcards added automatically. |
| `id` | integer | exact | Exact user ID to match. |
| `lastLoginFrom` | string | exact | Exact IP address of the last login to match. |
| `createdFrom` | int\|string | range (>=) | Lower bound for the `created` timestamp. |
| `createdTo` | int\|string | range (<=) | Upper bound for the `created` timestamp. |
| `modifiedFrom` | int\|string | range (>=) | Lower bound for the `modified` timestamp. |
| `modifiedTo` | int\|string | range (<=) | Upper bound for the `modified` timestamp. |
| `lastLoginAfter` | int\|string | range (>=) | Lower bound for the `lastLogin` timestamp. |
| `lastLoginBefore` | int\|string | range (<=) | Upper bound for the `lastLogin` timestamp. |
| `limit` | integer | - | Max results. Default 100, max 1000. |

Date range values may be Unix timestamps (integers) or date strings parseable by PHP's `strtotime()`.

## Response

```json
{
  "data": [
    {
      "id": 123,
      "username": "user@example.com",
      "removed": 0,
      "canLogin": true,
      "created": 1700000000,
      "modified": 1700000000,
      "lastLogin": 1700000000,
      "lastLoginFrom": "192.168.1.1"
    }
  ]
}
```

## Examples

Search by partial username:

```bash
bin/zolinga userSearch '{"username": "example.com"}'
```

Search by date range:

```bash
bin/zolinga userSearch '{"createdFrom": "2025-01-01", "createdTo": "2025-12-31"}'
```

Search by exact ID:

```bash
bin/zolinga userSearch '{"id": 42}'
```