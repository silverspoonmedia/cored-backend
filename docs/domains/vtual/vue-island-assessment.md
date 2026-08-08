# vTual Vue Island Assessment

**Date:** 2026-08-09  
**Scope:** Evaluate every vTual Blade view against the [dual-path Vue island checklist](../../web/blade-admin.md#dual-path--when-to-use-which) to determine which (if any) surfaces qualify for promotion.

## Background

Vue islands are the project's progressive enhancement strategy: mount Vue 3 SFCs inside Blade when a subtree needs richer client UI than vanilla ESM + jQuery plugins, but only after the dual-path checklist passes. Two islands exist today: `dashboard-session` (simple) and `file-hosting` (SPA). vTual is purely Blade + vanilla JS (`claim-realtime.js`, `credentials-platform.js`, `confirm-modal.js`, `registry-admin.js`, `credential-form.js`).

The checklist gates promotion on (A) all required — interactive subtree is product-scoped and XOR cutover is clean — and (B) at least two of four signals: rich client state, frequent Echo/mutation-driven partial updates, manual DOM patching >= 1k LOC, or declarative rendering being cheaper than DOM sync.

## Assessment per surface

### 1. vtuber/show.blade.php — Read-only vTuber profile

**Current:** 20-line Blade shell composing `_sidebar` + `_content_panel` partials. Zero JS, pure API data display (bio, channels, content cards).

**Checklist:**
- (A) Gate: Fails. Not interactive beyond scroll/click. No mutating subtree.
- (B) Signals: None. No client state, no live updates, no DOM patching.

**Verdict:** Stay on Blade. Pure display — zero checklist triggers.

---

### 2. vtuber/index.blade.php — Product hub placeholder

**Current:** Blank AdminLTE shell. No content yet.

**Verdict:** Stay on Blade. Empty placeholder. Re-evaluate when the hub gains content.

---

### 3. vtuber/claim.blade.php — Interactive claim wizard

**Current:** 188-line Blade template + `<template>` fallback for the active claim panel + `claim-realtime.js` (487 lines). Architecture: Echo WebSocket (`private-vtual.claim.{id}` → `claim.progress` events), DOM patching (show/hide panels via `d-none`, update badges, insert banners, manual HTML string building for proof channel via `escapeHtml` + concatenation), ajax-form event listeners, grace-timer degraded-fallback (30s), connection badge updates.

**Checklist analysis:**

(A) Gate:
- Interactive subtree is fully product-scoped (vtual claim flow only). No AdminLTE chrome interaction.
- XOR cutover: replacing `claim-realtime.js` orchestrator with Vue is a clean cut — the claim page has its own container (`#vtual-claim-root`) and shares no DOM ownership with other JS on the page.

(B) Signals (counting for >=2):

1. **Rich client state:** claim lifecycle (pending → queued → processing → verified / failed / cancelled / max_attempts), connection state (connected / degraded), queue badge status (`verify_queue_status`), blocked-until state, failed banner visibility, verify button visibility dependent on `attempts_remaining`. These states interact — a `verify_failed` event must update the badge, show the failed banner, re-show the verify button conditionally, and hide the channel section — all in one snapshot.

2. **Frequent partial UI updates from Echo:** `claim.progress` events drive `applyClaimSnapshot()` which mutates 5+ DOM regions per event (proof channel row, token code, attempts counter, queue badge, queue notice text, verify/cancel form hidden inputs, failed banner, blocked banner).

3. **Manual DOM patching:** `claim-realtime.js` builds HTML strings for the proof channel (`escapeHtml` + string concatenation for badge + label + link), toggles `d-none` classes on 6+ `data-claim-*` targets, clones `<template>` fragments for panel mount, creates elements imperatively (`showBlockedBanner`), and manually queries sibling selectors for each DOM region update.

4. **Declarative rendering would be cheaper:** The interaction between `d-none` classes, `data-claim-*` attributes, and the state machine (panel visibility, channel section visibility, proof channel display, queue status badge, failed banner, verify button visibility, blocked banner) is exactly what Vue's reactivity eliminates. Instead of 6+ manual `querySelector` + `classList.toggle` calls per snapshot, each DOM region binds to a reactive state variable.

**Count: (A) met, (B) has 3-4 signals.** Checklist is technically met.

**Implementation (2026-08-09):** Promoted to Vue island. `claim.blade.php` is now a thin mountpoint + JSON bootstrap. The Vue island at `resources/js/vue/islands/vtual-claim/` (`App.vue` + `useClaimRealtime.js` composable) replaces the 487-line `claim-realtime.js` orchestrator and the `claim-active-panel.blade.php` partial. All claim state (active panel, proof channel, attempts, queue badge, banners, connection indicator) is reactive Vue state instead of `querySelector` + `classList.toggle` DOM patching. Echo/Pusher subscription moved into `useClaimRealtime` composable with `onUnmounted` cleanup. AJAX form delegation replaced by `apiFetch`-based `submitAction` calls.

---

### 4. profiles/show.blade.php — Manage owned profile

**Current:** 32-line Blade shell composing 5 partials (`_meta-form`, `_merge-form`, `_links`, `_channels`, `_content-stub`). Interactive via AJAX forms on `_channels.blade.php` (259 lines: 3 tables with inline forms for verify / cancel / accept-anyway / refresh / archive / restore / promote, confirm modal delegation to `_confirm-modal`). `confirm-modal.js` (49 lines) handles modal → form `requestSubmit()`.

**Checklist:**
- (A) Gate: Product-scoped subtree. But it is CRUD forms, not a rich interactive surface.
- (B) Signals: None qualify. No client state beyond form submission results — AJAX forms handle mutations and inline error display. Confirm modals are Bootstrap-native delegation. No Echo subscriptions, no multi-panel navigation, no soft-nav history. Each partial is independently rendered server-side and independently updated via AJAX.

**Verdict:** Stay on Blade. Classic CRUD — exactly what ajax-form + DataTable + confirm-modal patterns handle well.

---

### 5. profiles/index.blade.php — Owned profiles list

**Current:** 63-line listing table with columns: avatar, display_name, slug, channels_count, actions. Standard server-rendered table.

**Verdict:** Stay on Blade. Index list — no checklist triggers. If this list ever grows a live refresh requirement (e.g., stream status indicators), re-evaluate via DataTable soft-reload first.

---

### 6. orphans/* — Staff orphan CRUD

**Current:** DataTable index (`initDataTable` with server-side processing, 54 lines) + create/edit forms with deferred channel registration. Standard admin CRUD pattern.

**Verdict:** Stay on Blade. Nothing in the checklist applies.

---

### 7. platform-credentials/* — Staff credential management

**Current:** DataTable listing + AJAX forms + `credentials-platform.js` (row-level status updates after test / revoke / delete via ajax-form:success events) + `credential-form.js` (type-based secret field toggle on create/edit forms). `probe.blade.php` is a form + results display.

**Checklist:**
- (A) Gate: Fails. Forms + DataTables, not a rich interactive subtree.
- (B) Signals: None. `credentials-platform.js` manages DataTable row DOM updates after AJAX — textbook soft-patch pattern. `credential-form.js` toggles field sections on select change — trivial client-side visibility.

**Verdict:** Stay on Blade. Standard admin CRUD with well-scoped vanilla helpers.

---

## Summary

| Surface | Candidate? | Reason |
|---|---|---|
| vtuber/show (read profile) | No | Pure display |
| vtuber/index (hub) | No | Empty placeholder — re-evaluate when content exists |
| vtuber/claim (wizard) | **Promoted (2026-08-09)** | Vue island at `resources/js/vue/islands/vtual-claim/` |
| profiles/show (manage) | No | Classic CRUD forms |
| profiles/index (owned list) | No | DataTable listing |
| orphans/* (staff CRUD) | No | Classic admin CRUD |
| platform-credentials/* | No | Classic admin CRUD |

**Status (2026-08-09):** The claim wizard at `vtuber/claim.blade.php` has been promoted to a Vue island (`resources/js/vue/islands/vtual-claim/`). `claim-realtime.js` and `claim-active-panel.blade.php` are superseded. All other surfaces remain on Blade.
