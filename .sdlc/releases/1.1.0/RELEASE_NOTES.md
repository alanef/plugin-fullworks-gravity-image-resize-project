# Release 1.1.0

**Date:** 2026-10-26
**Previous:** none tagged. 1.0.0 was installed by hand on lawncrew and never had a GitHub release.

## Highlights

The plugin now updates itself from its GitHub releases. After one manual install of 1.1.0 on lawncrew, later releases arrive through the normal WordPress update screens.

## What's New

### Added
- Self-update from GitHub releases through core's `Update URI` mechanism, using the boilerplate's managed updater.

### Changed
- Tested up to WordPress 7.1.

No change to resizing, scanning or orphan handling.

## Breaking Changes

None.

## Migration Guide

1.0.0 has no updater, so lawncrew will not be offered 1.1.0. HOSTING DevOps installs `fullworks-gravity-image-resize-1.1.0.zip` once, replacing the 1.0.0 folder. From then on it updates itself.

## Technical Details

### Issues Included
None filed. The work was assigned by PLUGINS Manager with Alan's approval: tooling to standard, plus the GitHub self-updater.

### Commits
- fd52e63: boilerplate tooling sync on the github pipeline: GitHub updater, standard CI with the http-basic dist-archive install, canonical test runner (helper require moved into the two integration tests), changelog.txt replaces CHANGELOG.md, plugin vendor/ untracked, lock files tracked
- f389aaf: Tested up to 7.1 (the only Plugin Check error)
- 97234c4: check-dependencies.sh re-synced (plugins with no Composer packages)
- Release 1.1.0 (version bump, .pot)
