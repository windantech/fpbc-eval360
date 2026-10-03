# FPC — Future Planning for the Child · CLAUDE.md

Last updated: 2026-10-03 (scaffold)

> **Status: foundation only.** The platform skeleton, conventions and design system
> are in place. No FPC domain code exists yet — the brief has not been written.
> Do not invent modules, tables or features. Wait for the brief.

---

## What this is

A demo platform for **Future Planning for the Child (FPC)**.

The architecture, conventions and design system are carried over from **Eval360**
(`../atbu`), a monitoring & evaluation platform built for a World Bank Centre of
Excellence over roughly 26 sessions. What has been borrowed is the *scaffolding and
the hard-won rules*, not the domain — every DLI, safeguard register and World Bank
report has been stripped out.

The rules below are not style preferences. Most of them are written down because
breaking them cost real debugging time on the other project.

---

## Stack

**PHP 8.2 · MySQL/MariaDB via PDO · no framework · no Composer packages.**

The dependency-free constraint is deliberate and should be defended. Eval360 is
maintained by university ICT staff on shared hosting, and every dependency is one
more thing that can break a deployment they have to fix themselves. HTTP clients for
object storage, email and AI providers were each written in a few hundred lines
against `curl` rather than pulling in an SDK and a `vendor/` tree.

`composer.json` declares PHP extensions only. If you find yourself wanting a package,
say so explicitly and justify it — do not quietly add one.

Front end: server-rendered PHP, Bootstrap Icons via CDN, Google Fonts. No build step,
no bundler, no framework. Vanilla JS only.

---

## Project layout

```
bootstrap/   init.php (entry point), helpers.php
config/      env.php, app.php, features.php      — add more as config('<file>.<key>')
db/          connection.php, query.php
lib/         layout.php, sidebar.php, rbac.php, notifications.php, coming_soon.php
modules/     <module>/pages/*.php                — EMPTY, awaiting the brief
public/      web root — index.php, login.php, assets/
storage/     uploads/ logs/ cache/ exports/      — gitignored, outside the web root
scripts/     CLI utilities
seeds/       SQL seed data
docs/        setup and runbooks
```

**Document root points at `public/`.** The root `.htaccess` blocks direct access to
source directories as a fallback for when it does not.

---

## The rules

### Every page starts the same way

```php
declare(strict_types=1);
require_once __DIR__ . '/../../../bootstrap/init.php';
require_once __DIR__ . '/../../../lib/rbac.php';
require_once __DIR__ . '/../../../lib/layout.php';

if (!current_user_id()) { redirect(public_url('/login.php')); }
rbac_require('<module-key>', 'read');     // or 'full'
rbac_audit('view', '<entity>', $id, []);
```

`bootstrap/init.php` must be the **first** require in every page and every AJAX
endpoint. It defines `APP_ROOT`, loads `.env`, sets the timezone and error handling,
loads the helpers, connects the database and starts the session.

### Helpers available everywhere

`config()` `env()` `feature()` · `e()` (escape) `clean()` (strip tags + trim) ·
`csrf_token()` `csrf_check()` · `app_url()` `public_url()` `redirect()` ·
`current_user_id()` `logger()` `json_response()` `storage_path()`

`e()` on every value that reaches HTML. `clean()` on text input — but **never on an
API key, token or password**, because it strips characters that are legitimate in a
secret.

### Database

`db()` returns the PDO connection. Query through the helpers in `db/query.php`:
`db_one()` `db_all()` `db_value()` `db_column()` `db_insert()` `db_update()`
`db_delete()` `db_exists()`. All use prepared statements. Anything hand-rolled must
too — no string interpolation into SQL, ever.

> **Never name a page-level variable `$pdo`.**
> `db()` does `global $pdo; return $pdo;`. A page-level `$pdo = db_one(...)` silently
> overwrites the connection, and every later query dies with
> `TypeError: db(): Return value must be of type PDO, array returned`. Use `$row`,
> `$record`, `$item`. This cost an afternoon on the other project.

