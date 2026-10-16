# Releasing EstateCraft (slug `wp-smart-property`)

Git is the source of truth; WordPress.org SVN is updated only by GitHub Actions.

## One-time setup
Repository secrets (Settings → Secrets and variables → Actions):
- `SVN_USERNAME` — WordPress.org username
- `SVN_PASSWORD` — SVN password from WordPress.org Profile → Account & Security

## Release
1. On the release branch, bump the version in `init.php` (the `Version:` header and
   `ESTATECRAFT_VERSION`), `package.json` and `readme.txt` (`Stable tag:`).
2. Add a `== Changelog ==` entry (and an `== Upgrade Notice ==` for security or major changes) in
   `readme.txt`.
3. Open a PR to `master` and wait for CI to pass: PHPCS, ESLint, Stylelint, the build, the
   shipped-PHP lint on 7.4–8.4 and PHPUnit on WordPress 6.5 and latest.
4. **Tag the PR's head commit first, then merge.** Tags have no `v` prefix:
   ```
   git tag 2.0.1 <head-commit> && git push origin 2.0.1
   ```
   Wait for the "Deploy to WordPress.org" run to finish, then merge the PR.

   Why this order: a merge to `master` that touches `readme.txt` runs `assets.yml`, which updates
   `trunk/readme.txt`. `assets.yml` skips itself while the readme's `Stable tag` is not in SVN, so
   merging early cannot point WordPress.org at a missing tag, but tagging first is the normal order.
5. `deploy.yml` verifies the versions match, installs `vendor/` (no dev packages) and builds
   `build/` with npm, lints every shipped PHP file, syncs `trunk`, creates
   `tags/X.Y.Z`, updates `/assets` from `.wordpress-org/` (when present), and attaches the ZIP to a
   GitHub release.

Only exact `X.Y.Z` tags deploy; a tag such as `3.0.0-rc.1` does nothing.

## Tests
```
composer install
bin/install-wp-tests.sh wordpress_test root '' localhost latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --group ajax
npm ci && npm run build && npm run lint:js && npm run lint:css
```

## Readme / asset-only updates
Changes to `readme.txt` or `.wordpress-org/` on `master` are pushed to SVN by `assets.yml` without
cutting a release (e.g. bumping "Tested up to"). Don't change `Stable tag` this way.

Files listed in `.distignore` never reach SVN.
