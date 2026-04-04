# FAQ

Answers for **GLPI administrators** using the Single Sign-On plugin.

---

## Callback URL and GLPI’s public address

### The callback URL shows the wrong host or protocol

The **Callback URL** on the provider form is built from GLPI’s idea of “the address of this application”. If you see an **internal hostname**, **`http`** while users use **`https`**, a **wrong domain**, or a **missing folder** in the path, the IdP will reject the redirect or users will land on the wrong place.

**Fix it in GLPI (not in the plugin):**

1. Log in as an administrator.  
2. Open **Setup → General**.  
3. On the **General** configuration tab, set **URL of the application** to the full address people use in the browser to open GLPI (scheme, host, and path if GLPI is in a subdirectory).  
4. Save, then open the Single Sign-On provider again and **copy the Callback URL** once more for your identity provider.

Official GLPI description of that field: *“used in various links provided within the application, in notifications, and for the API”* — see **[General configuration](https://help.glpi-project.org/documentation/modules/configuration/general/general_configuration)** in the GLPI documentation (Help Center).

Behind reverse proxies or load balancers, also ensure GLPI trusts the proxy and knows the external URL so this value stays correct.

### What exact redirect URI must I register with the IdP?

Always copy the **Callback URL** from the provider form **after** GLPI’s **URL of the application** is correct. Compare carefully with the IdP (`https` vs `http`, port, path, no extra slash).

### “Redirect URI mismatch”

Usually the URI registered at the IdP is not **identical** to the one GLPI shows. Re-copy from GLPI after fixing the general URL. Check for typos and for `http`/`https` differences.

### Callback works on one network but not another

Often the **URL of the application** matches only one environment. Each environment (test/production) needs its own GLPI base URL and its own OAuth app / redirect URI.

---

## Installation

### Why does GLPI complain about the folder name?

The folder under `plugins` must be named **`singlesignon`**. Rename any other name (for example from a GitHub ZIP) before enabling.

### Do I need Composer?

**No**, if you installed an official **release package** with a `vendor` folder. **Yes**, if you cloned the Git repository for development — see **[CONTRIBUTING.md](../CONTRIBUTING.md)**.

### Which GLPI and PHP versions work?

Run **Install** from **Setup → Plugins**; GLPI will refuse the install if your version is unsupported. Current plugin targets **GLPI 11** and **PHP 8.2+**.

---

## Login and users

### The SSO button does not appear

Check configuration first:

- Turn **Active** on for the provider.  
- Enable the plugin under **Setup → Plugins**.  
- Open the normal GLPI **login** page (the plugin adds buttons there).

If everything is enabled but buttons still **do not show**, or the page looks **wrong after an upgrade**, clear **GLPI’s server-side cache** — see **[Clearing GLPI cache](#clearing-glpi-cache-templates-and-translations)**. Then hard-refresh the browser (e.g. Ctrl+F5) or use a private window.

### Plugin labels / translations look wrong or do not update

After changing language, updating the plugin, or deploying new locale files, text may stay in the wrong language until cache is rebuilt. Use the same steps as **[Clearing GLPI cache](#clearing-glpi-cache-templates-and-translations)**.

### Clearing GLPI cache (templates and translations)

GLPI caches compiled templates and related data. Stale cache can prevent SSO buttons from appearing on the login page or keep old translations visible.

**1. Recommended: GLPI console**

From your **GLPI root** directory (where `bin/console` lives):

```bash
php bin/console cache:clear
```

Windows (PowerShell), from the same folder:

```powershell
php bin\console cache:clear
```

Run the command as a user that can write to GLPI’s `files` directory (often the web server account) if you see permission errors.

**2. Fallback: clear the `files/_cache` directory**

If you cannot run the console, or problems remain, remove the cache under **`files/_cache`** (do **not** delete the whole `files` folder). Replace `<GLPI_ROOT>` with your real path.

Linux / macOS — delete contents only:

```bash
rm -rf <GLPI_ROOT>/files/_cache/*
```

Or remove the directory so GLPI can recreate it:

```bash
rm -rf <GLPI_ROOT>/files/_cache
```

Windows (PowerShell):

```powershell
Remove-Item -Recurse -Force "<GLPI_ROOT>\files\_cache\*"
```

**After clearing:** reload the login page. If you just activated or upgraded the plugin, run **`php bin/console cache:clear`** again once after **Setup → Plugins** changes.

Official GLPI source install and tooling context: **[GLPI `INSTALL.md`](https://github.com/glpi-project/glpi/blob/main/INSTALL.md)**. For all commands: `php bin/console list`.

### Login works at Microsoft/Google but GLPI still refuses

- **Authorized domains** might block the user’s email.  
- **Allow automatic registration** might be off and no GLPI user matches.  
- **Field mappings** might not supply email/username — see [Field mappings](field-mappings.md).

### The wrong GLPI user is picked

Tighten **Field mappings** so **ID** and **Email** are unambiguous; prefer stable ids (`sub`, provider id) for **ID**.

---

## Profile and avatar

### Where do I see what the IdP sends?

Use **Test Single Sign-On** on the provider (as an admin). For deeper detail, use your IdP’s logs or a staging tenant — avoid logging secrets in production.

### Unusual JSON field names

Add explicit mappings; use bracket syntax for odd keys — see [Field mappings](field-mappings.md).

### Picture never updates

Check **Synchronization mode**, the **Avatar URL** mapping, and **Photo** authorization settings in [Configuration](configuration.md).

---

## TLS

### Certificate errors when calling the IdP

Fix certificates or trust store first. Turning off **SSL verify** on the provider is only for isolated tests, not production.

---

## Getting help

Open an issue with **GLPI version**, **plugin version**, provider type (**Generic**, **Azure**, …), and **redacted** settings (never paste **Client Secret**).

- [GitHub — issues](https://github.com/edgardmessias/glpi-singlesignon/issues)

---

## Documentation index

- [Documentation home](README.md)  
- [Installation](installation.md)  
- [Identity providers](identity-providers.md)  
- [Configuration](configuration.md)  
- [Field mappings](field-mappings.md)  