`db_insert()` adds `org_id = 1` automatically when you omit it. Single-tenant
convention: there is no organisations table, and `org_id` is a plain integer with no
foreign key. Keep the column — it makes multi-tenancy possible later without a
rewrite — but do not build an org switcher nobody asked for.

### Schema: zero migrations

There is no migration runner. **Each page creates and alters the tables it owns**, at
the top of the file:

```php
db()->exec("CREATE TABLE IF NOT EXISTS fpc_example (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    org_id   INT NOT NULL DEFAULT 1,
    ...
) DEFAULT CHARSET=utf8mb4");
```

Adding a column to a table that already exists in production:

```php
foreach ([
    'new_col'   => "VARCHAR(64) NOT NULL DEFAULT ''",
    'other_col' => 'TEXT',
] as $col => $def) {
    try { db()->exec("ALTER TABLE `fpc_example` ADD COLUMN `$col` $def"); }
    catch (PDOException $e) { /* 1060 = duplicate column, already applied */ }
}
```

- **Do not use `ADD COLUMN IF NOT EXISTS`** — MariaDB-only, fails on MySQL.
- **Omit `AFTER <col>`** unless you know the existing layout. MySQL appends; the
  column works identically.
- If a page can be reached before its "owner" page has ever loaded, give it its own
  `CREATE TABLE IF NOT EXISTS` as a safety net. Pages should not depend on visit order.

Prefix FPC tables `fpc_` so they are distinguishable from anything inherited.

### Writes

**CSRF on every POST.** `<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">`,
validated with `csrf_check($_POST['_csrf'] ?? null)`.

**PRG — Post, Redirect, Get.** Validate, write, then `redirect()`. Never render after a
successful mutation; a refresh must not resubmit.

**Upserts.** Use `ON DUPLICATE KEY UPDATE` when an existing row should change. Use
`INSERT IGNORE` only for idempotent seeding where a duplicate means "already there,
skip". Confusing the two produces either lost edits or duplicate rows.

**Unchecked checkboxes send nothing**, not `0`. Use
`$flag = isset($_POST['field']) ? 1 : 0;` — never `(int)($_POST['field'] ?? 0)`.

### Permissions

`rbac_can($moduleKey)` returns `'full' | 'read' | 'none'`. `rbac_require()` enforces.

- **Deny by default.** A key not in a role's scope is blocked, not allowed.
- **`rbac_audit()`'s fourth argument must be an array.** Passing a string is a
  `TypeError` at runtime. `rbac_audit('view','thing',0,['k'=>$v])` or
  `rbac_audit('add','thing',$id,[])`.
- UI gating is for convenience. **The real check is server-side on the page itself**,
  and must be, because hiding a nav item hides nothing from someone with the URL.

Roles live in `fpc_roles` (scope as JSON), assignments in `fpc_user_roles`, the audit
trail in `fpc_access_log`.

### PHP 8.2 constraints that have bitten before

- `mb_str_pad()` does not exist until 8.3. Use `mb_strlen()` + `str_repeat()`.
- `ZipArchive` is **not** available on the target host — `.xlsx` cannot be read.
  Imports are CSV-only. Tell users to "Save As CSV".
- **`??` and `?:` are invalid inside `"{...}"` string interpolation.** They fail
  silently or throw a parse error. Always extract first:
  `$v = $arr[$k] ?? 'default'; echo "text {$v}";`

### Secrets

- `.env` is **gitignored here.** The other project tracked it deliberately and ended
  up with live API keys in git history. Do not repeat that.
- Never log, echo, or put a key in an error message. Mask for display: prefix plus
  last four characters.
- Secrets stored in the database are encrypted with `APP_KEY`
  (AES-256-CBC + HMAC-SHA256). Generate the key with `php scripts/generate_key.php`.
- Never store a presigned or signed URL. They expire; a stored one is a durable leak.
  Mint them per request, after the permission check.

### JavaScript

Vanilla, in the page. Patterns worth repeating:

