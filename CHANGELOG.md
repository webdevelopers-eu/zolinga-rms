# Changelog

All notable changes to this module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## [Unreleased]

### Fixed
- `install/install/010-database.sql` had an uncommented `====` banner line between the `rmsMeta` table and the trigger section, which is invalid SQL and made the whole script fail to install. Also removed the `DELIMITER ;;` directive, which the installer rejects because `DELIMITER` is a mysql CLI client command rather than a server statement.

## [1.10] - 2026-11-07

### Added
- MCP tool `userRecover`: sends a password recovery e-mail with a time-limited reset link to any user account. Reuses `UserApi::onRecover()`, which now accepts `WebEvent|CallEvent`. Protected by the `member of administrators` right.

### Changed
- `UserApi::onRecover()` falls back to `$api->config['baseURL']` when no `referrer` is supplied, so the reset link is absolute instead of root-relative.

## [1.9] - 2026-11-07

### Added
- Event `rms:user:created`, dispatched internally by `$api->rms->createUser()` with the new `userId`. Feature modules listen to it to provision their per-user records, regardless of which path created the user (registration, Google login, MCP `userCreate`, `rms:user` CLI).

## [1.8] - 2026-11-07

### Changed
- MCP tool `userCreate`: `password` is now optional. When omitted, the server generates a random password derived from `random_bytes(32)` (MD5) that is never returned to the caller, so the account stays unusable until the owner resets the password.

## [1.7] - 2026-11-07

### Added
- MCP tool `userCreate` for creating RMS users. Accepts `username` (valid e-mail) and `password`, plus optional `canLogin`, `lang`, `givenName`, `familyName` and `grant` (list of rights to grant). Protected by the `member of administrators` right, so only administrators see it in `tools/list` and can call it.

## [1.6] - 2026-10-23

### Security
- `UserService::reset()` now also clears the loaded user data (via `parent::reset()`). Previously it only cleared the session and cookies, so a failed login left the identity loaded: `loginNoPassword()` returned `false` while `$api->user->id`, `isGuest()` and `hasRight()` still reported the previous user. Any caller that trusted the boolean could then act as that user.

### Added
- `rms:user --login` CLI option: passwordless login of the managed user. Subsequent events in the same CLI run are authorized as that user. Does not update `lastLogin`/`lastLoginFrom`.
- Wiki article "Magic Rights" expanded: documents that `member of users`/`member of guests` are code-based magic rights while `member of administrators` is an ordinary DB-backed right requiring an `rmsRights` row, plus the authorization flow and CLI testing notes.
- New agent skill `zolinga-rms-rights` covering rights resolution, storage schema, and testing of rights-protected listeners.

### Fixed
- Wiki `Zolinga RMS/CLI.md` examples used wrong event name `zolinga:rms`; the actual manifest event is `rms:user`. Examples corrected.

## [1.2] - 2026-09-03

### Added
- MCP tool `userSearch` for searching RMS users by username (LIKE), id (exact), lastLoginFrom IP (exact), and date ranges on created/modified/lastLogin. Requires the "manage rms" right.

## [1.1] - 2026-07-08

### Changed
- Migrated `rmsUsers` table from `utf8mb3` to `utf8mb4` character set.

## [1.0] - 2025-01-01

### Added
- Initial release.