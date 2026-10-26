# Change Impact Assessment: 1.1.0

## Risk Level: Low

- Plugin behaviour is unchanged. The only runtime addition is the updater: the boilerplate's managed class, the same one used across the estate, loaded by a guarded require, with its own tests.
- The plugin's `vendor/` autoloader is no longer committed. The build runs `composer install` in the plugin directory, so the zip still carries it. A plain checkout without it falls back to `fwgir_load_classes()`, which loads `includes/` directly.
- No database or option changes.

### Factors Considered
- Number of issues: 0 (tooling and updater only)
- Security-sensitive changes: the updater fetches the redirect target of `github.com/<repo>/releases/latest`, which is HEAD only, with no token and a 5 s timeout. That is unchanged from the estate's other GitHub-only plugins.
- API changes: no
- Database changes: no
- Breaking changes: no

## Affected Areas
- `fullworks-gravity-image-resize.php`: the `Update URI` header and the guarded require of `includes/class-github-updater.php`.
- `includes/class-github-updater.php` (new, managed).
- Tooling: CI workflows, run-tests.sh, root phpunit.xml.dist, tests/bootstrap.php (canonical), composer and package.json, .wp-env.json (PHP 8.4), release hooks, `/tk-deploy`.

## Testing
- `npm test` in wp-env on WordPress 7.1.3 and PHP 8.4: 59 tests, the plugin's 41 plus the updater's 18, all passing.
- On the dev site: the plugin activates, `wp fwgir scan` runs, `update_plugins_github.com` is hooked, and there is no debug log output.
- checks.yml is green on f389aaf: PHPCS, version consistency, build, Plugin Check and PHPUnit.
