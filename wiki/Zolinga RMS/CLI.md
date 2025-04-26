# Command Line Interface (CLI)

## Overview
The Zolinga RMS CLI is a command line tool that allows users to interact with the Zolinga RMS system. It provides a set of commands for managing resources, users, and other aspects of the system.

## Usage

```
bin/zolinga zolinga:rms --user=<user mail or id>
      [--grant=<string>]
      [--revoke=<string>]
      [--hasRight=<string>]
      [--list]
```      

* `--user` - The user to manage. This can be specified as an user name (email address) or user id.
* `--grant` - Grants a right to the user.
* `--revoke` - Revokes a right from the user.
* `--hasRight` - Checks if the user has a specific right.
* `--list` - Lists all rights of the user.

## Examples

```bash
bin/zolinga zolinga:rms --user="user@example.com" --grant="member of administrators"

bin/zolinga zolinga:rms --user=123 --revoke="access page#134" --list

bin/zolinga zolinga:rms --user="another@example.com" --hasRight="read all"
```

# Related
* [API Interface](:Zolinga RMS:CLI) - The API interface for Zolinga User CLI.