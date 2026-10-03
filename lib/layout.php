<?php
/**
 * lib/layout.php — Shared platform shell.
 *
 * Usage:
 *   layout_start('Page title', ['active_view' => 'ea-dashboard']);
 *   // ...content...
 *   layout_end();
 *
 * Options:
 *   active_view  — highlights the matching sidebar nav item
 *   page_title   — overrides the <title> tag
 *   no_sidebar   — render without sidebar (error pages, etc.)
 */

require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/notifications.php';

if (!function_exists('layout_start')) {

    function layout_start(string $title, array $opts = []): void
    {
        $activeView  = $opts['active_view'] ?? '';
        $noSidebar   = !empty($opts['no_sidebar']);
        $pageTitle   = $opts['page_title'] ?? $title;

        $clientName  = config('app.client.name', 'FPC');
        $clientSlug  = config('app.client.slug', 'fpc');

        $user        = db_one("SELECT id, full_name, email FROM users WHERE id = ?", [current_user_id()]);
        $role        = rbac_current_role();
        $userInitials = '??';
        if (!empty($user['full_name'])) {
            $parts = preg_split('/\s+/', trim($user['full_name']));
            $userInitials = strtoupper(substr($parts[0] ?? '?', 0, 1) . substr(end($parts) ?: '', 0, 1));
        }

        $notifications   = get_notifications();
        $notifBadgeCount = notifications_badge_count($notifications);
        $notifUrgentCount = count(array_filter($notifications, fn($n) => $n['type'] === 'urgent'));

        $programme = current_programme();
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e($pageTitle) ?> · <?= e($clientName) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* ── Design tokens ─────────────────────────────────────────── */
/* ── FPC identity ─────────────────────────────────────────────────────
   PROVISIONAL palette — replace with FPC's real brand colours when known.
   Changing identity should mean changing this block and nothing else; no
   component below hardcodes a colour.

   --green / --green-deep / --green-soft keep their names because the whole
   component layer references them. They are the PRIMARY ramp, not literally
   green. Renaming them is a find-and-replace across this file; recolouring
   them is three lines. Recolour.
   ───────────────────────────────────────────────────────────────────── */
:root {
  --bg:          #f4f6f8;   /* cool neutral ground, slight blue bias */
  --bg-card:     #ffffff;
  --bg-muted:    #e7ecf1;
  --ink:         #10212e;
  --ink-soft:    #3b4d5c;
  --ink-mute:    #6b7d8a;
  --line:        #cdd7e0;
  --line-soft:   #e1e8ee;

  /* PRIMARY ramp */
  --green:       #1b5e8f;   /* primary — buttons, links, active nav */
  --green-deep:  #123f61;   /* deepest — sidebar, brand mark */
  --green-soft:  #e3eef6;   /* tint — chips, soft fills */

  /* ACCENT — used sparingly, for one thing at a time */
  --gold:        #7d4a6b;
  --gold-soft:   #f2e7ee;

  /* SEMANTIC — status only. Never use these as decoration. */
  --red:         #a32424;
  --red-soft:    #f7e2e0;
  --amber:       #b3700d;
  --amber-soft:  #fbeed2;

  /* AI features */
  --ai:          #0e7490;
  --ai-soft:     #d5eff5;

  --sidebar-bg:  #123f61;
  --shadow:      0 1px 2px rgba(11,31,23,.04), 0 0 0 1px rgba(11,31,23,.06);
  --shadow-lg:   0 10px 32px rgba(11,31,23,.10), 0 0 0 1px rgba(11,31,23,.06);
  --radius:      12px;
}

/* ── Reset ─────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
  height: 100vh;
  overflow: hidden;        /* page itself never scrolls — children do */
  display: flex;
  background: var(--bg);
  color: var(--ink);
  font-family: 'Inter', system-ui, sans-serif;
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}
a { color: var(--green); text-decoration: none; }
a:hover { text-decoration: underline; }
button { font-family: inherit; cursor: pointer; }

/* ── App shell ─────────────────────────────────────────────── */
.sidebar {
  width: 248px;
  height: 100vh;           /* fixed to viewport */
  height: 100dvh;          /* dvh: correct on mobile with dynamic toolbars */
  overflow: hidden;        /* brand/filter/footer stay put; nav scrolls inside */
  background: var(--sidebar-bg);
  color: #d8e2dc;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  transition: width .22s cubic-bezier(.4,0,.2,1);
}
/* Brand, collapse button, filter and footer are fixed; only .sb-scroll scrolls */
.sidebar > .sb-brand,
.sidebar > .sb-mini-btn,
.sidebar > .sb-filter,
.sidebar > .sb-filter-empty,
.sidebar > .sb-footer { flex-shrink: 0; }
.sb-scroll {
  flex: 1 1 auto;
  min-height: 0;           /* allow the flex child to shrink so it can scroll */
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.1) transparent;
  overscroll-behavior: contain;
}
.sb-scroll::-webkit-scrollbar { width: 5px; }
.sb-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.14); border-radius: 4px; }
.sb-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.28); }
.sidebar.mini { width: 60px; }

