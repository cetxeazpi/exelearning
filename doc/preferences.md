# System Preferences

This document describes the new, centralized System Preferences used to configure eXeLearning at runtime. The goal is to replace scattered “hard-coded” defaults and dedicated singletons with a single, typed key–value store that is easy to read and write via UI, CLI, templates, and API.

## Overview

- Keys are defined once in a registry with metadata (type, group, default, optional labels/help).
- Values are stored in the database (table `system_preferences`) and cached for fast reads.
- Read access is available from PHP/Twig through a small service and Twig functions.
- Write access is available through:
  - Admin UI (EasyAdmin)
  - CLI commands
  - API Platform endpoints (admin-only)

## Registry and keys

- Code: `src/Config/SystemPref.php` (enum of keys) + `src/Config/Attribute/Setting.php` (metadata) + `src/Config/SystemPrefRegistry.php` (lookup helpers).
- Groups currently include:
  - `maintenance.*` (e.g., `maintenance.enabled`, `maintenance.message`, `maintenance.until`)
  - `additional_html.*` (HTML snippets injected in HEAD/BODY/footer)
  - `theme.*` (e.g., `theme.login_image_path`, `theme.favicon_path`, `theme.login_logo_path`)

Each key has a type (bool, string, int, float, date, datetime, html, …) and a default value used when the DB is empty.

## Storage and caching

- Entity: `src/Entity/net/exelearning/Entity/SystemPreferences.php` with columns:
  - `pref_key` (unique), `value` (text, nullable), `type` (string, nullable), timestamps and `updatedBy`.
- Service: `src/Service/net/exelearning/Service/SystemPreferencesService.php`
  - `get(string $key, mixed $default=null): mixed` returns the typed value (from cache/DB/registry default).
  - `set(string $key, mixed $value, ?string $type=null, ?string $updatedBy=null): void` persists and invalidates cache.
  - `delete(string $key): void` removes the stored value.

## Reading values (PHP / Twig)

- PHP: inject `SystemPreferencesService` and call `$prefs->get('theme.favicon_path')`.
- Twig: two helpers are available via `src/Twig/SystemPreferencesExtension.php`:
  - `{{ sys_pref('theme.favicon_path') }}`
  - `{{ sys_pref_bool('maintenance.enabled') ? 'ON' : 'OFF' }}`

Example (templates):

```twig
{% set login_img = sys_pref('theme.login_image_path') ?: asset('images/default.jpg') %}
```

## Managing values

### Admin UI (EasyAdmin)

- Go to Admin → Preferences.
- Use the left menu entries (Additional HTML, Theme, Maintenance) or open `/admin` and select the “Preferences” sections.
- You can also filter by prefix in the listing using the built-in links:
  - `additional_html.*`, `theme.*`, `maintenance.*`

#### Theme file preferences (type: `file`)

The theme-related preferences are declared as type `file` so they are easy to manage from the UI:

- Keys and purpose:
  - `theme.login_image_path`: background image in the login page.
  - `theme.login_logo_path`: logo in the login page.
  - `theme.favicon_path`: favicon displayed by browsers.

- How it works in the UI:
  - The EasyAdmin form shows a file upload input, a “Remove current” toggle, and a disabled field with the current path.
  - When you upload a file, it is stored under `public/assets/custom/` and the preference value is set to a relative URL like `/assets/custom/<generated-name>.<ext>`.
  - When you remove the file, the preference value is set to `null` and the previous file is deleted if it was inside `/assets/custom/`.

- Notes:
  - The file storage/cleanup logic is handled by `SystemPreferencesCrudController`.
  - Files are stored persistently under `FILES_DIR/system_preferences_files` and exposed as `/system_prefs/<filename>` via a symlink in `public/`. This ensures they survive container restarts when `FILES_DIR` is a volume.
  - Templates read these values via `sys_pref('theme.*')` and should fall back to a default asset if the value is null.
  - API/CLI write operations accept a path string; file uploads are managed via the admin UI (there is no direct API upload for these preferences).
  - Filenames include a short random hash to avoid predictable scans, e.g. `theme-login-image-<hash>-<timestamp>.png`.

### CLI

- Create missing keys and sync types from the registry:

```bash
bin/console app:prefs:sync
```

- Get a single key:

```bash
bin/console app:prefs:get maintenance.enabled
# → true|false|<string|null|number>
```

- Set a value (auto-casting with --type):

```bash
bin/console app:prefs:set maintenance.enabled true --type=bool
bin/console app:prefs:set maintenance.until '2025-09-30T23:59:59Z' --type=datetime
bin/console app:prefs:set theme.favicon_path '/assets/custom/favicon.ico' --type=string
```

- List current values (filter by group/prefix):

```bash
bin/console app:prefs:list --prefix=maintenance.
```

### API (admin-only)

API Platform exposes the registry-backed preferences for administrators:

- List: `GET /api/v2/system-preferences`
- Get one: `GET /api/v2/system-preferences/{key}`
- Update: `PUT /api/v2/system-preferences/{key}` with body `{ "value": <...>, "type": "bool|string|..." }`

Example:

```bash
curl -s -H 'Accept: application/json' -H 'Authorization: Bearer <ADMIN_JWT>' \
  http://localhost:8080/api/v2/system-preferences/maintenance.enabled

curl -s -X PUT -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <ADMIN_JWT>' \
  -d '{"value": false, "type": "bool"}' \
  http://localhost:8080/api/v2/system-preferences/maintenance.enabled
```

## Maintenance mode

The HTTP 503 screen for non-admin authenticated users is now controlled by preferences (instead of a dedicated entity):

- `maintenance.enabled` (bool)
- `maintenance.message` (string|null)
- `maintenance.until` (datetime|null)

The subscriber (`src/EventSubscriber/MaintenanceSubscriber.php`) reads these keys and sets `Retry-After` when applicable.

## Deprecation: Constants.php

We are progressively moving away from configuration in `src/Constants.php` to the new preferences system. Constants related to build-time paths and low-level framework integration will remain, but anything that represents user-configurable behavior or runtime branding should migrate to System Preferences.

- New equivalents already available:
  - Login image/logo paths → `theme.login_image_path`, `theme.login_logo_path`
  - Favicon path → `theme.favicon_path`
  - Additional HTML injection → `additional_html.head`, `.top`, `.footer`
  - Maintenance mode → `maintenance.*`

Action items:

1. Use `SystemPreferencesService` (or the Twig helpers) to read runtime options.
2. Prefer CLI/API/Admin UI to set values instead of editing code or environment constants.
3. When adding a new setting, declare a key in `SystemPref` with a `#[Setting(...)]` attribute; run `bin/console app:prefs:sync` once to seed.

This change aims to make eXe easier to configure without code changes and to keep defaults discoverable and centralized.