- **Export via Blob URL, not `document.write`.** Modern browsers block
  `window.open('','_blank') + document.write()`. Build the HTML string, make a
  `Blob`, `URL.createObjectURL()`, open that. Include a `@media print` stylesheet and
  a visible "Print / Save as PDF" button.
- **An IIFE hides its functions from `onclick`.** Anything an inline handler calls
  must be exported: `window.fnName = fnName;` at the bottom of the IIFE. Forgetting
  it gives `ReferenceError: fnName is not defined`.
- **Bind buttons directly inside `<details>`.** Use
  `document.querySelectorAll('.js-x').forEach(b => b.addEventListener(...))`, not
  document-level delegation with `closest()`. The `<details>` toggle intercepts click
  bubbling in some browsers and silently breaks delegation for every button at once.
- **Pass PHP data to JS via `data-*` attributes** on a hidden config div, not by
  interpolating PHP inside `<script>`. One encoding problem in the interpolation
  breaks the whole script and every handler on the page throws.

### A general lesson worth stating

On the other project, evidence files linked to indicators through a tag row storing
the indicator's **string code**, not its numeric id — because that is what the display
query joined on. Writing the id instead inserted perfectly cleanly and then rendered
nowhere.

The general rule: **when you add a row that makes something appear in the UI, read the
query that displays it and match that column exactly.** A silently invisible record is
far more expensive than an error.

---

## Design system

Full detail in [`DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md). The short version:

- **`lib/layout.php` renders the whole shell** — `<head>`, fonts, CSS tokens, topbar,
  sidebar, notification bell. Pages call `layout_start($title, $opts)` …
  `layout_end()` and supply only their own content. **No page writes `<html>` or
  `<head>`.**
- **All colour comes from CSS custom properties** declared once at the top of
  `layout.php`. No component hardcodes a hex. Rebranding is one token block.
- **Three typefaces, by role**: Fraunces (display/headings), Inter (UI/body),
  JetBrains Mono (codes, identifiers, figures).
- **Layout provides** `.btn` `.btn-primary` `.btn-ghost` `.btn-sm` `.btn-danger`
  `.card` `.card-head` `.section-title` `.page-title` `.kpi-row` `.data-table`.
  Page-specific primitives go in a per-page `<style>` block.
- **Semantic colour is status only** — red/amber/green mean something. Do not use
  them as decoration.

> The palette currently in `layout.php` is **provisional** — a considered blue with a
> plum accent, chosen so the scaffold does not look like the green-and-gold ATBU
> system. Replace it when FPC's real brand colours are known.

---

## Adding navigation

Edit `sidebar_programmes()` in `lib/sidebar.php`. Nothing else. The render engine
handles permission gating, the accordion and the mobile drawer.

A "programme" is one top-level area with its own nav tree. Keep a single one unless
FPC genuinely runs separate programmes that staff switch between — the switcher is
dead weight with one entry.

---

## What exists

| Path | State |
|---|---|
| `bootstrap/`, `config/`, `db/` | Ported, working, FPC-renamed |
| `lib/layout.php` | Ported — shell + design tokens, palette provisional |
| `lib/sidebar.php` | Render engine ported; **nav tree empty** |
| `lib/rbac.php` | Ported; **landing-page priority list empty** |
| `lib/notifications.php` | Contract stub; **no checks** |
| `lib/coming_soon.php` | Ported — renders a styled stub page |
| `modules/` | **Empty** |
| `public/` | `.htaccess` only — no `index.php` or `login.php` yet |

Not yet carried over, because they were only worth building once there was something
to use them for: authentication pages, the admin module (users, roles, config), the
evidence/file vault, email, object storage, the AI client layer. All exist in
`../atbu` and can be ported when the brief calls for them.

---

## Working agreement

1. **Wait for the brief** before creating domain tables, modules or pages.
2. When the brief arrives, map it to modules and RBAC keys **before** writing code.
3. Follow the rules above. Where FPC genuinely needs something different, change the
   rule here and say why — do not leave the document describing one thing while the
   code does another.
