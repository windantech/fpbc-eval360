# FPC — Design System

Carried over from Eval360 (`../atbu`). The *system* transfers; the *identity* is
provisional until FPC's brand is known.

---

## The idea in one line

**One shell, tokens for everything, three typefaces by role.** A page supplies
content and nothing else — no `<head>`, no fonts, no colour literals.

---

## 1. The shell

`lib/layout.php` renders the entire chrome:

```php
layout_start('Page title', ['active_view' => 'module-key']);
// ... page content only ...
layout_end();
```

It emits the document head, font links, the full token block, the topbar with
notification bell, the role-filtered sidebar, and the content container. A page that
writes its own `<html>` or `<head>` is a bug.

`page_title($title, $subtitle)` renders the in-content heading block.

---

## 2. Colour: tokens, never literals

Every colour is a CSS custom property declared once at the top of `layout.php`. No
component references a hex. **Rebranding is editing one block.**

### Current (provisional) palette

| Token | Value | Role |
|---|---|---|
| `--bg` | `#f4f6f8` | Page ground — cool neutral, slight blue bias |
| `--bg-card` | `#ffffff` | Raised surface |
| `--bg-muted` | `#e7ecf1` | Recessed fill, table headers |
| `--ink` | `#10212e` | Primary text |
| `--ink-soft` | `#3b4d5c` | Secondary text |
| `--ink-mute` | `#6b7d8a` | Labels, captions |
| `--line` | `#cdd7e0` | Borders |
| `--line-soft` | `#e1e8ee` | Internal dividers |
| `--green` | `#1b5e8f` | **Primary** — buttons, links, active nav |
| `--green-deep` | `#123f61` | Deepest — sidebar, brand mark |
| `--green-soft` | `#e3eef6` | Primary tint — chips, soft fills |
| `--gold` | `#7d4a6b` | **Accent** — used sparingly, one thing at a time |
| `--gold-soft` | `#f2e7ee` | Accent tint |
| `--red` / `--red-soft` | `#a32424` / `#f7e2e0` | Error, critical, overdue |
| `--amber` / `--amber-soft` | `#b3700d` / `#fbeed2` | Warning, due soon |
| `--ai` / `--ai-soft` | `#0e7490` / `#d5eff5` | AI-assisted features |
| `--radius` | `12px` | Standard corner |
| `--shadow` / `--shadow-lg` | — | Raised / floating |

### Two things to know

**The `--green*` tokens are the primary ramp, not literally green.** They kept their
names so the whole component layer still resolves. Recolour them; do not rename them
unless you are prepared to find-and-replace the entire file.

**Semantic colour is reserved.** Red, amber and green-as-status carry meaning —
overdue, due soon, complete. Using them decoratively destroys the signal. The accent
(`--gold`) is for emphasis; it is not a status.

### Neutrals are chosen, not defaulted

The greys carry a slight blue bias toward the primary. A pure `#888` reads as
unconsidered. Keep whatever bias the final brand suggests, but keep *a* bias.

---

## 3. Typography: three faces, three jobs

```
Fraunces        headings, page titles, figures that matter
Inter           UI, body, labels, buttons — everything else
JetBrains Mono  codes, IDs, reference numbers, aligned digits
```

Loaded from Google Fonts in `layout.php`. Each declares a real fallback stack.

The pairing does the work: a characterful serif for hierarchy against a neutral UI
sans, with mono reserved for things that are *identifiers* — a case reference, a
record code. Mono signals "this is a value you might copy", so do not use it for
emphasis.

Rules: headings get `text-wrap: balance`; running text stays near 65 characters;
uppercase labels get letter-spacing (`.07em`–`.1em`) and sit at 10–11px, 700 weight;
columns of digits get `font-variant-numeric: tabular-nums`.

---

## 4. Components layout already provides

Buttons `.btn` `.btn-primary` `.btn-ghost` `.btn-sm` `.btn-danger` `.btn-icon` ·
Surfaces `.card` `.card-head` `.card-pad` · Headings `.section-title` `.page-title`
`.page-sub` · Data `.data-table` `.kpi-row` `.kpi` `.kpi-value` `.kpi-label` ·
States `.empty` `.banner-readonly` · Utility `.mono` `.container`

Page-specific primitives go in that page's own `<style>` block. Promote one to
`layout.php` only when a third page needs it.

---

## 5. Page patterns

**Two-column working page** — main content left, context right:

```html
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:18px;align-items:start;">
  <div><!-- table, list, form --></div>
  <div><!-- distribution bars, counts, reference --></div>
</div>
```

Ratio by content weight: `1.4fr 1fr` for cards, `1.6fr 1fr` for a wide table.

**Hero** — a full-width gradient band for a section landing page. Eval360 gave each
module its own gradient so staff recognised where they were at a glance. Worth
repeating if FPC has several distinct areas; skip it if there are two.

**Modal** — `.modal-overlay` / `.modal-box` / `.modal-head` / `.modal-body` /
`.modal-foot`, closed by backdrop click:
`onclick="if(event.target===this)closeX()"`.

**Filter bar** — pills above a table, driven by `?filter=` in the URL rather than JS
state, so a filtered view is linkable and survives a refresh.

---

## 6. Rules that are not negotiable

**Not everything is a card.** Border, fill, radius and shadow each say "separate
object". One radius and one shadow stamped on every block flattens the hierarchy and
is the clearest tell of an unconsidered page. Spend them by role — lift the one thing
that needs lifting.

**Big-number tiles only when the figures are the point.** A KPI strip on a page whose
job is a form is decoration.

**Wide content scrolls in its own container.** `overflow-x: auto` on the wrapper. The
page body must never scroll sideways.

**Encode state in form as well as colour.** A pill, a chip, a severity stripe —
colour alone fails for colour-blind users and in print.

**Keyboard focus stays visible.** Never remove the outline without replacing it.

---

## 7. Writing

Name things the way the reader does, not the way the schema does. A control says what
it does (`Publish`), and the confirmation matches (`Published`). Errors say what went
wrong and what to do next — no apology, no vagueness.

Dates: `j M Y` (`3 Oct 2026`) in UI, `Y-m-d` in exports and filenames.

---

## 8. When the brief arrives

1. Replace the provisional palette with FPC's real colours — the token block only.
2. Reconsider the typefaces against the brand. The three-role structure should stay
   even if the faces change.
3. Keep the shell, the token discipline and section 6. Those are the parts that make
   a dozen pages look like one product rather than a dozen pages.
