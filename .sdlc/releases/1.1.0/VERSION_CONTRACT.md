# Version Contract: 1.1.0

**Date:** 2026-10-26

## Canonical Source

These are written and read back by `scripts/release/bump-version.sh`, and CI fails when they disagree:
- the plugin header `Version:` in fullworks-gravity-image-resize/fullworks-gravity-image-resize.php;
- `FWGIR_VERSION`;
- `Stable tag:` in fullworks-gravity-image-resize/readme.txt;
- package.json, package-lock.json and the .pot.

## Expected State After Deploy

- main has every version location at `1.1.0`, and `= 1.1.0 =` at the top of changelog.txt.
- Git tag `v1.1.0` is on main's HEAD. release.yml creates the GitHub release (not a prerelease) with `fullworks-gravity-image-resize-1.1.0.zip` attached. There is no `Type:` header, so there is no wp.org or Freemius deploy.
- `https://github.com/alanef/plugin-fullworks-gravity-image-resize-project/releases/latest` redirects to `/releases/tag/v1.1.0`. That redirect is what the updater reads.

## Required Deploy Command

```
/tk-deploy live
```

## Post-Deploy

- HOSTING DevOps installs the 1.1.0 zip once on lawncrew, replacing 1.0.0, which has no updater.
- After that, check that lawncrew's Dashboard → Updates shows no pending GIR update, and that "View details" works.
