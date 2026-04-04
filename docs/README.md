# Single Sign-On for GLPI — Documentation

These pages are written for **GLPI administrators** who will configure OAuth login. You do not need to be a programmer; a basic idea of “redirect URL”, “client ID”, and “user profile JSON” is enough.

## What this plugin does

Users can sign in to GLPI through an external account (Microsoft, Google, GitHub, or any OAuth/OIDC server you configure). After the provider confirms their identity, GLPI receives profile data and logs them in—or creates an account, if you allow that.

## Documents

| Document | What you will find |
|----------|-------------------|
| [Installation](installation.md) | Supported versions, installing from a release package, folder name, enabling the plugin |
| [Identity providers](identity-providers.md) | Step-by-step: Azure, Google, GitHub, Facebook, Instagram, LinkedIn, Generic |
| [Configuration](configuration.md) | Every option on the provider form (URLs, login rules, registration, photos) |
| [Field mappings](field-mappings.md) | How GLPI reads email, name, and id from the provider’s user profile |
| [FAQ](faq.md) | Wrong callback URL, redirect errors, buttons missing, etc. |

## Quick links

- **Source code & issues:** [GitHub — glpi-singlesignon](https://github.com/edgardmessias/glpi-singlesignon)
- **Download packages:** [Releases](https://github.com/edgardmessias/glpi-singlesignon/releases)

## For developers

See **[CONTRIBUTING.md](../CONTRIBUTING.md)** in the repository root (structure of the code, Composer, releases, translations, hooks).

After installation, day-to-day setup is done in GLPI under **Setup → Single Sign-On** (and **Setup → Plugins** to install or enable the plugin).