/* main column also fills viewport and scrolls independently */
.main {
  flex: 1;
  min-width: 0;
  height: 100vh;
  overflow-y: auto;        /* content scrolls independently */
  display: flex;
  flex-direction: column;
  scrollbar-width: thin;
  scrollbar-color: var(--line) transparent;
}
.main::-webkit-scrollbar { width: 6px; }
.main::-webkit-scrollbar-thumb { background: var(--line); border-radius: 4px; }

/* topbar is sticky within .main's scroll context */
.topbar {
  background: var(--bg-card);
  border-bottom: 1px solid var(--line);
  padding: 0 28px;
  height: 54px;
  display: flex;
  align-items: center;
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 20;
  flex-shrink: 0;
}
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }

.content { padding: 28px 32px 60px; }
.container { max-width: 100%; }

/* ── Sidebar internals ─────────────────────────────────────── */
.sb-brand {
  padding: 18px 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  display: flex;
  align-items: center;
  gap: 11px;
  min-height: 0;
}
.sb-brand-mark {
  width: 36px; height: 36px;
  border-radius: 8px;
  background: #fff;
  display: grid;
  place-items: center;
  font-family: 'Fraunces', serif;
  font-weight: 700;
  color: var(--green-deep);
  font-size: 17px;
  flex-shrink: 0;
}
.sb-brand-text .sb-brand-name {
  font-family: 'Fraunces', serif;
  font-weight: 600;
  color: #fff;
  font-size: 15px;
  line-height: 1.15;
  white-space: nowrap;
}
.sb-brand-text .sb-brand-sub {
  font-size: 10px;
  color: #9eb7a8;
  margin-top: 2px;
  letter-spacing: .04em;
  text-transform: uppercase;
  white-space: nowrap;
}
.sb-mini-btn {
  display: flex;
  justify-content: flex-end;
  padding: 6px 12px 0;
}
.sb-mini-btn button {
  background: rgba(255,255,255,.07);
  border: none;
  color: #9eb7a8;
  width: 26px; height: 22px;
  border-radius: 4px;
  font-size: 13px;
  display: grid;
  place-items: center;
  transition: all .12s;
}
.sb-mini-btn button:hover { background: rgba(255,255,255,.16); color: #fff; }

/* ── Sidebar quick-filter ──────────────────────────────────── */
.sb-filter { padding: 8px 14px 4px; }
.sb-filter-wrap { position: relative; }
.sb-filter-wrap .bi {
  position: absolute; left: 9px; top: 50%; transform: translateY(-50%);
  font-size: 12px; color: rgba(255,255,255,.4); pointer-events: none;
}
.sb-filter input {
  width: 100%; box-sizing: border-box;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 6px; color: #eaf2ec;
  font-size: 12px; font-family: inherit;
  padding: 7px 24px 7px 28px;
}
.sb-filter input::placeholder { color: rgba(255,255,255,.4); }
.sb-filter input:focus { outline: none; border-color: var(--gold); background: rgba(255,255,255,.1); }
.sb-filter .sb-filter-clear {
  position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: rgba(255,255,255,.45);
  cursor: pointer; font-size: 13px; line-height: 1; padding: 3px; display: none;
}
.sb-filter .sb-filter-clear:hover { color: #fff; }
.sb-filter-wrap.has-text .sb-filter-clear { display: block; }
.sb-filter-empty {
  display: none; padding: 8px 20px; font-size: 11.5px;
  color: rgba(255,255,255,.45); font-style: italic;
}
.sidebar.mini .sb-filter { display: none; }
/* When filtering: reveal matches even inside collapsed sections, hide the rest */
.sb-section.filter-hidden, .nav-item.filter-hidden { display: none !important; }
.sb-nav-group.filter-open { max-height: 2000px !important; }

.sb-section {
  padding: 16px 20px 5px;
  font-size: 10px;
  letter-spacing: .13em;
  text-transform: uppercase;
  color: #7a9788;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: color .15s;
}
.sb-section:hover { color: #b0c4b8; }
.sb-section.sb-section-fixed { cursor: default; pointer-events: none; margin-top: 8px; border-top: 1px solid rgba(255,255,255,.07); padding-top: 18px; }
.sb-section .sec-lbl { flex: 1; }
.sb-section .sec-chev { font-size: 9px; opacity: .55; transition: transform .22s cubic-bezier(.4,0,.2,1); flex-shrink: 0; }
.sb-section.closed .sec-chev { transform: rotate(-90deg); }

.sb-nav-group {
  overflow: hidden;
  max-height: 1200px;
  transition: max-height .32s cubic-bezier(.4,0,.2,1);
}
.sb-nav-group.collapsed { max-height: 0; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 9px 20px;
  color: #c7d4cc;
  font-size: 13px;
  font-weight: 500;
  border-left: 2px solid transparent;
  cursor: pointer;
  transition: all .12s;
  text-decoration: none;
  white-space: nowrap;
}
.nav-item:hover { background: rgba(255,255,255,.04); color: #fff; text-decoration: none; }
.nav-item.active {
  background: linear-gradient(90deg, rgba(255,255,255,.06), transparent);
  color: #fff;
  border-left-color: var(--gold);
}
.nav-item.readonly { opacity: .6; }
.nav-item i { font-size: 15px; width: 18px; text-align: center; flex-shrink: 0; }
.nav-item .nav-lbl { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.nav-item .nav-badge {
  margin-left: auto;
  font-size: 10px;
  background: var(--gold);
  color: var(--ink);
  padding: 1px 6px;
  border-radius: 8px;
  font-weight: 700;
  flex-shrink: 0;
}
.nav-item .nav-badge.urgent { background: var(--red); color: #fff; }

.sb-footer {
  margin-top: auto;
  padding: 14px 20px;
  border-top: 1px solid rgba(255,255,255,.08);
  font-size: 11px;
  color: #7a9788;
  line-height: 1.5;
}
.sb-footer .who { color: #fff; font-weight: 600; font-size: 12px; margin-bottom: 2px; }

/* Mini mode — icon-only */
.sidebar.mini .sb-brand-text,
.sidebar.mini .sb-section,
.sidebar.mini .sb-footer { display: none; }
.sidebar.mini .sb-brand { justify-content: center; padding: 16px 0 14px; }
.sidebar.mini .sb-mini-btn { justify-content: center; padding: 5px 0 0; }
.sidebar.mini .nav-item { padding: 9px 0; justify-content: center; border-left: none; border-right: 2px solid transparent; }
.sidebar.mini .nav-item.active { background: rgba(255,255,255,.06); border-left: none; border-right-color: var(--gold); }
.sidebar.mini .nav-lbl,
.sidebar.mini .nav-badge,
.sidebar.mini .bi-eye { display: none; }
.sidebar.mini .sb-nav-group { max-height: none !important; overflow: visible; }

/* ── Topbar internals ──────────────────────────────────────── */
.prog-switch-btn {
  background: var(--bg-muted);
  border: 1px solid var(--line);
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  display: inline-flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
}
.prog-switch-btn:hover { background: var(--line-soft); }
.prog-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

.prog-switcher-menu {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  background: var(--bg-card);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 6px;
  min-width: 290px;
  box-shadow: var(--shadow-lg);
  z-index: 100;
  display: none;
}
.prog-switcher-menu.open { display: block; animation: fadeInDown .15s ease; }
@keyframes fadeInDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

.prog-switcher-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 7px;
  color: var(--ink);
  text-decoration: none;
  transition: background .1s;
}
.prog-switcher-item:hover { background: var(--bg-muted); text-decoration: none; }
.prog-switcher-item.active { background: var(--green-soft); }
.prog-switcher-item .prog-name { font-size: 13px; font-weight: 600; }
.prog-switcher-item .prog-sub  { font-size: 11px; color: var(--ink-mute); margin-top: 1px; }

.topbar-crumb { font-size: 12px; color: var(--ink-mute); font-weight: 500; }

.role-pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px;
  background: var(--green-soft);
  border-radius: 99px;
  font-size: 11.5px;
  color: var(--green-deep);
  font-weight: 600;
}
.avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--green);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 11.5px;
  letter-spacing: .03em;
}
.btn-icon {
  background: transparent;
  border: none;
  color: var(--ink-soft);
  width: 34px; height: 34px;
  border-radius: 7px;
  display: grid;
  place-items: center;
  font-size: 17px;
  transition: all .12s;
}
.btn-icon:hover { background: var(--bg-muted); color: var(--ink); }
.logout-link { color: var(--ink-soft); font-size: 17px; }
.logout-link:hover { color: var(--red); text-decoration: none; }

/* ── Buttons ───────────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 16px;
  border: 1px solid var(--line);
  background: var(--bg-card);
  border-radius: 7px;
  font-family: 'Inter', inherit;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
  cursor: pointer;
  text-decoration: none;
  transition: all .12s;
}
.btn:hover { border-color: var(--ink-soft); text-decoration: none; }
.btn-primary { background: var(--green); color: #fff; border-color: var(--green); }
.btn-primary:hover { background: var(--green-deep); border-color: var(--green-deep); }
.btn-ghost { border: none; background: transparent; color: var(--ink-soft); }
.btn-ghost:hover { background: var(--bg-muted); color: var(--ink); }
.btn-danger { background: var(--red); color: #fff; border-color: var(--red); }
.btn-danger:hover { background: #7f1d1d; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.btn[disabled], .btn:disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }

/* ── Cards ─────────────────────────────────────────────────── */
.card {
  background: var(--bg-card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 20px 22px;
}
.card-head {
  padding: 14px 20px;
  border-bottom: 1px solid var(--line-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.card-head h3 { font-family: 'Fraunces', serif; font-size: 16px; font-weight: 600; color: var(--ink); }
.card-pad { padding: 18px 20px; }

/* ── Typography helpers ────────────────────────────────────── */
.section-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin: 4px 0 16px;
  gap: 16px;
}
.section-title {
  font-family: 'Fraunces', serif;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -.01em;
  color: var(--ink);
}
.section-sub { font-size: 12.5px; color: var(--ink-mute); margin-top: 3px; }
.section-actions { display: flex; gap: 8px; flex-shrink: 0; }

.page-title-strip { margin-bottom: 22px; }
.page-title {
  font-family: 'Fraunces', serif;
  font-size: 24px;
  font-weight: 600;
  letter-spacing: -.01em;
  color: var(--ink);
  margin: 0 0 4px;
}
.page-sub { font-size: 12.5px; color: var(--ink-mute); line-height: 1.5; }

/* ── Status pills ──────────────────────────────────────────── */
.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .02em;
}
.status-pill.open      { background: var(--green-soft); color: var(--green-deep); }
.status-pill.submitted { background: #eff6ff; color: #1d4ed8; }
.status-pill.locked    { background: var(--bg-muted); color: var(--ink-soft); }
.status-pill.resolved  { background: var(--green-soft); color: var(--green-deep); }
.status-pill.closed    { background: var(--bg-muted); color: var(--ink-soft); }
.status-pill.escalated { background: var(--red-soft); color: var(--red); }
.status-pill.under_review { background: var(--amber-soft); color: var(--amber); }

/* ── Tables ────────────────────────────────────────────────── */
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th {
  text-align: left; padding: 10px 16px;
  font-size: 11px; text-transform: uppercase;
  letter-spacing: .06em; color: var(--ink-mute);
  font-weight: 600;
  background: var(--bg-muted);
  border-bottom: 1px solid var(--line);
}
.data-table td { padding: 13px 16px; border-bottom: 1px solid var(--line-soft); vertical-align: top; }
.data-table tbody tr { cursor: pointer; transition: background .1s; }
.data-table tbody tr:hover { background: #f5f1e4; }

/* ── KPI row ───────────────────────────────────────────────── */
.kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
.kpi { background: var(--bg-card); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px 18px; transition: box-shadow .12s; }
.kpi:hover { box-shadow: var(--shadow); }
.kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-mute); font-weight: 600; margin-bottom: 8px; }
.kpi-value { font-family: 'Fraunces', serif; font-size: 28px; font-weight: 600; letter-spacing: -.02em; line-height: 1; }
.kpi-value small { font-size: 13px; color: var(--ink-mute); font-weight: 400; font-family: 'Inter', sans-serif; }
.kpi-delta { font-size: 11px; margin-top: 8px; display: flex; align-items: center; gap: 5px; }
.kpi-delta.up { color: var(--green); }
.kpi-delta.warn { color: var(--amber); }
.kpi-delta.down { color: var(--red); }

/* ── Progress ──────────────────────────────────────────────── */
.prog-bar { width: 100%; height: 6px; background: var(--bg-muted); border-radius: 99px; overflow: hidden; margin-top: 6px; }
.prog-bar .bar { height: 100%; border-radius: 99px; }
.prog-bar .bar.ok   { background: var(--green); }
.prog-bar .bar.warn { background: var(--amber); }
.prog-bar .bar.risk { background: var(--red); }

/* ── Evidence items ────────────────────────────────────────── */
.ev-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--line-soft);
  border-radius: 8px;
  margin-bottom: 8px;
  background: var(--bg);
}
.ev-icon { width: 36px; height: 36px; border-radius: 8px; background: var(--green-soft); color: var(--green-deep); display: grid; place-items: center; flex-shrink: 0; }
.ev-icon i { font-size: 16px; }
.ev-name { font-weight: 600; font-size: 13px; }
.ev-meta { font-size: 11.5px; color: var(--ink-mute); margin-top: 2px; }
.ev-action { margin-left: auto; }

/* ── Empty state ───────────────────────────────────────────── */
.empty {
  padding: 40px 24px;
  text-align: center;
  background: var(--bg-card);
  border: 1px dashed var(--line);
  border-radius: var(--radius);
  color: var(--ink-mute);
  font-size: 13px;
}

/* ── Read-only banner ──────────────────────────────────────── */
.banner-readonly {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 16px;
  background: var(--amber-soft);
  border: 1px solid #e8c97a;
  border-radius: 8px;
  margin-bottom: 18px;
  font-size: 12.5px;
  color: var(--ink-soft);
}
.banner-readonly i { color: var(--amber); font-size: 16px; }
.banner-readonly b { color: var(--ink); }

/* ── Hero card ─────────────────────────────────────────────── */
.hero {
  background: linear-gradient(120deg, var(--green-deep) 0%, var(--green) 70%, #0e5e34 100%);
  color: #fff;
  padding: 26px 28px;
  border-radius: 14px;
  margin-bottom: 24px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 24px;
  align-items: center;
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute; right: -40px; top: -40px;
  width: 220px; height: 220px;
  background: radial-gradient(circle, rgba(255,255,255,.08), transparent 70%);
  pointer-events: none;
}
.hero h2 { font-family: 'Fraunces', serif; font-size: 24px; font-weight: 600; margin-bottom: 6px; }
.hero p { color: rgba(255,255,255,.78); font-size: 13.5px; max-width: 640px; line-height: 1.6; }
.hero .pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border-radius: 99px;
  background: rgba(255,255,255,.15);
  font-size: 11px; font-weight: 500;
  letter-spacing: .04em; text-transform: uppercase;
  margin-bottom: 10px;
}

/* ── Mono code ─────────────────────────────────────────────── */
.mono { font-family: 'JetBrains Mono', monospace; }

/* ── Misc ──────────────────────────────────────────────────── */
.two-col { display: grid; grid-template-columns: 1.4fr 1fr; gap: 22px; }
@media (max-width: 1000px) { .two-col { grid-template-columns: 1fr; } }

/* Fade animation */
@keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

/* ── Mobile sidebar overlay ────────────────────────────────── */
.sb-backdrop {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 199;
}
@media (max-width: 1024px) {
  body { overflow: auto; }
  .sidebar {
    position: fixed;
    left: 0; top: 0;
    height: 100%;
    z-index: 200;
    transform: translateX(-100%);
    transition: transform .22s cubic-bezier(.4,0,.2,1), width .22s cubic-bezier(.4,0,.2,1);
    box-shadow: 4px 0 24px rgba(0,0,0,.22);
    will-change: transform;
  }
  .sidebar.sb-open {
    transform: translateX(0);
  }
  .sidebar.mini { width: 248px; transform: translateX(-100%); }
  .sidebar.mini.sb-open { transform: translateX(0); }
  .main { height: auto; min-height: 100vh; }
  .sb-backdrop.open { display: block; }
  .sb-hamburger { display: flex !important; }
  /* In-sidebar mini toggle is meaningless when the sidebar is a drawer */
  .sb-mini-btn { display: none !important; }
}
.sb-hamburger {
  display: none;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: 7px;
  background: transparent;
  border: none;
  color: var(--ink-soft);
  font-size: 18px;
  cursor: pointer;
  transition: all .12s;
  margin-right: 2px;
}
.sb-hamburger:hover { background: var(--bg-muted); color: var(--ink); }

/* Topbar Home / Dashboard quick link */
.topbar-home-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px;
  border-radius: 7px;
  background: var(--green); color: #fff !important;
  font-size: 12px; font-weight: 600;
  text-decoration: none !important;
  transition: background .12s, transform .08s;
}
.topbar-home-btn:hover { background: var(--green-deep); text-decoration: none !important; }
.topbar-home-btn:active { transform: translateY(1px); }
.topbar-home-btn i { font-size: 13px; }
@media (max-width: 640px) {
  /* Tight viewports: icon-only home button to save space */
  .topbar-home-lbl { display: none; }
  .topbar-home-btn { padding: 6px 9px; }
}

/* ── Notifications ─────────────────────────────────────────── */
.notif-wrap { position: relative; }
.notif-bell-btn { position: relative; }
.notif-badge {
  position: absolute; top: 2px; right: 2px;
  min-width: 17px; height: 17px;
  background: var(--red); color: #fff;
  border-radius: 99px; font-size: 9px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  padding: 0 4px; pointer-events: none;
  border: 2px solid var(--bg-card);
  line-height: 1;
}
.notif-badge.warn { background: var(--amber); }
.notif-panel {
  position: absolute; top: calc(100% + 10px); right: 0;
  width: 360px; max-height: 500px;
  background: var(--bg-card);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  z-index: 300;
  display: none;
  flex-direction: column;
  overflow: hidden;
}
.notif-panel.open { display: flex; animation: fadeInDown .15s ease; }
.notif-panel-head {
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--line-soft);
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.notif-panel-head h4 {
  font-family: 'Fraunces', serif; font-size: 15px; font-weight: 600;
  display: flex; align-items: center; gap: 8px;
}
.notif-count-pill {
  font-family: 'Inter', sans-serif; font-size: 11px; font-weight: 700;
  background: var(--red); color: #fff;
  padding: 1px 7px; border-radius: 99px;
}
.notif-count-pill.warn { background: var(--amber); }
.notif-count-pill.ok   { background: var(--green); }
.notif-list { overflow-y: auto; flex: 1; }
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-thumb { background: var(--line); border-radius: 4px; }
.notif-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 13px 16px;
  border-bottom: 1px solid var(--line-soft);
  text-decoration: none; color: var(--ink);
  transition: background .1s;
  position: relative;
}
.notif-item:hover { background: var(--bg); text-decoration: none; }
.notif-item:last-child { border-bottom: none; }
.notif-item::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; border-radius: 0 2px 2px 0;
}
.notif-item.urgent::before  { background: var(--red); }
.notif-item.warning::before { background: var(--amber); }
.notif-item.info::before    { background: var(--ai); }
.notif-icon {
  width: 34px; height: 34px; border-radius: 8px;
  display: grid; place-items: center; flex-shrink: 0;
  font-size: 16px; margin-top: 1px;
}
.notif-icon.urgent  { background: var(--red-soft);   color: var(--red); }
.notif-icon.warning { background: var(--amber-soft);  color: var(--amber); }
.notif-icon.info    { background: var(--ai-soft);     color: var(--ai); }
.notif-body { flex: 1; min-width: 0; }
.notif-title { font-size: 13px; font-weight: 600; color: var(--ink); line-height: 1.35; }
.notif-desc  { font-size: 11.5px; color: var(--ink-mute); margin-top: 3px; line-height: 1.45; }
.notif-arrow { color: var(--line); font-size: 14px; margin-top: 8px; flex-shrink: 0; transition: color .1s; }
.notif-item:hover .notif-arrow { color: var(--ink-soft); }
.notif-empty { padding: 36px 20px; text-align: center; color: var(--ink-mute); font-size: 13px; }
.notif-empty i { font-size: 30px; color: var(--green); display: block; margin-bottom: 10px; }
.notif-empty strong { color: var(--ink); display: block; font-size: 14px; margin-bottom: 4px; }
.notif-footer {
  padding: 10px 16px; border-top: 1px solid var(--line-soft);
  flex-shrink: 0; display: flex; align-items: center; justify-content: space-between;
}
.notif-footer a { font-size: 12px; color: var(--ink-mute); text-decoration: none; }
.notif-footer a:hover { color: var(--ink); text-decoration: underline; }
.notif-footer .ts { font-size: 11px; color: var(--line); }
@media (max-width: 800px) {
  .notif-panel { width: calc(100vw - 24px); right: -8px; }
}
</style>
</head>
<body>

