# Configuration

Open **Setup → Single Sign-On** in GLPI. Each **provider** is one OAuth application at your identity provider (Microsoft Entra ID, Google Workspace, Okta, Keycloak, etc.).

The sections below follow the form in the GLPI interface.

---

## General

### Name

Label shown to you in GLPI and used for the **Login with …** button on the login page.

### Comments

Optional notes for administrators only.

### SSO Type

Pick a **preset** (Azure, Google, …) to pre-fill standard OAuth addresses and scopes, or **Generic** to type everything yourself. For IdPs not listed, use **Generic** and copy endpoints from their documentation.

### Active

Inactive providers are hidden on the login page and will not complete login.

### Client ID / Client Secret

The application credentials from your identity provider’s admin console. Treat the secret like a password.

---

## OAuth URLs and HTTP

### Scope

What the provider is allowed to return (for example profile and email). Presets suggest a default; your IdP documentation may require changes.

### Extra Options

Extra parameters added to the **authorization** link (for example forcing login again). Format and effect depend on the provider.

### Authorize URL / Access Token URL / Resource Owner Details URL

In simple terms:

1. **Authorize** — where the user is sent to sign in and approve access.  
2. **Access token** — where GLPI exchanges a short-lived **code** for a **token**.  
3. **Resource owner details** — where GLPI calls with that token to download the **user profile** (name, email, etc.).

Preset types hide these in the form but still use the built-in values unless you switch to **Generic**.

### Resource Owner Authorization

How the access token is sent when GLPI requests the user profile. Most providers expect **Bearer**. Use other modes only if the API documentation says so.

### Resource Owner Custom Headers

Optional extra HTTP headers for that profile request (one per line). Some placeholders can be filled with the access token; see the hint in the form.

### SSL verify host / SSL verify peer

Leave **enabled** in production so invalid or fake certificates are rejected. Disable only in a test lab if you must use self-signed TLS.

---

## Login behavior

### Is default

Marks the preferred provider when several are active (ordering / default choice where the plugin uses it).

### PopupAuth

Runs the login flow in a popup instead of leaving the page entirely (if supported for your setup).

### SplitDomain

If the login value looks like an email (`user@company.com`) and this is on, GLPI uses only **`user`** as the username. If off, the full string may be kept.

### Authorized domains

Optional comma-separated **patterns**. The email or username must **end with** one of these patterns (case-insensitive). Example: `@company.com` or `company.com`, depending on what your IdP sends. Leave empty to allow all (other rules still apply).

### Use Email as Login

Use the email from the profile as the GLPI login name when appropriate.

### Split Name

If the provider sends a single **full name**, GLPI can try to split it into first and last name (together with field mappings).

---

## Registration

Options used when **new GLPI users** may be created after SSO.

### Allow automatic registration

Creates a GLPI user on first successful SSO when no match exists (subject to GLPI permissions and plugin rules).

### Confirm registration before creating account

Shows a confirmation step before creating the user.

### Default entity for new users

Which **entity** new users belong to when nothing else assigns one.

### Match entity by email domain (entity name = domain)

Tries to place the user in an entity whose **name** equals the domain part of their email.

### Default profile when GLPI has no default

Profile assigned when GLPI would otherwise leave the user without one.

---

## User photo synchronization

### Synchronization mode

Whether to copy the picture from the provider: never, only if GLPI has no photo yet, or on every login.

### Photo Authorization / Photo Custom Headers

Same idea as for the profile request, but used when **downloading the image file** from the avatar URL (some APIs need different headers).

---

## Personalization

### Background color / Color / Picture

Customize how this provider’s button looks on the login page.

---

## Callback URL

After you save a provider, GLPI shows the **Callback URL** (redirect URI) to register in your identity provider’s OAuth application.

That address is built from GLPI’s own base URL. If it is wrong (internal server name, `http` instead of `https`, missing subpath), fix **GLPI’s general configuration** first — see the FAQ: **[The callback URL shows the wrong host or protocol](faq.md#the-callback-url-shows-the-wrong-host-or-protocol)**.

### Test Single Sign-On

Administrators can run a test from the provider form to inspect the flow; use it together with your IdP’s logs when troubleshooting.

---

## Related topics

- [Identity providers](identity-providers.md) — where to create OAuth apps (Azure, Google, …)  
- [Field mappings](field-mappings.md) — how profile JSON becomes email, id, name, photo  
- [FAQ](faq.md) — redirect mismatch, proxy, SSL  
