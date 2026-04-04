# Contributing

Thank you for helping improve Single Sign-On for GLPI. This document is for **developers and maintainers**. End-user documentation is in [`docs/`](./docs/README.md).

## Use GLPI from Git (recommended)

For plugin development, install **GLPI itself from the source repository** (Git), not only the production tarball. Official guidance:

- **[GLPI — `INSTALL.md` (source / dependencies)](https://github.com/glpi-project/glpi/blob/main/INSTALL.md)** — when you work from source, GLPI requires extra steps so all third-party libraries (PHP and JS) are present.
- **[GLPI installation documentation](https://glpi-install.readthedocs.io/)** — general install and prerequisites.

From the GLPI root, after [Composer](https://getcomposer.org/) and [npm](https://www.npmjs.com/) are available, run:

```bash
php bin/console dependencies install
```

That command installs GLPI’s dependencies, **including development tooling** used by the core tree. This plugin’s `composer.json` scripts invoke binaries from **`../../vendor/bin/`** (PHP CS Fixer, PHPStan, Psalm, Rector, …), i.e. **GLPI’s** `vendor/bin`, not a standalone copy. If GLPI was installed without those dev dependencies, `composer lint` from the plugin directory will fail or be incomplete.

**Summary:** clone GLPI from Git → `php bin/console dependencies install` in GLPI → place this plugin under `plugins/singlesignon` → `composer install` inside the plugin (with dev packages). Then lint and other scripts align with how GLPI expects plugins to be developed.

For norms and coding expectations on GLPI itself, see **[Contributing to GLPI](https://github.com/glpi-project/glpi/blob/main/CONTRIBUTING.md)** and the **[GLPI developer documentation](https://glpi-developer-documentation.readthedocs.io/)**.

## Getting started

1. **GLPI:** follow the official source install above so `vendor/bin` in the GLPI root is populated.
2. **Plugin:** fork and clone into `plugins/singlesignon` (exact folder name; see [`docs/installation.md`](./docs/installation.md)).
3. From the plugin directory, install dependencies **including** `require-dev`:

   ```bash
   composer install
   ```

4. Run quality checks:

   ```bash
   composer lint
   ```

   This runs `license-check`, `rector`, `php-cs-fixer`, `phpstan`, and `psalm` as defined in `composer.json`. Fix-oriented variants: `composer lint:fix` (where applicable).

## Project layout

| Path | Purpose |
|------|---------|
| `setup.php` | Plugin version, GLPI compatibility range, hook registration |
| `hook.php` | Install/uninstall/migrations, database tables |
| `src/` | PHP classes (`Provider`, `Provider_Field`, `LoginRenderer`, `Preference`, …) |
| `front/` | HTTP entry points (`callback.php`, provider UI) |
| `templates/` | Twig templates |
| `providers.json` | Default OAuth endpoints and default field mappings per preset type |
| `locales/` | gettext translations |
| `composer.json` | Runtime dependency: `galbar/jsonpath`; dev tooling via GLPI/tools when applicable |

## Pull requests

- Keep changes focused and match existing style.
- Fix or add tests / static analysis where the project expects them.
- Do not commit secrets or real OAuth credentials.

## Release process (maintainers)

Releases can be automated via GitHub Actions (see `.github/workflows/`).

1. Update **`plugin.xml`**: version, GLPI compatibility, download URL when applicable.
2. Update **`setup.php`**: `PLUGIN_SINGLESIGNON_VERSION` (and related constants if needed).
3. Create the branch/tag as required by your release workflow.

## Translations

Community translations are coordinated on [Transifex](https://app.transifex.com/eduardomozart/glpi-singlesignon/languages/).

## Advanced integration (hooks)

Plugins or custom code can alter behavior using GLPI hooks invoked from this project, for example:

| Hook | Purpose |
|------|---------|
| `sso:scope` | Adjust scope before it is sent to the IdP |
| `sso:url_authorize` | Adjust the authorization URL or related fields |

Search the codebase for `Plugin::doHookFunction` to find exact call sites and payload shapes.

## Questions

- [Issue tracker](https://github.com/edgardmessias/glpi-singlesignon/issues)
- Administrator docs: [`docs/README.md`](./docs/README.md)
