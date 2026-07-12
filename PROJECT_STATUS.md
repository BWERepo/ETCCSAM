# SAM Project Status

**Last updated:** 2026-07-11 (session covering checkpoints v3.13 and v4.0 — the Registrations/API-sync overhaul)

This file exists so a brand-new Claude Code session can resume this work with zero prior conversation context. Read this alongside `CLAUDE.md` (architecture/rules) before touching code.

---

## Current state (as of this doc)

- **Deployed version:** v4.0 (`index.html` footer `#app-version`), deployed to https://etccapps.com/apps/sam/
- **Git:** `main` branch, last commit `b92183d` ("Checkpoint v4.0: Registrations screen overhaul, API-backed bidder sync"), pushed to `origin` (https://github.com/BWERepo/ETCCSAM.git)
- **No uncommitted checkpoint work pending.** `PROJECT_STATUS.md` itself remains untracked (a continuity doc, not app code).
- **Not blocked** — regression suite was run manually by the user at https://etccapps.com/apps/sam/test.html and confirmed green before the v4.0 checkpoint.
- **Open item (not yet fixed):** a small number of reg #s that had winners/payments recorded but lost their bidder record *before* the v4.0 merge-preserving fix landed (see item 8 below) still show as `"Reg Number N"` in the Auction Summary / Club Income reports (`printAuctionSummary()` / `printClubIncomeReport()` name fallback). The fix only prevents *future* loss — it does not retroactively restore already-orphaned records. There is currently **no UI path** to manually re-enter a name for an orphaned reg #, because the Edit/Add buttons were removed from Registrations this session (see item 3). If this needs fixing, it requires either: (a) getting that reg # back into the external API feed so the next auto-load repopulates it, or (b) reinstating some form of manual bidder edit/add capability.

---

## What was accomplished this session (v3.12 → v4.0)

This was a large session that progressively transformed **Register Bidders** (a manual CSV-import + add/edit/delete screen) into **Registrations** (a read-mostly screen that auto-syncs from an external API), plus several follow-on renames and a data-integrity fix discovered along the way.

### 1. Checkpoint v3.12 (commit `f7c3702`) — carried over from prior session
Finished the home-screen club-branding-banner removal that was pending at the start of this session (see prior status doc). Tests confirmed green, version bumped v3.11→v3.12, committed, pushed.

### 2. Register Bidders toolbar relabel (folded into checkpoint v3.13, commit `65132f0`)
- `"+ Add Bidder"` → `"+ Add Walk-In NonMember Bidder"`
- `"View Member Database"` → `"Add Walk-In Member Bidder"`
- Same underlying handlers (`addBidder()`, `showMemberDBModal()`) — label-only change at this point.

### 3. Bidder type display relabel (checkpoint v3.13)
Added `BIDDER_TYPE_LABELS` map + `bidderTypeLabel(type)` helper (`index.html` ~line 4600 at the time). Display-only remap, **stored `bidder_type` values unchanged** (`'walk-in'`, `'member'`, `'pre-registration'`) so existing data and the `clearBiddersBySelection()` filter logic kept working:
- `member` → **Walk-In Member**
- `walk-in` → **Walk-In NonMember**
- `pre-registration` → **Pre-Registration**

Applied everywhere `bidder_type` was rendered as text: bidders table, Edit Bidder modal, print list, post-auction report, delete-selection dropdown, confirm dialogs.

### 4. Developer > API test page (new, added after v3.13)
- New Developer submenu item **🔌 API** → `#screen-api` (gated by the same `settingsPassword` as Settings/Configuration/Change Log).
- Shows/lets you edit an API URL, defaulting to Car Show's external feed: `https://etccapps.com/apps/carshow/paid-registrations-api.php?key=92d3b014523e89c2aac59e3272326251`.
- **"Test This URL"** button fires a real `GET` (`credentials:'omit'`, no session cookie) and shows the raw HTTP status + response body.
- The URL is a **persisted setting** (`sam_settings.externalApiUrl`, added to `DEFAULT_SETTINGS`) — `saveApiTestUrl()` writes it on `onchange`; `getExternalApiUrl()` reads it (falling back to the Car Show default URL, `DEFAULT_API_TEST_URL`, when nothing's been saved yet).
- This is the **same URL** later consumed by Registrations' auto-load (item 6).

### 5. "Load from API" on Register Bidders (superseded by item 6/7 below)
Initially added as a manual toolbar button (confirm + alert, hard-replace). `fetchBiddersFromApi()` was written to fetch the configured URL and map the Car Show feed's JSON shape (`{ ok, registrations: [{ memberNumber, firstName, lastName, phone, email }] }`) into SAM bidder records (`memberNumber` → `bidder_number`, camelCase → snake_case, `bidder_type: 'pre-registration'` always, missing `memberNumber` auto-numbers from `walkInStart`).

### 6. Auto-load on screen display
Per explicit request, this became **automatic**: every time Registrations (`screen-bidder-reg`) is displayed, `navigate()` now `await`s `autoLoadBiddersFromApi()` — silently reloads from the API with **no confirm dialog** (a blocking dialog on every screen visit would be unusable). Failures are logged to the debug log only; the table still renders whatever it had.

### 7. Buttons removed from Registrations (explicit request)
All five toolbar buttons removed: `+ Add Walk-In NonMember Bidder`, `Add Walk-In Member Bidder`, `Add Registered Members`, `Load from API` (manual), `Delete All`. Per-row **Edit** and **Delete** buttons removed too (action `<td>`/`<col>`/`<th>` dropped, colspan adjusted 7→6→5 as columns kept shrinking).
- Underlying functions were **left in place but are now orphaned/dead code** (deliberately not deleted, flagged to the user each time): `addBidder()`, `showMemberDBModal()`, `addRegisteredMembers()`, `loadBiddersFromApi()` (manual variant), `deleteAllBidders()`, `openBidderEditModal()`/`closeBidderEditModal()`/`saveBidderEdit()` + the `#bidder-edit-modal` HTML.
- A **new Refresh button** (`.tk-btn`, same style as Print) was added back in, positioned *before* PageToolbar's built-in "Clear Filters" button, calling `autoLoadBiddersFromApi()`. This is distinct from PageToolbar's own built-in "Refresh" button (positioned after Clear Filters, still present, still a no-op since no `dataUrl`/`onRefresh` is passed to `PageToolbar.init()` — `toolbar.js` was not modified, per the standing "do not modify toolbar.js" rule).

### 8. Bug found & fixed: orphaned bidder names in reports
User reported the Club Income / Pickup & Pay report (`printClubIncomeReport()`) showing `"Reg Number 134"` instead of an actual name for some reg #s. Root cause: the destructive auto-replace (item 6) was wiping any bidder record not present in the *current* API feed, even if that reg # had a recorded winner or payment — orphaning the winner/payment record's name lookup.

**Fix:** added `referencedBidderNumbers()` (collects every `bidder_number` referenced by `DB.getWinners()` or a key in `DB.getPayments()`) and `mergeApiBidders(fresh, existing)` (fresh API data wins for any number it contains; existing bidders that are referenced but missing from the fresh feed are carried forward instead of dropped). Both `autoLoadBiddersFromApi()` and `loadBiddersFromApi()` (manual, now orphaned) now merge instead of hard-replacing.
- **This fix is forward-looking only** — see the "Open item" note above regarding already-orphaned reg #s from before this fix.

### 9. Settings screen cleanup
Removed three full cards no longer needed now that Registrations is API-driven: **Member Database** (CSV import), **Registration Database** (CSV import), **Bidder Database** (delete-by-type selector). Also removed the standalone **Clear Bidders** button from Developer Tools' clear-scope list.
- Had to also remove one **un-guarded top-level** `document.getElementById('btn-clear-bidders').addEventListener(...)` line — left in place after removing the button it referenced, this would have thrown `TypeError: Cannot read properties of null` at page load and **halted all subsequent script execution for the entire app**. (The sibling `btn-clear-members`/`btn-clear-registrations` handlers are null-guarded inside their callbacks and were left alone since those buttons still exist.)
- Underlying functions now orphaned: `importMemberCSV()`, `importRegCSV()`, `clearBiddersBySelection()`.

### 10. Bidder Type column removed from Registrations table
Table went from 7 columns (original) → 6 (after action-column removal) → 5: `Reg #`, `Last Name`, `First Name`, `Email`, `Phone`. `bidderTypeLabel()` is still used elsewhere (print list, post-auction report) — only the on-screen Registrations table dropped the column.

### 11. Screen renamed "Register Bidders" → "Registrations"
Page header (no more "Add and manage auction bidders." subtitle), home screen workflow card, `navigate()`'s `screenNames['bidder-reg']`, and the User Manual's Step 3 section (heading + rewritten body describing the new auto-load-from-API behavior). **Internal IDs unchanged** — `screen-bidder-reg`, `data-screen="bidder-reg"`, `developerScreens`/`workflowScreens` array entries all still say `'bidder-reg'`.

### 12. Registrations layout narrowed
Wrapped the metric card + table in `max-width:900px; margin:0 auto` (matching the Change Log / API screen pattern). Also fixed a **stale** `min-width:1150px` on the table (a leftover from when there were 7 columns) down to `860px` to match the current 5-column `colgroup` sum — otherwise the narrower wrapper would've just forced a horizontal scrollbar instead of visually narrowing anything.

### 13. "Bidder #" → "Reg Number" → "Reg #" (two-stage global rename)
First pass: **all 27 occurrences** of the literal text "Bidder #" anywhere in the app were changed to "Reg Number" (column headers, labels, alerts, comments, and interpolated prose like `` `Bidder #${bidderNum}` `` → `` `Reg Number ${bidderNum}` `` — note the added space to avoid "Reg Number42").

Second pass (follow-up request, scoped to "columns" only): the **14 actual `<th>` column headers** were shortened further to **"Reg #"** — Registrations table, Payment & Pickup, Post Auction, Print Bid Sheet, Thank You / Winning Bidder email modals, Auction Summary / Club Income reports. The sort-instruction text on Payment & Pickup ("Click **Reg #** or Last Name...") was kept in sync since it directly names that column.

**Left as "Reg Number"** (intentionally, not a column): the (now-orphaned) Edit Bidder modal's form label, a couple of code comments, an `alert()` message, and prose sentences like `"Winning Bidder: John Doe | Reg Number 42"` on printed notices/emails.

### 14. Auction Summary report heading renamed
`printAuctionSummary()`'s `<h2>Registered Bidders (N)</h2>` → `<h2>Registrations (N)</h2>`. The table's own column headers (`Reg #`/Name/Email/Type) were separately already covered by item 13.

### 15. Version bump + Checkpoint v4.0 (commit `b92183d`)
Version bumped directly to **v4.0** (major bump, `bump-version.ps1 -Major`, v3.13→v4.0) per explicit request — not part of the automatic checkpoint step this time, done ahead of the checkpoint itself. `test.html` was then updated with ~10 new regression suites covering everything in items 4–14 (screen rename, button/column/card removals, Refresh button, Reg # rename, and — most importantly — real `assertEqual` unit tests for `mergeApiBidders()`/`referencedBidderNumbers()` using stubbed `DB.getWinners()`/`getPayments()`). User confirmed green, committed, pushed without further prompting per standing convention.

---

## Checkpoint procedure (unchanged, still the standing convention)

1. Update `test.html` for whatever changed (skip if nothing test-relevant changed).
2. `.\deploy.ps1 test.html` if it changed.
3. **Ask the user** to manually run https://etccapps.com/apps/sam/test.html and report pass/fail (automated running is broken — see "Known issue" below). Do not proceed until they say it's green.
4. Once green: `.\bump-version.ps1` (minor bump by default; `-Major` flag for major bumps) **if not already bumped earlier in the session** — check the footer span first, don't double-bump. Then `.\deploy.ps1 index.html`.
5. `git add` the changed files (never `git add -A`), commit with a `Checkpoint vX.Y: <short description>` message, `git push`. Commit and push **without asking** once tests are confirmed green.
6. Report the commit hash, version, and live URL back to the user.

Deploying individual files (`.\deploy.ps1 <file>`) happens continuously after every code change, **without being asked** — separate from the commit/checkpoint step. Never commit on every deploy — only at an explicit "checkpoint". Bare "test" (no other words) means **update** the regression suite only — never run it yourself.

**Known issue, still open (not touched this session):** automated regression test running from a Claude Code session is blocked — Cloudflare challenges any CDP-automated browser (Puppeteer/`run-tests.js`, gitignored/not in git) with a 403 bot-check page, even though the button-click fix from a prior session (`await page.click('button')` before the wait) is technically correct. Workaround in continued active use: the user runs the suite manually and reports pass/fail verbally. This has now worked for every checkpoint from v3.10 through v4.0.

---

## Architecture notes not yet in CLAUDE.md

- **New `sam_settings` field:** `externalApiUrl` (default `''`) — the URL shown/edited on Developer > API and consumed by Registrations' auto-load. Falls back to `DEFAULT_API_TEST_URL` (the Car Show feed URL with its key baked in) when unset. Read via `getExternalApiUrl()`, written via `saveApiTestUrl()`.
- **New functions (Registrations / API sync), all in `index.html`:**
  - `fetchBiddersFromApi()` — pure fetch+map, throws on any failure (non-200, malformed body, `data.ok === false`, `registrations` not an array). No side effects.
  - `referencedBidderNumbers()` — `Set` of every `bidder_number` (as string) referenced by `DB.getWinners()` or a key in `DB.getPayments()`.
  - `mergeApiBidders(fresh, existing)` — returns `{ merged, preservedCount }`; fresh data wins per-number, but existing bidders referenced by a winner/payment and missing from `fresh` are kept.
  - `autoLoadBiddersFromApi()` — the live path: called from `navigate()` on every Registrations display and by the toolbar's Refresh button. Silent (no confirm/alert), always calls `refreshBiddersTable()` at the end (success or failure) so the table renders regardless.
  - `loadBiddersFromApi()` — the older manual variant (confirm + alert). **Currently orphaned** — no UI element calls it anymore, kept for potential future reconnection.
  - `bidderTypeLabel(type)` / `BIDDER_TYPE_LABELS` — display-only remap of the `bidder_type` field (stored values unchanged: `'walk-in'`/`'member'`/`'pre-registration'`).
- **Registrations screen (`#screen-bidder-reg`) column model:** 5 columns — `Reg #`, `Last Name`, `First Name`, `Email`, `Phone`. No Bidder Type, no Edit/Delete action column. `colgroup`/`thead`/`refreshBiddersTable()` row template and the empty-state `colspan` must all be kept in sync if columns change again.
- **Orphaned/dead code deliberately left in place** (flagged, not deleted, per "protect existing features" convention): `addBidder()`, `showMemberDBModal()`, `addRegisteredMembers()`, `loadBiddersFromApi()`, `deleteAllBidders()`, `openBidderEditModal()`/`closeBidderEditModal()`/`saveBidderEdit()` + `#bidder-edit-modal`, `importMemberCSV()`, `importRegCSV()`, `clearBiddersBySelection()`. None of these have a live UI entry point as of v4.0.
- **Developer submenu** (`#nav-developer-submenu`) now has 4 items instead of 3: Configuration, Settings, Change Log, **API** (new). `navigate()`'s `developerScreens` array includes `'api'` alongside `'settings'`, `'config'`, `'changelog'`.
- **`settingsPassword`** now gates four screens instead of three (adds `api`).

---

## Files touched this session

| File | Status | Notes |
|---|---|---|
| `index.html` | committed (v3.13 `65132f0`, v4.0 `b92183d`) | Registrations overhaul: API sync, button/column/card removals, renames, merge-preserving fix |
| `test.html` | committed (v3.13 `65132f0`, v4.0 `b92183d`) | ~15 new regression suites added across the session; both checkpoints confirmed green by the user |
| `PROJECT_STATUS.md` | uncommitted (untracked) | this file — continuity doc, not app code |
