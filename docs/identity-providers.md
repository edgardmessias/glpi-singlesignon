# Configuring each identity provider

This guide tells you **where to click** at each vendor and **what to paste into GLPI**. It matches the presets built into the plugin (same URLs and default scopes as the shipped configuration).

---

## Before you start (GLPI)

1. Set GLPI’s **URL of the application** correctly (**Setup → General**). Otherwise the **Callback URL** will be wrong — see [FAQ](faq.md#the-callback-url-shows-the-wrong-host-or-protocol).
2. In GLPI: **Setup → Single Sign-On** → add a provider (or open an existing one).
3. Choose the **SSO Type** listed for your vendor below (this fills OAuth addresses automatically).
4. **Save** the provider, then copy the **Callback URL** from the form. You will paste it into the vendor’s “redirect URI” field (name varies: *Redirect URI*, *Authorized redirect URI*, *Valid OAuth Redirect URIs*, etc.).
5. Paste the vendor’s **Client ID** and **Client Secret** into GLPI’s **Client ID** and **Client Secret** fields, **save** again, then test from the login page.

Default **Scope** values come from the plugin; you can adjust them in GLPI if your IdP documentation requires different scopes.

---

## Quick reference

| SSO Type in GLPI | Where you usually create the app | Official starting point |
|------------------|-----------------------------------|-------------------------|
| **Azure** | Microsoft Entra admin center (Azure AD) | [Microsoft identity platform](https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app) |
| **Google** | Google Cloud Console | [Google OAuth / OpenID](https://developers.google.com/identity/protocols/oauth2) |
| **GitHub** | GitHub account → Developer settings | [GitHub OAuth apps](https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app) |
| **Facebook** | Meta for Developers | [Facebook Login](https://developers.facebook.com/docs/facebook-login) |
| **Instagram** | Meta for Developers (Instagram product) | [Instagram platform docs](https://developers.facebook.com/docs/instagram-basic-display-api/) |
| **LinkedIn** | LinkedIn Developer Portal | [LinkedIn OAuth 2.0](https://learn.microsoft.com/en-us/linkedin/shared/authentication/authentication) |
| **Generic** | Your IdP’s admin console | See [Official docs for common IdPs](#generic-keycloak-okta-auth0-and-others) below |

---

## Microsoft Azure (Entra ID)

**GLPI:** SSO Type = **Azure**.

### Where to go

1. Sign in to **[Microsoft Entra admin center](https://entra.microsoft.com)** (or [Azure Portal](https://portal.azure.com) → **Microsoft Entra ID**).
2. **Identity** → **Applications** → **App registrations** → **New registration**.

### What to fill at Microsoft

| Field | What to enter |
|-------|----------------|
| **Name** | Any label (e.g. `GLPI SSO`). |
| **Supported account types** | Choose who can log in (single tenant, multitenant, or personal Microsoft accounts), per your policy. |
| **Redirect URI** | Platform: **Web**. URI: paste GLPI’s **Callback URL** exactly. |

After creation:

3. Open **Overview** → copy **Application (client) ID** → this is GLPI **Client ID**.
4. **Certificates & secrets** → **New client secret** → copy the **Value** (not the Secret ID) → GLPI **Client Secret** (paste once; Microsoft may show it only briefly).

### What to fill in GLPI

| GLPI field | Value |
|------------|--------|
| **Client ID** | Application (client) ID |
| **Client Secret** | The new client secret value |
| **Scope** | Default is usually `User.Read` (Microsoft Graph). Add more only if your policy requires it. |

### Notes

- The preset uses the `common` authority in the authorize/token URLs. For **single-tenant** only, some organizations replace `common` with their **tenant ID** in those URLs (advanced; use SSO Type **Generic** if you edit URLs manually).
- Users need a mailbox or profile fields where Graph returns **mail** / **userPrincipalName**; otherwise adjust [Field mappings](field-mappings.md).

---

## Google

**GLPI:** SSO Type = **Google**.

### Where to go

1. Open **[Google Cloud Console](https://console.cloud.google.com/)**.
2. Select or create a **project**.
3. **APIs & Services** → **OAuth consent screen** — configure app name, user type (internal/external), and scopes if the wizard asks (the plugin default scope is `openid email profile`).
4. **APIs & Services** → **Credentials** → **Create credentials** → **OAuth client ID**.

### What to fill at Google

| Field | What to enter |
|-------|----------------|
| **Application type** | **Web application**. |
| **Authorized redirect URIs** | Add one entry: GLPI’s **Callback URL** (exact copy). |

Then copy **Client ID** and **Client secret** from the credentials page.

### What to fill in GLPI

| GLPI field | Value |
|------------|--------|
| **Client ID** | Google **Client ID** |
| **Client Secret** | Google **Client secret** |
| **Scope** | Leave default or use `openid email profile` unless Google’s doc asks otherwise. |

### Notes

- External apps may need **verification** if you use sensitive scopes or many users.
- Workspace admins can restrict which clients are allowed.

---

## GitHub

**GLPI:** SSO Type = **GitHub**.

### Where to go

1. While logged into GitHub, open **Settings** → **Developer settings** → **OAuth Apps** → **New OAuth App**  
   (or use: [Register a new OAuth app](https://github.com/settings/developers)).

### What to fill at GitHub

| Field | What to enter |
|-------|----------------|
| **Application name** | Any name (e.g. `GLPI`). |
| **Homepage URL** | Your GLPI public URL (front page). |
| **Authorization callback URL** | GLPI **Callback URL** (single line, exact match). |

Create the app, then note **Client ID** and generate a **Client secret**.

### What to fill in GLPI

| GLPI field | Value |
|------------|--------|
| **Client ID** | GitHub **Client ID** |
| **Client Secret** | GitHub **Client secret** |
| **Scope** | Default `user:email` lets the API expose the primary email when permitted. |

### Notes

- If the user keeps email private, **`email` may be empty** in the profile; consider **Use Email as Login** / field mappings / organization rules accordingly.
- For **GitHub Enterprise Server**, URLs differ; use SSO Type **Generic** and your server’s OAuth endpoints.

---

## Facebook

**GLPI:** SSO Type = **Facebook**.

### Where to go

1. **[Meta for Developers](https://developers.facebook.com/)** → **My Apps** → create or select an app.
2. Add the **Facebook Login** product if prompted.
3. **Facebook Login** → **Settings** (or **Client OAuth settings**).

### What to fill at Meta

| Setting | What to enter |
|---------|----------------|
| **Valid OAuth Redirect URIs** | GLPI **Callback URL** (exact). |
| **Login** / app mode | Development vs Live affects who can sign in; switch to Live when ready. |

From **Settings → Basic**: copy **App ID** and **App Secret**.

### What to fill in GLPI

| GLPI field | Value |
|------------|--------|
| **Client ID** | Facebook **App ID** |
| **Client Secret** | Facebook **App Secret** |
| **Scope** | Default `public_profile,email` — email requires app review for some use cases. |

### Notes

- New apps and data use policies change often; follow Meta’s current checklist for **email** and **public_profile**.
- Users must allow email permission for email-based matching in GLPI.

---

## Instagram

**GLPI:** SSO Type = **Instagram**.

### Where to go

Instagram login APIs are tied to **Meta** developer apps. Start from **[Meta for Developers](https://developers.facebook.com/)** and the current **Instagram** product documentation (Basic Display has evolved; confirm which API your app uses).

### What to fill

| Item | Detail |
|------|--------|
| **Redirect URI** | GLPI **Callback URL**. |
| **Client ID / Secret** | From the Meta app’s Instagram product settings (names may be App ID / App Secret). |

### What to fill in GLPI

| GLPI field | Value |
|------------|--------|
| **Client ID** / **Client Secret** | From the Meta developer app |
| **Scope** | Plugin default is `basic` (legacy naming); your app must expose the scopes Meta actually grants. |

### Notes

- Instagram/Facebook APIs and products change frequently. Validate against Meta’s **current** docs; you may need **Generic** URLs if Meta replaces endpoints.
- The plugin’s preset expects a classic profile shape; use [Field mappings](field-mappings.md) if JSON layout differs.

---

## LinkedIn

**GLPI:** SSO Type = **LinkedIn**.

### Where to go

1. **[LinkedIn Developers](https://www.linkedin.com/developers/)** → **Create app**.
2. Fill company and app details as required by LinkedIn.
3. **Auth** tab → **OAuth 2.0 settings**.

### What to fill at LinkedIn

| Field | What to enter |
|-------|----------------|
| **Redirect URLs** | GLPI **Callback URL**. |
| **Client ID** / **Client Secret** | Shown on the **Auth** tab (names may be *Client ID* and *Primary Client Secret*). |

Request access to **Sign In with LinkedIn** (and email) products if the portal offers them; scopes must match what LinkedIn approves for your app.

### What to fill in GLPI

| GLPI field | Value |
|------------|--------|
| **Client ID** | LinkedIn **Client ID** |
| **Client Secret** | LinkedIn **Client Secret** |
| **Scope** | Plugin default mentions `r_liteprofile` and `r_emailaddress`; LinkedIn has renamed/deprecated APIs — use the scopes **LinkedIn’s documentation** lists for your app version. |

### Notes

- LinkedIn’s API versioning may require you to adjust **Resource Owner** URL or **Field mappings**; use **Test Single Sign-On** and [Field mappings](field-mappings.md) if email or name is missing.

---

## Generic (Keycloak, Okta, Auth0, and others)

**GLPI:** SSO Type = **Generic**.

### Where to go

In your IdP admin UI, create an **OpenID Connect** or **OAuth 2.0** “client”, “application”, or “integration”. You need:

- **Authorization** (authorize) endpoint  
- **Token** endpoint  
- **UserInfo** endpoint (or any URL that returns the signed-in user as JSON for the access token)

Copy GLPI’s **Callback URL** into the IdP’s allowed **redirect URI** / **callback URL** list.

### Official documentation for common services

Use these entry points to find **create client**, **redirect URI**, and **endpoints** (often under “OpenID Connect” or “OAuth 2.0”):

| Service | Official documentation |
|---------|-------------------------|
| **Keycloak** | [Server Administration Guide — OIDC clients](https://www.keycloak.org/docs/latest/server_admin/#oidc-clients) · [Securing applications — OIDC](https://www.keycloak.org/docs/latest/securing_apps/#_oidc) |
| **Okta** | [OAuth 2.0 and OpenID Connect overview](https://developer.okta.com/docs/concepts/oauth-openid/) · [Implement authorization code flow](https://developer.okta.com/docs/guides/implement-grant-type/authcode/main/) |
| **Auth0** | [Applications in Auth0](https://auth0.com/docs/get-started/applications) · [Register regular web applications](https://auth0.com/docs/get-started/applications/application-settings#register-regular-web-applications) |
| **Amazon Cognito** | [User pool app integration](https://docs.aws.amazon.com/cognito/latest/developerguide/cognito-user-pools-app-integration.html) · [OAuth 2.0 and OIDC](https://docs.aws.amazon.com/cognito/latest/developerguide/cognito-user-pools-oauth-idp.html) |
| **Google Workspace / Cloud** | (Preset **Google** in GLPI is easiest.) [Using OAuth 2.0 to access Google APIs](https://developers.google.com/identity/protocols/oauth2) |
| **Microsoft Entra ID** | (Preset **Azure** in GLPI is easiest.) [Microsoft identity platform](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow) |
| **OneLogin** | [OpenID Connect](https://developers.onelogin.com/openid-connect) · [OAuth 2.0](https://developers.onelogin.com/api-docs/2/oauth20-tokens/oauth20-token-overview) |
| **Ping Identity** | [OpenID Connect developer guide](https://docs.pingidentity.com/developer-resources/openid_connect_developer_guide/index.html) · [PingOne — OpenID Connect / OAuth 2 APIs](https://developer.pingidentity.com/pingone-api/foundations/auth-apis-overview/openid-connect-oauth-2.html) |
| **JumpCloud** | [SSO with OIDC](https://jumpcloud.com/support/sso-with-oidc) · [OIDC overview](https://jumpcloud.com/support/oidc-overview) |
| **Salesforce** | [OAuth 2.0 web server flow](https://help.salesforce.com/s/articleView?id=sf.remoteaccess_oauth_web_server_flow.htm) |

Vendor UIs differ, but you almost always set: **client ID**, **client secret**, **redirect URI** = GLPI callback, and sometimes **allowed grant types** (authorization code).

### What to fill in GLPI

| GLPI field | What to enter |
|------------|----------------|
| **Authorize URL** | Authorization endpoint from the IdP. |
| **Access Token URL** | Token endpoint. |
| **Resource Owner Details URL** | UserInfo or equivalent profile URL (must return JSON the plugin can map). |
| **Scope** | Usually `openid email profile` for OIDC; follow the IdP’s examples. |
| **Client ID** / **Client Secret** | From the IdP application/client registration. |
| **Callback URL** | Still copied from GLPI into the IdP’s **redirect URI** list. |

### Notes

- After the first save, tune **[Field mappings](field-mappings.md)** to match your IdP’s JSON (claims may use other names than `email` or `sub`).
- If your IdP publishes a **discovery** document (`.well-known/openid-configuration`), the JSON lists the exact **authorization_endpoint**, **token_endpoint**, and **userinfo_endpoint** URLs to paste into GLPI.

---

## After configuration

- Turn **Active** on, **save**, then test **Login with …** on the GLPI login page.
- If redirect fails, see [FAQ](faq.md) (callback URL, `https`, GLPI base URL).
- If login works but user data is wrong, see [Field mappings](field-mappings.md) and [Configuration](configuration.md).

---

## Related

- [Configuration](configuration.md) — all GLPI provider options  
- [Installation](installation.md) — install the plugin  
- [FAQ](faq.md) — troubleshooting  
