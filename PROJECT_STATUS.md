# SAM Project Status

**Last updated:** 2026-07-17 — **checkpoint v5.0**: added a brand-new standalone page, `starting-bid-list.php`, listing every donated item's starting bid — built and refined through several rounds of live feedback, `test.html` updated with a new suite, confirmed green by the user, and checkpointed (major version bump, committed, pushed).

This file exists so a brand-new Claude Code session can resume this work with zero prior conversation context. Read this alongside `CLAUDE.md` (architecture/rules) before touching code.

---

## Current state (as of this doc)

- **Deployed version:** **v5.0** (`index.html` footer `#app-version`) — deployed code matches the latest checkpoint commit, no drift.
- **Git:** `main` branch, last commit `5245137` ("Checkpoint v5.0: new Starting Bid List standalone page"), pushed to `origin` (https://github.com/BWERepo/ETCCSAM.git). Working tree is clean.
- **Regression suite (`test.html`) was updated this session and confirmed green by the user** before the v5.0 checkpoint.
- **No uncommitted app-code work** as of this doc.

### ⚠️ Open items carried into the next session

1. **The settings-password "corrupted again" investigation from several sessions ago is still not conclusively closed.** The working theory (a transcription/autofill issue at the password prompt, not a code bug) was never confirmed — the user was given a console snippet to bypass manual typing and confirm it, but no result was ever reported. If it resurfaces, start by asking whether that bypass test was ever tried, rather than re-diagnosing from scratch.
2. **`api.php`/`add-item.php` deploys via `deploy.ps1` have been intermittently unreliable in past sessions** (`curl: (56)`/`curl: (18) ... got 450`) — manual upload via Hostinger File Manager is the fallback that has worked. This session's `deploy.ps1` calls (including the new `starting-bid-list.php`) all reported success with no retries needed, but keep verifying with a diff/marker check if a deploy ever looks suspicious.
3. **The Gmail-scan workflow's UI is hidden (`display:none`), not deleted**, and a large amount of supporting JS (OAuth token handling, inbox scanning, `parseEmailBody()`/`DEFAULT_FIELD_MAP`-driven parsing) remains in `index.html`, unreferenced by any visible UI. It couldn't be fully removed because the **Gmail OAuth Settings card is still load-bearing** — it configures the same Gmail API connection used by the currently-working **Announce Winners → Email Winners** feature (`sendWinnerEmails()` → `sendEmailsViaGmail()`). A future session could cleanly split "Gmail auth for sending" from "Gmail scanning for inbox import" if the scanning code is ever confirmed permanently dead.
4. **`donate-item.php` remains fully removed** (unchanged from prior sessions) — `add-item.php` is the only item-donation entry point. Its old SQL-side backend (`donated_items_pending` table, `get_pending_donations`/`mark_donations_imported` in `api.php`) is still there, unused, per the same convention.
5. **`starting-bid-list.php` (new this session) has no password gate**, matching `add-item.php`'s convention — anyone with the URL can view the full donated-items list with member names and starting bids. This was not explicitly discussed as a security tradeoff; flag it if the club raises privacy concerns about member names being publicly listable.

---

## What was accomplished this session (checkpoint v5.0)

Built entirely from a single fresh-start feature request that then went through many small rounds of live, iterative feedback (each a one-line follow-up). Summarized in final end-state order, not literal chat order.

### 1. New feature — `starting-bid-list.php`, a printable Starting Bid List
The user asked for "a page listing items donated" with Print and Done buttons, showing item number, category, description, and a starting bid (reserve, or Starting Bid % × value if no reserve).

**First attempt (superseded within the session):** built as an in-app JS function `printStartingBidList()` on the Donated Items screen (`index.html`), wired to a new "🖨 Starting Bid List" toolbar button — generated the report into a popup window via `document.write()`, the same pattern `printDonatedItemsList()` already uses.

**Final design (what's actually shipped):** the user then asked to remove that button and said they needed a **URL** for the list instead. Since the popup-window approach has no bookmarkable address, this was rebuilt as a real standalone PHP page — `starting-bid-list.php` — following the exact pattern `add-item.php` already established:
- No password gate, no login (same explicit convention as `add-item.php`) — public, reachable directly at **https://etccapps.com/apps/sam/starting-bid-list.php**.
- Reads live data **server-side from the SQL `sam_store` table**, not from localStorage: looks up `sam_current_auction` to pick the right `sam_{auctionId}_items` key (falling back to `sam_items`), decodes that JSON blob, and reads `sam_settings` for `startingBidPct`.
- The in-app popup version (`printStartingBidList()` JS function and its toolbar button) was fully deleted from `index.html` once the standalone page replaced it — no orphaned code left behind, since it was a same-session addition-then-replacement rather than a persisted feature being retired.

### 2. Iterative refinement of `starting-bid-list.php` (all same-session follow-ups)
In the order requested:
- **Column order**: started as Item # / Category / Description / Starting Bid, then Starting Bid was moved to directly after Category (Item # / Category / Starting Bid / Description).
- **Member Name column added**, sourced from `etcc_member_name` (the same field `add-item.php`'s "ETCC Member Name" dropdown writes), inserted between Starting Bid and Description. Final column order: **Item # / Category / Starting Bid / Member Name / Description**.
- **Print sizing**: "make print wider, make form narrower" — interpreted as: constrain the on-screen content to a narrower column (`max-width` on the content) while widening the printed page. First implementation switched print to landscape orientation; a later request ("make form modular" — user clarified via `AskUserQuestion` this meant **"not full page"**) reverted print back to **portrait**, keeping the printed table the same narrower width as the screen view rather than stretching it edge-to-edge.
- **Nowrap columns**: Item #, Category, and Member Name given `white-space:nowrap` so they never wrap onto a second line (Starting Bid and Description still wrap normally).
- **Header text**: changed to `<h2>Silent Auction - Donated Items</h2>` with an intro line "The following items have been donated by our members." A "Printed: `<date/time>`" line was added and then removed again per a direct follow-up ("remove printed date and time from form").
- **Done button behavior**: originally `window.close()` with a `setTimeout` fallback to `location.href='index.html'` (matching `add-item.php`'s Done button, for the case where the tab wasn't opened via `window.open()` and can't be closed by script). Simplified to **`window.close()` only** per explicit request ("done should closed form") — this page is always reached directly by URL, so the index.html fallback wasn't wanted.
- **Floating card layout**: "make form floating page" — clarified via `AskUserQuestion` (user picked **"Card with shadow"**) to mean a centered white card (rounded corners, `box-shadow`, `max-width:700px`) floating over a light-gray page background, matching a common modern-form aesthetic. `@media print` strips the background/shadow/border-radius and expands the card to full width so the printed page doesn't waste ink on decoration that only makes sense on screen.

### Gotcha for future sessions: two `AskUserQuestion` clarifications were needed this session
Two requests in a row ("make form modular", "make form floating page") were ambiguous enough on their own that guessing wrong would have meant redoing the work — both were disambiguated with `AskUserQuestion` before implementing, rather than guessing. If a short, vague styling request comes in for this page again, it's worth pattern-matching against this history before assuming what it means.

### 3. `test.html` updated to match, confirmed green
Added one new suite, **`starting-bid-list.php — standalone Starting Bid List page (v4.8 session)`** (9 assertions), covering: the standalone/no-login page itself, the server-side `sam_store` data source, final column order, the Starting Bid formula (reserve-or-percentage, matching the bid-sheet convention), the nowrap columns, the header text change, the floating-card layout, portrait print sizing, and the Done button's close-only behavior. (Suite name says "v4.8" — written before the checkpoint version number was decided; the actual shipped version is v5.0. Harmless, but if a future session is grepping `test.html` by version number, note the mismatch.)

No stale assertions needed fixing — the in-app popup button that was briefly added to `index.html` and then removed left no net trace requiring a test update.

Deployed via `.\deploy.ps1 test.html`, then **confirmed green by the user** at https://etccapps.com/apps/sam/test.html before the v5.0 checkpoint proceeded.

### 4. Checkpoint v5.0 (major version bump)
The user explicitly asked to bump to **5.0** (not a minor bump) — done via `.\bump-version.ps1 -Major` mid-session, ahead of the formal `/ETCCSAMCheckpoint` invocation. The checkpoint skill correctly detected the version was already bumped and did not double-bump.

### Files touched this session
| File | Status | Notes |
|---|---|---|
| `starting-bid-list.php` | new, committed (`5245137`) | Standalone public "Starting Bid List" page — see above for full column/layout history |
| `index.html` | committed (`5245137`) | Net change is just the version bump to v5.0 — the `printStartingBidList()` function and its toolbar button were added and then fully removed within this same session, so there's no trace of the popup-window approach left in the file |
| `test.html` | committed (`5245137`) | One new suite added (9 assertions) for `starting-bid-list.php`; no stale assertions found |
| `PROJECT_STATUS.md` | this update | continuity doc, not app code |

---

## What was accomplished this session (checkpoint v4.7)

Short, incremental session driven by the user reviewing the live Donated Items screen after the v4.6 checkpoint and flagging two follow-on issues. Nothing here was requested up front — it was found live, in order:

### 1. Removed the orphaned Add Item modal (resolves prior open item #3)
The v4.6 session had switched "+ Add Item" to open `add-item.php` in a new tab, leaving the old in-app modal (`#add-item-modal`) and its three functions (`openAddItemModal()`, `closeAddItemModal()`, `saveAddItemModal()`) orphaned but not deleted, per the project's "flag, don't delete" convention. This session confirmed via grep that nothing else in `index.html` referenced them, then deleted the modal's `<div>` block and all three functions outright.

### 2. Real bug — Donor Name overflow bleeding into Donor Email column
**Symptom reported (via screenshot):** the Donor Email cell for a long-donor-name row ("Wilderness Trail Distillery, Attn. Grayson Yaden") showed visually garbled/overlapping text. First assumed to be a data problem (bad paste, hidden Unicode bidi characters) or a screenshot/tooltip artifact — the user then confirmed via Edit mode that the underlying data was completely clean (`Grayson.Yaden@campari.com`), which ruled that out.

**Root cause, found by comparing sibling `<td>` styling:** in `refreshItemsTable()` (`index.html`), the Description and Category `<td>`s explicitly set `white-space:normal;word-break:break-word;vertical-align:top`, but the Donor Name and Donor Email `<td>`s (originally two lines down) had no such style — so under `table-layout:fixed`, a long Donor Name overflowed past its column's fixed width and visually overlapped the Donor Email cell next to it. This wasn't a data bug or a screenshot artifact at all; it only became visible once a sufficiently long donor name/address was entered (the `add-item.php` workflow introduced in v4.6 made long addresses common for the first time).

**Fix:** added the same `white-space:normal;word-break:break-word;vertical-align:top` styling to the Donor Name and Donor Email `<td>`s in `refreshItemsTable()`. Also widened both columns in `#items-table`'s `<colgroup>` (Donor Name 140px→260px in two steps per user follow-up request; Donor Email 190px→260px), bumping the table's `min-width` from 1576px→1766px to match.

### 3. Real bug — Donor Name/Email edit-mode inputs clipped long text
Once the *display* mode wrapped correctly, the *edit* mode (triggered by clicking "Edit") still used single-line `<input type="text">` elements for Donor Name and Donor Email in `editItemByNumber()`, which clip/scroll long values instead of wrapping — inconsistent with Description, which already used a `<textarea>`. Changed both to `<textarea>`, matching Description's pattern. This required a follow-on fix in `saveItemEdit()`: it read the new values via `querySelector('input')`, which no longer matched anything once the elements became `<textarea>`s (would have silently kept stale values on save) — updated to `querySelector('textarea')` for both fields.

### 4. `test.html` updated to match, confirmed green
- Corrected the now-stale v4.6-session assertion that claimed `#add-item-modal`/`openAddItemModal()` "still exist in the DOM but are no longer wired to any button" — now notes they were fully removed in this session.
- Added a new suite, `Donated Items table — orphaned Add Item modal removed, columns widened, donor overflow fixed (v4.7 session)`, covering all three fixes above.
- Deployed via `.\deploy.ps1 test.html`, then **confirmed green by the user** at https://etccapps.com/apps/sam/test.html before the checkpoint proceeded.

### Files touched this session
| File | Status | Notes |
|---|---|---|
| `index.html` | committed (`61f0345`) | Add Item modal removal; Donor Name/Email `<td>` wrap fix + column widening; Donor Name/Email edit-mode `<textarea>` fix + matching `saveItemEdit()` fix; version bumped to v4.7 |
| `test.html` | committed (`61f0345`) | One stale assertion corrected, one new suite added (4 assertions); confirmed green by the user |
| `PROJECT_STATUS.md` | committed separately (`9c09762`, then this update) | continuity doc, not app code |

---

## What was accomplished this session (checkpoint v4.6)

This was a long, iterative session driven entirely by incremental UI/UX requests against the Step 1 screen and `add-item.php`, plus two real bugs discovered and fixed along the way. Summarized in the order the underlying *design* ended up in, not the literal chat order (which zig-zagged through several false starts — see "Design detours" below).

### 1. `deploy.ps1` — cache-busting on every deploy
Added `Update-CacheBust()`: stamps a fresh `?v=<epoch-seconds>` query string onto the four static asset links in `index.html` (`css/table.css`, `css/toolbar.css`, `js/table.js`, `js/toolbar.js`), replacing any existing `?v=...` rather than stacking (idempotent, same pattern as `Update-Version`'s deploy-date stamp). Wired into both the single-file (`.\deploy.ps1 index.html`) and full-deploy code paths, alongside `Update-Version`. Purpose: force browsers to fetch fresh CSS/JS instead of a stale cached copy after every deploy.

### 2. Step 1 — "Item Load" → "Donated Items" screen overhaul
The single biggest change this session. Final end state:
- **Renamed everywhere**: page header, Home screen workflow card (title + subtitle "View donated items"), the `screenNames` map used by `navigate()`, and the in-app User Manual's Step 1 section all now say "Donated Items" instead of "Item Load"/"Load Item Emails".
- **Gmail email-scan workflow retired from view, not deleted.** The Gmail connection status strip, "no client ID" warning, and the Inbox Emails card (Scan Emails / View All / Delete All, `#email-table`) are now wrapped in a `display:none` container. They could not be removed outright: several `document.getElementById('btn-scan').addEventListener(...)`-style calls elsewhere have **no null-guard**, so deleting the elements would throw at script load and break the whole page. `#btn-load-items` ("Update Items") is hidden the same way, for the same reason.
- **The separate "Donated Items" modal (`#donated-items-modal`) was removed entirely** — it was a short-lived design from earlier this session (a full-screen overlay mirroring Loaded Items, with its own checkbox/bulk-delete UI) that got superseded once the user clarified they wanted the *existing* Loaded Items table itself to carry that behavior, not a separate view. `openDonatedItemsModal()`, `closeDonatedItemsModal()`, `renderDonatedItemsTable()`, `selectAllDonatedItemRows()`, `deleteCheckedDonatedItems()`, and the `ITEM_EDIT_COLS_DONATED` column map are all gone. The original `#items-table`/`#items-tbody` (card header now says "Donated Items", was "Loaded Items") carries the checkbox column and bulk delete directly.
- **Checkbox column + bulk delete on the one remaining table**: every row has a `.item-row-check` checkbox (`data-item-number="..."`); a header "check all" checkbox (`#items-check-all-th`) toggles all rows via `selectAllItemRows(checked)`. **Gotcha discovered and fixed**: `TableKit.init()` rebuilds every `<th>`'s contents on init, which wiped this checkbox out on every `refreshItemsTable()` call (same issue the View All modal already had to work around). Fixed by re-injecting the checkbox into the header cell immediately after `TableKit.init(document.getElementById('items-table'))` runs.
- **Per-row Delete button removed** — Actions column is now Edit-only (+ conditional View when a scanned-email match exists, a vestige of the old workflow, harmless). Deletion is now bulk-only via a **Delete Item** button (`deleteCheckedItems()`), which collects checked `data-item-number`s, confirms once, filters them out, saves, and cleans up any orphaned winner records — mirrors what `deleteItemByNumber()` already did per-row.
- **"Date Loaded" column removed** entirely — from the `<colgroup>`/`<thead>`, from `refreshItemsTable()`'s row template, and from `ITEM_EDIT_COLS_MAIN`'s column-index map (shifted left by one: `category:3, desc:4, value:5, reserve:6, donorName:7, donorEmail:8, donorPhone:9, actions:10`). Table is now 12 columns (was 13, including the checkbox).
- **"View Categories" and "View All" buttons removed** from the toolbar (functions still exist, just unused here — `showViewAll` is still called elsewhere for emails/bid-sheets).
- **"Delete All" removed, replaced with a "🖨 Print" button** (`printDonatedItemsList()`) — opens a print-friendly list of all donated items via the same `openPrintWindow()` pattern already used by `printBiddersList()`/`printPaymentTable()`/etc. (`deleteAllItems()` is still defined but now unused.)
- **"Check All" / "Uncheck All" toolbar buttons removed** — the header checkbox is now the only toggle-all control (added originally alongside the buttons, then the buttons were removed once the header checkbox worked correctly).
- **"+ Add Item" now opens `add-item.php` in a new tab** (`window.open('add-item.php', '_blank')`) instead of the in-app modal — see open item #3 above re: the now-orphaned modal.
- **`add-item.php`'s "Cancel" button relabeled "Done"**, and its behavior changed from "just reload the same blank form" to `window.close(); setTimeout(() => location.href='index.html', 150);` — tries to close the tab (it was opened via script, so this works), falling back to navigating to `index.html` if the browser refuses.

### 3. Real bug #1 — multi-tab data loss (add-item.php inserts silently erased)
**Symptom reported:** an item added via the `add-item.php` form didn't show up in the app, even after navigating away and back.

**Root cause, found by tracing the actual save/sync code** (not by guessing): `navigate()` always calls `persistItemLoadScreenToDB()` when leaving the item-load screen, which does a **full overwrite** (`DB.saveItems()` → `save_items` action → deletes and re-inserts every row for that auction, in both the SQL `items` table and the `sam_store` key-value blob) using whatever items array is currently in the browser's memory. If `add-item.php` (now opened in a separate tab) inserted an item while the original SAM tab still held an older in-memory copy, switching back to that tab and navigating *anywhere* would push the stale array back out — silently erasing the item `add-item.php` had just written. This risk was actually already flagged in `add-item.php`'s own header comment, written back when Add Item only had an in-app-modal path with no separate-tab conflict — switching Add Item to open in its own tab (this session) is what made the risk real.

**Fix:** the app now re-syncs from the database automatically whenever the tab regains focus, reusing the existing `PageVisibilityManager.init()` `visibilitychange` handler (previously only used for session-timeout detection). Its "page visible again, session still valid" branch now also calls `syncFromKeyValueDB()`, then `refreshItemsTable()`/`refreshMetrics()` if the Donated Items screen is currently active — so switching back from the `add-item.php` tab refreshes the in-memory copy *before* any subsequent navigation can push a stale version back out.

### 4. Real bug #2 — Reserve Amount silently hidden when it had a "$" prefix
**Symptom reported:** after adding an item via `add-item.php`, its Reserve field wasn't populating in the table.

**Root cause:** `add-item.php`'s client-side `formatCurrency()` prepends a `$` to the Reserve Amount before submission (e.g. `"$75"`). Four separate render sites decided whether to show the Reserve column using a bare `parseFloat(item.reserve_amount) > 0` — and `parseFloat("$75")` returns `NaN` in JavaScript (it does not skip a leading `$`), so any reserve value carrying that prefix was silently treated as blank/zero. Items loaded the old way (Gmail scan, plain numeric reserve) never had a `$` prefix, which is exactly why this bug went unnoticed until `add-item.php` became the primary entry point this session.

**Fix:** switched all four sites — `refreshItemsTable()` (Donated Items table), `printDonatedItemsList()`, the Announce Winners table, and the View All-equivalent render path — plus the bid-sheet's `hasReserve` flag (governs whether the Reserve info box/column appears on printed bid sheets) to use the existing `parseMoney()` helper, which already strips `$`/`,` before parsing.

### 5. Total Value metric — reserve-or-value fallback
Per explicit user request: `refreshMetrics()`'s Total Value calculation now sums, per item, the **Reserve** amount if it's set and non-zero, otherwise the **Value** amount (previously always summed Value regardless of Reserve).

### 6. Settings screen — Email Field Mapping card and Clear Emails button removed
- **Email Field Mapping card removed entirely**: the card, `renderFieldMapTable()`/`editFieldMapRow()`/`saveFieldMapRow()`/`removeFieldMapRow()`, the `LOADED_ITEMS_COL`/`SELECT_BY_OPTIONS` constants, its `#field-map-table` CSS, and its User Manual mention are all gone. `DB.getFieldMap()`/`saveFieldMap()` (the data-layer functions) were left alone, since the still-present (orphaned) Gmail-scan email parser calls `DB.getFieldMap()`.
- **Gotcha caught before it shipped**: `renderFieldMapTable()` was called unconditionally on every Settings-screen load — leaving that call in place after removing its target table would have thrown (`tbody.innerHTML` on `null`) and broken the rest of Settings' load sequence (auction dropdown, favicon, settings-password field, etc.). Removed the call.
- **"Clear Emails" button removed** from Developer Tools, along with its `document.getElementById('btn-clear-emails').addEventListener(...)` call (same unguarded-null-ref risk, same fix).
- **Gmail OAuth card explicitly kept** — user was asked directly and confirmed keeping it, since it's still load-bearing for Announce Winners → Email Winners (see open item #4 above).

### Design detours worth knowing about (in case they look like unfinished work)
- Early in the session, "replace the Item Load form with the donated items form" went through **three rounds of clarification** before landing on the final design — the user first meant the Donated Items screen's manual-entry fields (not `add-item.php`, not the old Gmail-parsed fields), which is why the very first version of this change made the Add Item modal (already existing but previously unwired) the primary entry point, *before* a later request switched it to open `add-item.php` in a new tab instead.
- A short-lived intermediate design added a **separate** "Donated Items" modal (mirroring Loaded Items with its own checkbox/bulk-delete UI) before the user clarified they wanted that behavior folded directly into the existing Loaded Items table instead, with no separate modal. If a future session finds this confusing in git history, that's why — it was corrected within the same session, not left half-done.

---

## Checkpoint procedure (unchanged, still the standing convention)

1. Update `test.html` for whatever changed (skip if nothing test-relevant changed).
2. `.\deploy.ps1 test.html` if it changed.
3. **Ask the user** to manually run https://etccapps.com/apps/sam/test.html and report pass/fail (automated running is broken — see "Known issues" below). Do not proceed until they say it's green.
4. Once green: `.\bump-version.ps1` (minor bump by default; `-Major` flag for major bumps) **if not already bumped earlier in the session** — check the footer span first, don't double-bump. Then `.\deploy.ps1 index.html` (and any other changed files individually).
5. `git add` the changed files (never `git add -A`), commit with a `Checkpoint vX.Y: <short description>` message, `git push`. Commit and push **without asking** once tests are confirmed green.
6. Report the commit hash, version, and live URL back to the user.

Deploying individual files (`.\deploy.ps1 <file>`) happens continuously after every code change, **without being asked** — separate from the commit/checkpoint step. Never commit on every deploy — only at an explicit "checkpoint". Bare "test" (no other words) means **update** the regression suite only — never run it yourself.

**A bare "commit, and push"** (or invoking the separate, lighter-weight `ETCCCheckpoint` skill — a global agent skill, not part of this repo, shared with the CarShow project) skips the version bump and the test-green gate entirely. Use judgment on which the user actually means.

**Known issue #1 (still open):** automated regression test running from a Claude Code session is blocked — Cloudflare challenges any CDP-automated browser with a 403 bot-check page. Workaround: the user runs the suite manually and reports pass/fail verbally.

**Known issue #2 (intermittent, not fully resolved — now confirmed to affect more than `api.php`):** `deploy.ps1`'s curl-based FTP upload sometimes fails (`curl: (56) response reading failed` or `curl: (18) ... got 450`), and the file doesn't actually update on the server despite (or alongside) the reported failure. This session hit it **repeatedly on `add-item.php`** (two separate rounds, 3 and 4 consecutive failures respectively) in addition to the previously-documented `api.php` occurrences. Verify with a diff/marker check when it matters — don't trust a reported failure OR success at face value. Manual upload via Hostinger File Manager is the fallback that has worked every time so far.

---

## Architecture notes not yet in CLAUDE.md

- **`rowCheckboxOffset(tr)`** (`index.html`, module-scope, near `ITEM_EDIT_COLS_MAIN`): returns `1` if a row's first `<td>` contains any `<input type="checkbox">`, else `0`. Used by `editItemByNumber()`/`saveItemEdit()` so cell-index lookups stay correct regardless of which table (Loaded/Donated Items' now-permanent checkbox, or View All's optional one) is being edited. Since Donated Items' checkbox is now permanent (not conditional), `ITEM_EDIT_COLS_MAIN`'s indices are consistently "un-shifted" positions with `off` always adding the checkbox offset on top for that table.
- **`VIEW_ALL_SELECTABLE.items.stripLeadingCol`** (`index.html`, `showViewAll()`): the View All modal clones `#items-table`'s rows and normally adds its *own* selection checkbox. Since Loaded/Donated Items now has a permanent leading checkbox of its own, this flag strips the source table's own checkbox cell/header from the clone first (guarded to skip the single-cell empty-state row) so the two don't stack into a doubled, misaligned column.
- **`PageVisibilityManager`** (`index.html`): originally only handled session-timeout-on-return-from-hidden. Its "page visible again" branch now also re-syncs from the database (`syncFromKeyValueDB()`) and refreshes the Donated Items screen if active — see "Real bug #1" above. Any future feature that opens app-adjacent pages in a separate tab (like `add-item.php`) benefits from this automatically; no per-feature wiring needed.
- **`deploy.ps1`'s `Update-CacheBust()`** (new this session): stamps `?v=<epoch-seconds>` onto `css/table.css`, `css/toolbar.css`, `js/table.js`, `js/toolbar.js` in `index.html` at deploy time. Idempotent — re-running replaces the existing `?v=...` rather than appending a duplicate.
- **`add-item.php`'s "Done" button** now does `window.close()` first, falling back to `location.href='index.html'` after 150ms if the tab wasn't closable (e.g. reached directly rather than via `window.open()` from the app).

---

## Files touched this session

| File | Status | Notes |
|---|---|---|
| `index.html` | committed (`8b32733`) | Donated Items screen overhaul (see above), multi-tab sync fix (`PageVisibilityManager`), Reserve Amount display fix (4 sites + bid-sheet `hasReserve`), Total Value reserve-or-value fallback, Settings Field Mapping/Clear Emails removal |
| `add-item.php` | committed (`8b32733`); also manually uploaded twice mid-session due to FTP failures | "Cancel" → "Done" button relabel + `window.close()`-then-fallback behavior |
| `deploy.ps1` | committed (`8b32733`) | New `Update-CacheBust()` function, wired into both deploy paths |
| `test.html` | committed (`8b32733`) | Substantially rewritten via `/ETCCSAMTest`: 3 fully-stale suites rewritten (old Donated Items modal design, Step 1 button positioning, item-editing cell indices), 6 new suites added (add-item.php Done button, multi-tab sync fix, Reserve Amount bug fix, Total Value fallback, Settings removals, deploy.ps1 cache-busting). Confirmed green by the user before the v4.6 checkpoint. |
| `PROJECT_STATUS.md` | this file, being committed now | continuity doc, not app code |
