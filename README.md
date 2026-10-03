# FPC — Future Planning for the Child

Demo platform. **Foundation only — awaiting the brief.**

The architecture, conventions and design system are carried over from Eval360
(`../atbu`). The domain is not: every indicator, register and report from that
project has been stripped out. What remains is the scaffolding and the rules.

- **[CLAUDE.md](CLAUDE.md)** — conventions, patterns and the foot-guns worth knowing
- **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)** — tokens, typography, page patterns

## Getting it running

```bash
cp .env.example .env
php scripts/generate_key.php        # paste the output into APP_KEY
# create the database named in DB_NAME, then point the web root at public/
```

Requires PHP 8.1+ with pdo_mysql, mbstring, curl, fileinfo, openssl. No Composer
install — there are no packages, by design.

## What is here

| | |
|---|---|
| `bootstrap/` `config/` `db/` | Entry point, config resolution, PDO helpers |
| `lib/layout.php` | Page shell: head, fonts, design tokens, topbar, sidebar |
| `lib/sidebar.php` | Role-aware nav — render engine ready, **tree empty** |
| `lib/rbac.php` | Permissions and audit log |
| `lib/notifications.php` | Bell contract — **no checks yet** |
| `lib/coming_soon.php` | Styled stub page for routes not yet built |
| `modules/` | **Empty** — awaiting the brief |

## Next

Paste the brief. It gets mapped to modules and permission keys before any code is
written.