<?php if (!$noSidebar): ?>
<!-- Mobile backdrop -->
<div class="sb-backdrop" id="sb-backdrop" onclick="closeMobileSidebar()"></div>
<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="app-sidebar">

  <div class="sb-brand">
    <div class="sb-brand-mark">E</div>
    <div class="sb-brand-text">
      <div class="sb-brand-name">FPC</div>
      <div class="sb-brand-sub">FPC · FPC</div>
    </div>
  </div>

  <div class="sb-mini-btn">
    <button onclick="toggleSidebar()" title="Collapse sidebar" id="sb-toggle-btn">
      <i class="bi bi-layout-sidebar-reverse" id="sb-toggle-icon"></i>
    </button>
  </div>

  <div class="sb-filter">
    <div class="sb-filter-wrap" id="sb-filter-wrap">
      <i class="bi bi-search"></i>
      <input id="sb-filter-input" type="text" placeholder="Filter menu…" autocomplete="off" aria-label="Filter sidebar menu">
      <button type="button" class="sb-filter-clear" id="sb-filter-clear" title="Clear" aria-label="Clear filter">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>
  <div class="sb-filter-empty" id="sb-filter-empty">No matching items</div>

  <div class="sb-scroll">
    <?php sidebar_render($activeView); ?>
  </div>

  <div class="sb-footer">
    <div class="who"><?= e($user['full_name'] ?? 'Unknown') ?></div>
    <?= e($role['label'] ?? '') ?>
  </div>

