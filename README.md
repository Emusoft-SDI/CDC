# NATCODEV Platform

Multi-stakeholder agricultural platform for the National Coconut Development & Propagation Initiative:
grower registry, Academy LMS, marketplace, wallet & payments, certificate issuance/verification,
support desk, and field-agent / coordinator workspaces.

## Stack

- **PHP 8.3**, **MySQL**, **Apache** (UniServerZ on Windows).
- No framework and no Composer: a single bootstrap (`config.php`) plus procedural libraries in `lib/`.
- Hand-rolled CSS/JS. A small PWA shell lives in `manifest.json`, `sw.js`, and `mobile/`.

## Layout

- **Root** — public pages (`index.php`, `about.php`, `login.php`, `register-wizard.php`, `news.php`, …).
- **Portals** — `admin/`, `super-admin/`, `buyer/`, `provider/`, `market/` (real commerce engine) with
  `marketplace/` acting as URL-compatibility shims, `academy/`, `field-agent/`, `coordination/`,
  `dashboard/` (grower), `support/`, `registry/`.
- **Shared** — `lib/` (libraries, layouts, integrations), `config.php` (bootstrap + core helpers),
  `api/` (JSON endpoints), `cron/` (CLI workers), `webhooks/` (payment receivers).
- **Not web-accessible** (see `.htaccess`): `app/`, `lib/`, `cron/`, `tests/`, `tools/`,
  `documents/`, `private_backups/`, plus `.env`, `.log`, `.bak`, `.codex-bak`, `.zip`, etc.

## Local development

1. Serve from the UniServerZ web root; the app runs at `http://localhost/win`.
2. Copy `.env.example` to `.env` and fill in the values. **Never commit `.env`.**
3. PHP binary used locally: `C:\UniServerZ\core\php83\php.exe`.

## Tests

Tests are a bespoke CLI harness (no PHPUnit): `tests/TestHarness.php` plus 12 suites driven by
`tests/run_security_suite.php`. They exercise a real MySQL database and insert fixtures.

```
DB_DATABASE=natcodev_test php tests/run_security_suite.php
```

The harness **refuses to run against a non-test database** (the DB name must match `*test*`), because
a past run wrote fixtures into production. There is deliberately no override: every suite aborts
before writing if it is not pointed at a test-scoped database.

If fixtures ever do leak into a database, purge them with the dry-run cleanup tool:

```
php tools/purge-test-data.php                          # report what would be removed
php tools/purge-test-data.php --apply --confirm=<db>   # back up, then delete
```

## Lint

Every PHP file must pass `php -l`; this runs in CI (`.github/workflows/ci.yml`).

```
find . -name '*.php' -exec php -l {} \;
```

## Deployment

Manual: pull the branch into the UniServerZ web root. There is **no build step**.

## Security notes

- Secrets live in `.env` (untracked) — never commit `.env` or ` - Copy.env`.
- **Maintenance break-glass:** set `MAINTENANCE_BYPASS_KEY` in `.env`, then visit
  `?admin_bypass=<key>` (or set the `natcodev_bypass` cookie directly). To end maintenance, remove the
  `.maintenance` flag file. Do not hardcode bypass tokens in source.
- Do not add one-off dev/refactor scripts to the web root; they are web-reachable `.php` files.

## Architecture direction

The app is being migrated incrementally from page-per-file to **business-domain modules** under
`app/Modules/` (Identity, Registry, Academy, Marketplace, Wallet, Certificates, Support, Governance,
FieldOps). Migrated pages become thin shims that delegate to a module controller, so existing URLs
keep working throughout. New code should target the module layout rather than adding to the root.
