<!-- tooling:start (managed by wordpress-plugin-boilerplate/tooling - do not edit by hand) -->
# Fullworks Gravity Image Resize - Development Guide

Tooling in this repository is standardised across the Fullworks free plugins. The master
description lives in
[wordpress-plugin-boilerplate/CLAUDE.md](https://github.com/alanef/wordpress-plugin-boilerplate/blob/main/CLAUDE.md).
**Fix tooling problems there first, then roll out** with its `bin/sync-tooling.sh`; never
hand-edit the managed files listed there.

## This repository

| | |
|---|---|
| Plugin directory | `fullworks-gravity-image-resize/` |
| Main file | `fullworks-gravity-image-resize/fullworks-gravity-image-resize.php` |
| Default branch | `main` |
| WordPress.org slug | `fullworks-gravity-image-resize` |
| wp-env ports | dev `8901`, tests `8902` |
| Version locations | plugin header `Version:`, `readme.txt` `Stable tag:` and `FWGIR_VERSION` in the main file |

CI fails when the version locations disagree.

## Commands

```bash
composer install && npm install   # first time
composer run check                # PHPCompatibility + WordPress security sniffs
npm run start                     # wp-env (dev :8901, tests :8902, admin/password)
npm test                          # PHPUnit inside the wp-env tests container
npm test -- --filter Foo          # pass PHPUnit args through
composer run build                # zipped/fullworks-gravity-image-resize-free.zip via wp dist-archive
```

## Changelog and versions

One changelog: `fullworks-gravity-image-resize/changelog.txt`, wp.org style, shipped in the zip and published
to the KB by `update-changelog.sh` where `.env.wordpress` exists. `readme.txt` may link to it
or repeat recent entries, but is not the source. Every user-visible change adds a typed bullet
under `= Unreleased =` at the top, in the same commit:

```
= Unreleased =
* Fixed: what was wrong, and for whom
* Added: / Changed: / Deprecated: / Removed: / Security:
```

While developing, carry a prerelease suffix (`1.4.0-alpha.1`, bump only the `N`); release.yml
refuses to publish one. Releasing drops it.

## Release

Always through the commands, never by hand:

```
/tk-release X.Y.Z    # scripts/release/bump-version.sh: every version location + changelog, read back
                     # scripts/release/check-dependencies.sh: shipped composer deps block, tooling warns
                     # then commit the release ("Release X.Y.Z") and push
                     # scripts/release/run-tests-and-mark.sh X.Y.Z: CI's gates locally (wp-env running)
                     # commit .sdlc/releases/X.Y.Z/ evidence and push
/tk-deploy live      # .claude/commands/deploy-live.md: preconditions, confirm, tag vX.Y.Z, watch CI
```

`/tk-release` runs the test hook before you commit, so run it **again after** the release
commit: its marker names the commit, and `/tk-deploy live` refuses it if plugin code changed
since. The tag runs `Build Release`,
which re-runs the checks, creates the GitHub release with the zip attached, then deploys to
whatever the plugin's `Type:` calls for.

## Where a release goes

`Build Release` reads a `Type:` header from the plugin's `readme.txt` and deploys
accordingly. Every plugin gets a GitHub release; `Type:` only decides what else:

| `readme.txt` | GitHub | WordPress.org | Freemius |
|---|---|---|---|
| *(no `Type:` header)* | yes | | |
| `Type: free` | yes | yes | |
| `Type: freemium` | yes | yes | yes |
| `Type: premium` | yes | | yes |

Omitting the header is the safe default -- a plugin is never published anywhere
public by accident. A header that is present but unrecognised **fails the
release**, because that is a typo rather than a choice, and silently falling back
to GitHub-only is how a plugin quietly stops reaching WordPress.org for months.
The resolved targets are echoed in the job log and the run summary.

With no `Type:` header the plugin also **updates itself from its GitHub releases** (managed
`includes/class-github-updater.php` and `Update URI:` header, both added by the sync). Adding a
`Type:` and re-syncing removes them, since WordPress.org forbids off-site updates.

WordPress.org deploys need the `SVN_USERNAME` and `SVN_PASSWORD` repository
secrets; the step fails loudly if they are missing or the SVN tag already exists.
<!-- tooling:end -->

# Claude Code Instructions for WordPress Plugin Development

When working on WordPress plugin development in this repository, please follow these guidelines:

## Required Reading

Before starting any WordPress plugin development work:

1. **Read the README.md** - Contains the overall project structure, setup instructions, and deployment strategies
2. **Read AI-WORDPRESS-PLUGIN-PROMPT.md** - Contains comprehensive WordPress.org compliance requirements including:
   - Security best practices (input sanitization, output escaping, nonces)
   - Proper namespacing and unique prefixes (minimum 4 characters)
   - WordPress coding standards
   - Common review failures to avoid
   - Proper enqueueing of scripts and styles
   - Translation and internationalization requirements

## Key Development Principles

### Security First
- ALWAYS sanitize all inputs
- ALWAYS escape all outputs at the last possible moment
- ALWAYS use nonces for forms and AJAX requests
- ALWAYS check user capabilities

### WordPress Standards
- Use WordPress functions instead of PHP natives (e.g., `wp_remote_get()` not `curl`)
- Enqueue scripts/styles properly - never include directly
- Use WordPress bundled libraries (jQuery, etc.) - don't download your own
- Follow WordPress naming conventions and coding standards

### Unique Prefixes
- All global functions, constants, and classes must have unique prefixes of at least 4 characters
- Example: Use `MYAWESOMEPLUGIN_` not `MY_` for constants
- Namespaces should also be unique: `MyAwesomePlugin\` not `MyPlugin\`

### No Trademark Violations
- Never use "WordPress" in plugin names (use "WP" instead)
- Avoid using trademarked names unless creating official integrations

### Clean Code
- Use Composer autoloading with classmap
- Organize code into logical directories (admin/, public/, includes/)
- Include proper PHPDoc comments
- Add translator comments for translatable strings with placeholders

## Running Quality Checks

Before committing any code:

```bash
# Check PHP coding standards
npm run lint:php

# Fix PHP coding standards automatically
npm run lint:php:fix

# Run tests
npm run test
```

## Development Workflow

1. Start the development environment: `npm run env:start`
2. Make your changes following the guidelines
3. Test thoroughly in the local environment
4. Run PHPCS to ensure code quality
5. Commit with clear, descriptive messages

## Important Files

- `phpcs.xml.dist` - WordPress coding standards configuration
- `.distignore` - Files to exclude from distribution builds
- `.wp-env.json` - Local development environment configuration

Remember: The goal is to create secure, efficient plugins that will pass WordPress.org review on first submission.