</aside>
<?php endif; ?>

<!-- ── Main (topbar + content) ─────────────────────────────── -->
<div class="main">

  <!-- Topbar -->
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:10px;">
      <?php if (!$noSidebar): ?>
      <button class="sb-hamburger" id="sb-hamburger-btn" onclick="openMobileSidebar()" title="Open menu">
        <i class="bi bi-list"></i>
      </button>
      <?php endif; ?>
      <a href="<?= e(rbac_home_url()) ?>" class="topbar-home-btn" title="Go to dashboard">
        <i class="bi bi-house-door-fill"></i>
        <span class="topbar-home-lbl">Dashboard</span>
      </a>
      <span style="display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:var(--ink);background:var(--green-soft);padding:5px 11px;border-radius:8px;">
        <span style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block;"></span>
        FPC
      </span>
      <i class="bi bi-chevron-right" style="font-size:10px;color:var(--ink-mute);"></i>
      <span class="topbar-crumb"><?= e($title) ?></span>
    </div>

    <div class="topbar-right">
      <?php if ($role): ?>
        <span class="role-pill">
          <i class="bi bi-shield-fill-check"></i>
          <?= e($role['label']) ?>
        </span>
      <?php endif; ?>

      <!-- Notification Bell -->
      <div class="notif-wrap" id="notif-wrap">
        <button class="btn-icon notif-bell-btn" id="notif-bell-btn"
                title="Notifications<?= $notifBadgeCount > 0 ? ' (' . $notifBadgeCount . ' items)' : '' ?>">
          <i class="bi bi-bell<?= $notifBadgeCount > 0 ? '-fill' : '' ?>"></i>
          <?php if ($notifBadgeCount > 0): ?>
            <span class="notif-badge <?= $notifUrgentCount > 0 ? '' : 'warn' ?>">
              <?= $notifBadgeCount > 9 ? '9+' : $notifBadgeCount ?>
            </span>
          <?php endif; ?>
        </button>

        <div class="notif-panel" id="notif-panel" role="dialog" aria-label="Notifications">

          <!-- Panel header -->
          <div class="notif-panel-head">
            <h4>
              Notifications
              <?php if ($notifBadgeCount > 0): ?>
                <span class="notif-count-pill <?= $notifUrgentCount > 0 ? '' : 'warn' ?>">
                  <?= $notifBadgeCount ?>
                </span>
              <?php elseif (empty($notifications)): ?>
                <span class="notif-count-pill ok"><i class="bi bi-check-lg"></i></span>
              <?php endif; ?>
            </h4>
            <button class="btn-ghost btn-sm" onclick="document.getElementById('notif-panel').classList.remove('open')"
                    style="padding:3px 8px;font-size:12px;">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <!-- Notification items -->
          <div class="notif-list">
            <?php if (empty($notifications)): ?>
              <div class="notif-empty">
                <i class="bi bi-check2-circle"></i>
                <strong>All clear</strong>
                No action items right now — platform data is healthy.
              </div>
            <?php else: ?>
              <?php foreach ($notifications as $notif): ?>
                <a href="<?= e($notif['url']) ?>" class="notif-item <?= e($notif['type']) ?>"
                   onclick="document.getElementById('notif-panel').classList.remove('open')">
                  <div class="notif-icon <?= e($notif['type']) ?>">
                    <i class="bi <?= e($notif['icon']) ?>"></i>
                  </div>
                  <div class="notif-body">
                    <div class="notif-title"><?= e($notif['title']) ?></div>
                    <div class="notif-desc"><?= e($notif['body']) ?></div>
                  </div>
                  <i class="bi bi-chevron-right notif-arrow"></i>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Panel footer -->
          <div class="notif-footer">
            <!-- FPC: point this at a help/assistant page once one exists. -->
            <span style="color:var(--ink-mute)">Updated live</span>
            <span class="ts"><?= date('H:i') ?></span>
          </div>
        </div>
      </div>
      <!-- /Notification Bell -->

      <div class="avatar" title="<?= e($user['full_name'] ?? '') ?>"><?= e($userInitials) ?></div>
      <a href="<?= e(public_url('/logout.php')) ?>" class="btn-icon logout-link" title="Log out">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </header>

  <!-- Page content -->
  <div class="content">
    <div class="container">
