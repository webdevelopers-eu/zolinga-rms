# Changelog

All notable changes to this module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## [Unreleased]

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