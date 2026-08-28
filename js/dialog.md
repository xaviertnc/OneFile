# F1 Dialog User Guide

## Overview

`F1.lib.Dialog` is the confirm / validate / auth / form API.  
`F1.lib.Popup` is the engine for custom or toast content. Do **not** use a raw
`Popup` + `theme` for Yes/Cancel — use Dialog.

Every Dialog panel is draggable by its header and has a 24px `.btn-close` SVG.
Type is a **header band**. Theme is **CSS tokens only** — no JS colour object.

## Include order

```html
<link rel="stylesheet" href="vendors/F1/css/popup.css">
<link rel="stylesheet" href="vendors/F1/css/dialog.css">
<script src="vendors/F1/js/popup.js"></script>
<script src="vendors/F1/js/dialog.js"></script>
```

`popup.js` is optional if you only call Dialog (S5). Include it when you also use `new Popup`.

## Dialog vs Popup

| Use | API |
|-----|-----|
| Confirm / delete / decide | `Dialog.confirm` |
| Validation / error list | `Dialog.validate` or `Dialog.alert` |
| Authorize with a code | `Dialog.auth` |
| One field or a short form | `Dialog.prompt` / `Dialog.form` |
| Custom markup, toast, wait box | `new F1.lib.Popup({ … })` |
| Drag + close on an existing panel | `Dialog.bindPopup` / `Dialog.makeDraggable` |

## API

### `Dialog.confirm( message, title?, type? )`

Resolves `true` / `false`. Commit label is **Confirm** (or `okText` if you pass an options object).

`message` is a string **or** `{ name, lead, facts, note }`:

- `name` — sits under the title
- `lead` — opening sentence
- `facts` — `[ [ label, value ], … ]` two-column list
- `note` — muted closer

```javascript
const yes = await F1.lib.Dialog.confirm(
  { name: 'Jane', lead: 'Create this client.', facts: [ [ 'CIF', '123' ] ], note: 'No authorization.' },
  'Create Client',
  'confirm'
);
if ( ! yes ) return;
```

Options object (S3): `Dialog.confirm({ title, message, type, theme, danger, okText, cancelText, auth, chrome: 'outline' })`.

`danger: true` / `type: 'danger'` → red band + `.btn-danger`.

### `Dialog.auth( message, title?, type? )`

Same chrome plus an authorization code field. Resolves the **code** or `null`.

### `Dialog.validate( message, title?, type? )`

OK only. `message` is a string or an array of errors. Resolves `true` when dismissed.

### `Dialog.form( opts )` / `Dialog.prompt( opts )` / `Dialog.alert( opts )`

Form: optional `fields` (`text` | `textarea`), `altButton`, `okOnly`, `warn`.  
Resolves a field map, `true` (no fields), `altButton.value`, or `null`.

Prompt: one field; resolves a trimmed string or `null`.

Alert: OK-only notice (`theme` / `warn`).

## Kinds

Class `.ui-dialog-panel--{kind}` sets `--dialog-ink` / `--dialog-band` / `--dialog-line`.

| Kind | Band | Commit |
|------|------|--------|
| (none) | Wash (host primary) | `.btn-primary` |
| `confirm` | Slate (decision, not brand) | Confirm |
| `auth` | `--dialog-band-auth` / `--brand-navy` | Authorize / `.btn-auth` |
| `danger` / `error` | `--dialog-band-danger` | `.btn-danger` |
| `warning` | Amber | `.btn-primary` |
| `success` | Green | `.btn-primary` |
| `info` | `--dialog-band-info` | `.btn-primary` |

`chrome: 'outline'` — wash band, themed title ink.

## Theme — CSS tokens only

Host `:root` may set `--primary-50` … `--primary-700`, `--primary-color`, `--brand-navy`. Dialog reads those; S5 numbers are the last fallback. Type (danger red, title size, pad) is CSS tokens — not a JS theme object.

| Token | Role |
|-------|------|
| `--dialog-wash` | Default (untitled) header band |
| `--dialog-wash-line` | Neutral hairline (`#e5e7eb`, not primary) |
| `--dialog-footer` | Footer fill (`#f8fafc`) |
| `--dialog-ok` / `--dialog-ok-hover` | Primary commit |
| `--dialog-band-confirm` | Decision band (shared slate) |
| `--dialog-band-auth` | Auth / navy |
| `--dialog-band-danger` | Destroy (`#dc2626`; not app `--danger-color`) |
| `--dialog-title-size` | Header title (F1 default `15px`) |
| `--dialog-body-size` | Message + footer buttons (F1 default `13px`) |
| `--dialog-chrome-pad` | Header / footer pad (F1 default `6px`) |
| `--dialog-body-min-height` | Message floor (hosts `3.67em`; F1 default `0`) |

**S5:** `style.css` then `dialog.css` — F1 compact type wins unless overridden after.  
**S3:** `dialog.css` then `main.css` — set `1.2rem` / `0.875rem` / `0.67em` plus wash ramps + `--brand-navy`. Do not add a second dialog stylesheet.

## Overlays

| Class | Behaviour |
|-------|-----------|
| `.ui-dialog-overlay` | Blocks the page |
| `.ui-popup-overlay` | Pointer events pass through (workbench) |

Footer `.ui-dialog-actions` is control actions only (Cancel / Confirm / Authorize / OK).

## Chrome

- Drag by `.popup-chrome` (or any handle passed to `bindPopup`)
- Close: 24px `.btn-close` (`.popup-close` is an alias)
- S3 aliases `.fu-dialog` / `.fu-create-popup` / `.fu-btn-*` are compatibility only. New code uses the S5 class names.