<?php
    }

    function layout_end(): void
    {
        ?>
    </div><!-- /container -->
  </div><!-- /content -->
</div><!-- /main -->

<!-- Sidebar mini toggle -->
<script>
(function () {
  var sb = document.getElementById('app-sidebar');
  var icon = document.getElementById('sb-toggle-icon');
  if (!sb) return;
  var mini = localStorage.getItem('sb_mini') === '1';
  if (mini) { sb.classList.add('mini'); icon && icon.classList.replace('bi-layout-sidebar-reverse', 'bi-layout-sidebar'); }
  window.toggleSidebar = function () {
    sb.classList.toggle('mini');
    mini = sb.classList.contains('mini');
    localStorage.setItem('sb_mini', mini ? '1' : '0');
    if (icon) icon.className = mini ? 'bi bi-layout-sidebar' : 'bi bi-layout-sidebar-reverse';
  };
})();

// Section accordion — only one section open at a time; active section auto-opens
(function () {
  var headers = Array.prototype.slice.call(document.querySelectorAll('.sb-section[data-grp]'));
  var LS_KEY = 'sb_sections_v1';
  var saved = {};
  try { saved = JSON.parse(localStorage.getItem(LS_KEY)) || {}; } catch (e) {}

  function setSection(hd, open) {
    var grp = document.getElementById(hd.dataset.grp);
    hd.classList.toggle('closed', !open);
    if (grp) grp.classList.toggle('collapsed', !open);
  }
  function persist() {
    var state = {};
    headers.forEach(function (hd) { state[hd.dataset.grp] = !hd.classList.contains('closed'); });
    try { localStorage.setItem(LS_KEY, JSON.stringify(state)); } catch (e) {}
  }

  // Initial state: remembered choice wins; otherwise open the active section.
  headers.forEach(function (hd) {
    var k = hd.dataset.grp;
    var open = saved.hasOwnProperty(k) ? !!saved[k] : (hd.dataset.hasActive === '1');
    setSection(hd, open);
  });

  // Click toggles just this section — multiple can stay open — then remembers it.
  headers.forEach(function (hd) {
    hd.addEventListener('click', function () {
      setSection(hd, hd.classList.contains('closed'));
      persist();
    });
  });
})();

// Sidebar quick-filter — type to find any nav item across all sections.
(function () {
  var input = document.getElementById('sb-filter-input');
  if (!input) return;
  var wrap    = document.getElementById('sb-filter-wrap');
  var clear   = document.getElementById('sb-filter-clear');
  var empty   = document.getElementById('sb-filter-empty');
  var groups  = Array.prototype.slice.call(document.querySelectorAll('.sb-nav-group'));

  function headerFor(grp) {
    var hd = grp.previousElementSibling;
    return (hd && hd.classList.contains('sb-section')) ? hd : null;
  }

  function apply(q) {
    q = (q || '').trim().toLowerCase();
    if (wrap) wrap.classList.toggle('has-text', q.length > 0);

    if (!q) {
      // Clear filter — underlying accordion (collapsed) state resumes untouched.
      document.querySelectorAll('.filter-hidden').forEach(function (el) { el.classList.remove('filter-hidden'); });
      groups.forEach(function (g) { g.classList.remove('filter-open'); });
      if (empty) empty.style.display = 'none';
      return;
    }

    var anyMatch = false;
    groups.forEach(function (grp) {
      var groupMatch = false;
      Array.prototype.forEach.call(grp.querySelectorAll('.nav-item'), function (it) {
        var lblEl = it.querySelector('.nav-lbl');
        var hit = lblEl && lblEl.textContent.toLowerCase().indexOf(q) !== -1;
        it.classList.toggle('filter-hidden', !hit);
        if (hit) { groupMatch = true; anyMatch = true; }
      });
      grp.classList.toggle('filter-open', groupMatch);   // reveal matches even if section was collapsed
      grp.classList.toggle('filter-hidden', !groupMatch);
      var hd = headerFor(grp);
      if (hd) hd.classList.toggle('filter-hidden', !groupMatch);
    });
    if (empty) empty.style.display = anyMatch ? 'none' : 'block';
  }

  input.addEventListener('input', function () { apply(input.value); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { input.value = ''; apply(''); input.blur(); }
  });
  if (clear) clear.addEventListener('click', function () { input.value = ''; apply(''); input.focus(); });
})();

// Mobile sidebar open / close
window.openMobileSidebar = function () {
  var sb  = document.getElementById('app-sidebar');
  var bd  = document.getElementById('sb-backdrop');
  if (sb) sb.classList.add('sb-open');
  if (bd) bd.classList.add('open');
};
window.closeMobileSidebar = function () {
  var sb  = document.getElementById('app-sidebar');
  var bd  = document.getElementById('sb-backdrop');
  if (sb) sb.classList.remove('sb-open');
  if (bd) bd.classList.remove('open');
};
// Close on nav-item click (navigating away closes the drawer)
document.querySelectorAll('.nav-item').forEach(function (a) {
  a.addEventListener('click', function () { closeMobileSidebar(); });
});

// Close programme-switcher menu on outside click
document.addEventListener('click', function (e) {
  var menu = document.getElementById('prog-switcher-menu');
  var btn  = document.querySelector('.prog-switch-btn');
  if (!menu || !btn) return;
  if (!btn.contains(e.target) && !menu.contains(e.target)) {
    menu.classList.remove('open');
  }
});

// Notification bell toggle
(function () {
  var wrap  = document.getElementById('notif-wrap');
  var btn   = document.getElementById('notif-bell-btn');
  var panel = document.getElementById('notif-panel');
  if (!btn || !panel) return;

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    panel.classList.toggle('open');
  });

  document.addEventListener('click', function (e) {
    if (wrap && !wrap.contains(e.target)) {
      panel.classList.remove('open');
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { panel.classList.remove('open'); }
  });
})();
</script>

</body>
</html>
<?php
    }

    function page_title(string $title, ?string $sub = null): void
    {
        ?>
        <div class="page-title-strip">
            <h1 class="page-title"><?= e($title) ?></h1>
            <?php if ($sub): ?>
                <div class="page-sub"><?= e($sub) ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    function readonly_banner_if_needed(string $viewKey): bool
    {
        if (rbac_can($viewKey) === 'read') {
            $role = rbac_current_role();
            ?>
            <div class="banner-readonly">
                <i class="bi bi-eye-fill"></i>
                <div style="flex:1;">
                    <b>Read-only access</b> — you're viewing this as <b><?= e($role['label'] ?? 'Unknown') ?></b>. Changes can't be saved.
                </div>
            </div>
            <?php
            return true;
        }
        return false;
    }
}
