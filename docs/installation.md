# Installation

This guide is for **GLPI administrators**. If you are installing from Git to develop or patch the plugin, see **[CONTRIBUTING.md](../CONTRIBUTING.md)** for Composer and tooling.

## What you need

- A supported **GLPI** version (the plugin installer will warn you if yours is too old or too new).
- **PHP** 8.2 or newer (again, the installer checks this).
- A normal GLPI database and web server setup, same as for GLPI itself.

Official **release packages** include dependencies the plugin needs. You normally **do not** need to run Composer.

## Folder name (important)

The plugin folder **must** be named exactly:

```text
singlesignon
```

So the full path looks like:

```text
…/plugins/singlesignon/
```

If you unpack a ZIP and get a name like `glpi-singlesignon-main`, **rename** it to `singlesignon` before enabling the plugin. Otherwise GLPI will show an error.

---

## Install from a release package (recommended)

Release archives are published on **[GitHub Releases](https://github.com/edgardmessias/glpi-singlesignon/releases)**. Each release includes **`singlesignon.zip`**, **`singlesignon.tgz`**, and **`singlesignon.tar.bz2`** (same content; the archive already contains a top-level **`singlesignon/`** folder).

### Download URLs

| What you want | URL pattern |
|---------------|-------------|
| **Latest stable release** (GitHub’s “latest” non-draft; usually skips pre-releases) | `https://github.com/edgardmessias/glpi-singlesignon/releases/latest/download/singlesignon.tar.bz2` |
| **A specific version** | `https://github.com/edgardmessias/glpi-singlesignon/releases/download/<TAG>/singlesignon.tar.bz2` |

Replace `<TAG>` with the Git tag of the release (examples: `v2.0.0`, `v1.5.1`). The tag is shown on the release page. You can use **`.zip`** instead of **`.tar.bz2`** in the filename if you prefer.

### Linux / macOS (shell)

Replace `/path/to/glpi/plugins` with your GLPI `plugins` directory.

**Latest release:**

```bash
cd /path/to/glpi/plugins
curl -fL -O https://github.com/edgardmessias/glpi-singlesignon/releases/latest/download/singlesignon.tar.bz2
tar -xjf singlesignon.tar.bz2
rm singlesignon.tar.bz2
```

**Specific tag** (set `TAG` to match the release, e.g. `v2.0.0`):

```bash
cd /path/to/glpi/plugins
TAG=v2.0.0
curl -fL -O "https://github.com/edgardmessias/glpi-singlesignon/releases/download/${TAG}/singlesignon.tar.bz2"
tar -xjf singlesignon.tar.bz2
rm singlesignon.tar.bz2
```

With **GNU wget** instead of curl:

```bash
cd /path/to/glpi/plugins
wget -O singlesignon.tar.bz2 https://github.com/edgardmessias/glpi-singlesignon/releases/latest/download/singlesignon.tar.bz2
tar -xjf singlesignon.tar.bz2 && rm singlesignon.tar.bz2
```

After extraction you should have `.../plugins/singlesignon/setup.php`.

### Windows (PowerShell)

Replace `C:\path\to\glpi\plugins` with your GLPI `plugins` path. `tar` is available on current Windows 10 and 11.

**Latest release:**

```powershell
$plugins = "C:\path\to\glpi\plugins"
$uri = "https://github.com/edgardmessias/glpi-singlesignon/releases/latest/download/singlesignon.tar.bz2"
Invoke-WebRequest -Uri $uri -OutFile "$plugins\singlesignon.tar.bz2"
tar -xjf "$plugins\singlesignon.tar.bz2" -C $plugins
Remove-Item "$plugins\singlesignon.tar.bz2"
```

**Specific tag:**

```powershell
$plugins = "C:\path\to\glpi\plugins"
$tag = "v2.0.0"
$uri = "https://github.com/edgardmessias/glpi-singlesignon/releases/download/$tag/singlesignon.tar.bz2"
Invoke-WebRequest -Uri $uri -OutFile "$plugins\singlesignon.tar.bz2"
tar -xjf "$plugins\singlesignon.tar.bz2" -C $plugins
Remove-Item "$plugins\singlesignon.tar.bz2"
```

### Finish in GLPI

1. Confirm **`plugins\singlesignon\setup.php`** (or `plugins/singlesignon/setup.php`) exists.
2. Open **Setup → Plugins**, find **Single Sign-On**, then **Install** and **Enable**.

---

## Install from GitHub (technical users)

Use this only if you know why you need a Git checkout (for example to test a fix before it is released).

```bash
cd /path/to/glpi/plugins
git clone https://github.com/edgardmessias/glpi-singlesignon.git singlesignon
cd singlesignon
composer install --no-dev --optimize-autoloader
```

On Windows PowerShell, the same idea applies: clone into `plugins\singlesignon`, then run `composer install` inside that folder.

For contributor workflow and lint commands, see **[CONTRIBUTING.md](../CONTRIBUTING.md)**.

---

## After installation

1. **Setup → Single Sign-On** — create a provider and copy the **Callback URL** into your identity provider’s application settings.  
   If that URL shows the **wrong address** (internal hostname, `http` instead of `https`, missing path), fix GLPI’s global **URL of the application** first — see [FAQ — Callback URL](faq.md#the-callback-url-shows-the-wrong-host-or-protocol).
2. Log out and try **Login with …** on the GLPI login page.

---

## Uninstall

Use **Setup → Plugins** to disable and uninstall. Uninstall removes the plugin’s tables and stored settings; plan backups for production systems.

---

## Next

- [Identity providers](identity-providers.md) — register the app at Microsoft, Google, GitHub, etc.  
- [Configuration](configuration.md) — every option on the GLPI provider form  
- [Field mappings](field-mappings.md) — match profile fields to GLPI users  
- [FAQ](faq.md) — common problems  
