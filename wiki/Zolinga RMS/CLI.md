# Command Line Interface (CLI)

## Overview
The Zolinga RMS CLI is a command line tool that allows users to interact with the Zolinga RMS system. It provides a set of commands for managing resources, users, and other aspects of the system.

## Usage

```
bin/zolinga rms:user --user=<user mail or id>
      [--grant=<string>]
      [--revoke=<string>]
      [--hasRight=<string>]
      [--list]
      [--login]
```      

* `--user` - The user to manage. This can be specified as an user name (email address) or user id.
* `--grant` - Grants a right to the user.
* `--revoke` - Revokes a right from the user.
* `--hasRight` - Checks if the user has a specific right.
* `--list` - Lists all rights of the user.
* `--login` - Logs the user in without a password. See [Logging In From Command Line](#logging-in-from-command-line).

## Examples

```bash
bin/zolinga rms:user --user="user@example.com" --grant="member of administrators"

bin/zolinga rms:user --user=123 --revoke="access page#134"

bin/zolinga --single=permissions rms:user --user="admin@example.com" --list

bin/zolinga --single=hasRight rms:user --user="admin@example.com" --hasRight="read all"
```

Note: The `--single=permissions` option is used to output only the response's `permissions` field. See `bin/zolinga --help` for details.

# Logging In From Command Line

The `--login` option of the `rms:user` event logs the user in without a password. Subsequent events in the same `bin/zolinga` run are then authorized as that user:

```bash
bin/zolinga rms:user --user="admin@example.com" --login \
    my:event --param=value
```

- The login is passwordless and works only from the command line.
- The last-login timestamp and IP are **not** updated.
- The login takes effect for all events chained after `rms:user` in the same run.

# Related
* [API Interface](:ref:event:rms:user) - The API interface for Zolinga User CLI.