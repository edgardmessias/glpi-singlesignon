# Field mappings

After login, GLPI receives a **user profile** from your identity provider (usually JSON with fields like email, name, and id). **Field mappings** tell this plugin **where** to read each piece of information in that profile.

You edit them on the **Field mappings** tab of each provider. **Save the provider first** — the tab appears only after the record exists.

---

## Why this matters

Each vendor uses different field names. Microsoft Graph, Google, GitHub, and custom OIDC servers all look slightly different. Mappings bridge that gap so GLPI always gets a reliable **email**, **login**, **remote id**, and optional **name** / **photo URL**.

---

## What each column means

| Column | Meaning |
|--------|---------|
| **Field type** | What GLPI uses the value for (see table below). |
| **JSONPath** | A small expression that points to one value inside the profile JSON. |
| **Active** | If off, this row is ignored. |
| **Sort order** | Lower numbers are tried **first**. The first mapping that returns a value **wins** for that field type. |

### Field types

| Type | Role in GLPI |
|------|----------------|
| **ID** | Stable id from the provider (links the same person across logins). Often a numeric id or a `sub` value. |
| **Username** | Login name when you are **not** using “email as login”. |
| **Email** | Email address; used heavily to find or create users. |
| **First name** / **Last name** | Given name and family name in GLPI. |
| **Full name** | One string; can be split if **Split Name** is enabled on the provider. |
| **Avatar URL** | Address of the user’s picture; photo sync uses this URL. |

---

## How GLPI picks a value

For each type (for example **Email**):

1. Try your **active** mappings in **sort order** until one returns a non-empty value.  
2. If none work, use the plugin’s **preset defaults** for that provider type (Azure, Google, …).  
3. If still empty, use **generic** built-in guesses (common keys like `email`, `sub`, …).

So your custom rows are tried **before** the defaults.

---

## JSONPath in plain language

JSONPath is a way to write “go to this field inside the JSON”. Examples:

| Expression | Typical meaning |
|------------|-----------------|
| `$.email` | The `email` field at the top level. |
| `$.sub` | Common in OpenID Connect for a subject id. |
| `$.userPrincipalName` | Often seen with Microsoft. |
| `$['email-address']` | When the key contains a hyphen or special characters. |
| `$.data.username` | A value nested inside `data`.

If a path points to a list, the plugin uses the first usable value it finds.

**Tip:** Use **Test Single Sign-On** on the provider. If email or login stays empty, your mappings are the first place to check.

---

## Practical examples

**Different email field** — Profile has `contact.work_email` instead of `email`: add an **Email** mapping with a path to that field and put it **above** weaker defaults (lower sort order).

**Prefer one field over another** — Put the preferred path first (lower **sort order**); put fallbacks after.

**Avatar does not download** — Ensure **Avatar URL** is a full `https://…` address the GLPI **server** can reach; adjust **Photo Authorization** if the image URL needs special headers (see [Configuration](configuration.md)).

---

## Related

- [Configuration](configuration.md) — OAuth URLs, registration, photo sync  
- [FAQ](faq.md) — login fails, wrong user, redirect issues  
