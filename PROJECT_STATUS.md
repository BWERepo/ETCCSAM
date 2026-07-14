# SAM Project Status

**Last updated:** 2026-07-14 (session covering checkpoints v4.1, v4.2, v4.3 — Reg # prose cleanup, Update Registrations buttons, and the item-donation forms / Donated Items overhaul)

This file exists so a brand-new Claude Code session can resume this work with zero prior conversation context. Read this alongside `CLAUDE.md` (architecture/rules) before touching code.

---

## Current state (as of this doc)

- **Deployed version:** v4.3 (`index.html` footer `#app-version`), deployed to https://etccapps.com/apps/sam/
- **Git:** `main` branch, last commit `0fb89f2` ("Checkpoint v4.3: item-donation forms, Donated Items overhaul, sam_current_auction fix"), pushed to `origin` (https://github.com/BWERepo/ETCCSAM.git)
- **No uncommitted checkpoint work pending.** `PROJECT_STATUS.md` itself remains untracked (a continuity doc, not app code).
- **Regression suite confirmed green** by the user manually at https://etccapps.com/apps/sam/test.html before the v4.3 checkpoint.

### ⚠️ Open items carried into the next session (read this first)

1. **`donate-item.php` submissions currently have no way to reach the app.** The public donation form (`donate-item.php`) still writes into the `donated_items_pending` table via `api.php`'s `get_pending_donations`/`mark_donations_imported` actions — that part is unchanged and still works. But the **Donated Items screen that used to review that queue was repurposed this session to just mirror Loaded Items instead** (see item 5 below), because `add-item.php` bypasses the queue and that's what was actually being watched. There is now **no UI anywhere that shows pending `donate-item.php` submissions** — they just accumulate silently in `donated_items_pending` with `status='pending'`. Either build a new review UI for that queue, or retire `donate-item.php` in favor of `add-item.php` (they have very different trust models: `donate-item.php` is meant for public/donor use with a review step; `add-item.php` is public *and* writes directly into the live item list with no review step at all — see item 3).
2. **`api.php` may still be serving a leftover debug marker.** While diagnosing a deploy failure, a temporary `'deploy_marker' => 'current_auction_fix_v1'` field was added to the `health` action's JSON response, then removed from the source. The removal was deployed via `deploy.ps1 api.php`, which **failed** with the same `curl: (56)` error described in item 4 below — it was never manually re-uploaded afterward. **The live server's `health` action may still return the marker field.** Check with `curl -s -X POST https://etccapps.com/apps/sam/api.php -d '{"action":"health"}'`; if `deploy_marker` is present, the current `api.php` (which has no marker) needs a manual upload via Hostinger File Manager.
3. **`add-item.php` has no password gate**, by explicit user request. Anyone with the URL can insert a real auction item directly into the live item list (bypassing any review). This was a deliberate choice, not an oversight — flagging it here so it isn't mistaken for a bug in a future session.
4. **`api.php` cannot be reliably deployed via `deploy.ps1` / curl FTP.** Multiple attempts this session consistently transferred the file 100% but then failed with `curl: (56) response reading failed (errno: 0)`, and the live file was verifiably NOT updated (confirmed via a temporary marker in the `health` action). Ruled out: stale/wrong FTP password (rotated mid-session, still failed), timeout (`--max-time 120`, no change), EPSV/passive-mode negotiation (`--disable-epsv`, no change). **The only thing that has worked is manually uploading `api.php` via Hostinger's File Manager.** `index.html`, `test.html`, `add-item.php`, and `donate-item.php` all deploy fine via the normal script — this issue seems specific to `api.php` (possibly a Hostinger-side WAF/malware-scan quirk on repeated PHP uploads of that particular file). Don't re-waste time on the same three dead ends; go straight to manual upload if `deploy.ps1 api.php` fails.
5. **3 orphaned items** from before the `sam_current_auction` bug fix (item 6) are sitting under `auction_id=''` in the `items` table / the unnamespaced `sam_items` key in `sam_store` — invisible in the app since it reads `sam_a1782226412747_items`. The user chose to **manually re-enter these** rather than have an automated migration run (a migration script was drafted but the permission system correctly blocked it as an unauthenticated destructive endpoint on production; it was removed without running). These 3 items are still sitting there un-migrated as of this doc.
6. **A real FTP password was exposed in this session's transcript.** While debugging the `api.php` deploy failure, `cat .ftp-credentials` was run to inspect the file, which printed the plaintext FTP password into the conversation. The user rotated the password on Hostinger immediately and updated `.ftp-credentials` themselves. Resolved, but documented here as a hard rule for every future session: **never `cat`/`Read` `.ftp-credentials`, `.env`, or any other credentials file** — `deploy.ps1` already handles them via a generated `.netrc` file without ever printing the values.

---

## What was accomplished this session (v4.0 → v4.3)

### Checkpoint v4.1 — "Reg Number" → "Reg #" in invoice/report prose
Following on from v4.0's column-header rename, the same shortening was applied to prose text that v4.0 had deliberately left alone:
- **Silent Auction Invoice** (`printInvoice()`, `printCheckedInvoices()`, `printAllInvoices()`, and the Winning Bidder Pickup email invoice builder — all 4 render sites): `"Reg Number ${n}"` → `"Reg # ${n}"`.
- **Winning Bidder Report** (`printWinnerReport()`): bidder-block header and the no-bidder-record fallback name both changed to `"Reg # {n}"`.
- **Items Not Paid For** report: column header `"Winning Bidder"` → **`"Winning Reg #"`**.
- Left unchanged (explicitly out of scope): the Auction Summary / Club Income report's `"Reg Number {n}"` fallback name for bidder records with no name on file.

### Checkpoint v4.2 — "Update Registrations" button + Bidder→Reg # rename
- Added an **"Update Registrations"** button to the Record Winning Bidders (Step 4) toolbar, positioned before Clear Filters. Calls `autoLoadBiddersFromApi()`, refreshes the winners table, then shows a confirmation `alert()`.
- Renamed the Registrations (Step 3) screen's existing **"Refresh"** button to **"Update Registrations"** for consistency, and added the same confirmation alert to it (previously silent).
- Renamed the Record Winning Bidders table's **"Bidder"** column header to **"Reg #"**.
- Moved **Regression Tests** out of Settings → Developer Tools and into the left-nav **🛠 Developer** submenu (new item: **"▶ Regression Tests"**, opens `test.html` in a new tab) — same password gate as the rest of the Developer submenu (the whole submenu requires authentication to expand, so no separate gating was needed on the new item itself). Updated the User Manual text and `CLAUDE.md` to match.

### Checkpoint v4.3 — Item-donation forms, Donated Items overhaul, critical bug fixes
This was the largest and most consequential session in a while. Summarized roughly in the order it happened:

**1. `donate-item.php` (new file)** — a public, unauthenticated item-donation form for donors, styled to match the Car Show project's `sponsor-form.php` (logo, red-accent panel look, phone auto-formatting). Fields: Item Category, Description, Item Value, Donor Name, Donor Email, Donor Phone. Submissions INSERT into a new `donated_items_pending` table (its own table — deliberately never written to by `save_items`'s full-replace of the `items` table, so nothing here gets silently wiped by a normal app sync).

**2. `api.php` additions for the pending-donations queue** — new `donated_items_pending` SQL table (auto-created alongside `sam_store`), plus two new authenticated actions: `get_pending_donations` and `mark_donations_imported`.

**3. First "Donated Items" screen design (later reworked — see #7)** — a full-screen modal (button on Load Item Emails' Loaded Items toolbar) that read the pending-donations queue via `get_pending_donations`, with per-row Import/Delete and an Import All button. Iteratively simplified per feedback across several requests: removed its subtitle, removed its own metric row, renamed "Pending Donations" wording to "Donated Items" throughout, removed the Import All button, removed the Date Loaded column.

**4. `add-item.php` (new file)** — a standalone, full-page "Add Item" form with its own bookmarkable URL, replacing the in-app "Add Item" modal for this use case. Went through several rounds of changes:
- Initially password-gated (matching the app's shared staff password) — **gate later removed by explicit request**; the page is now fully public/unauthenticated.
- Writes **directly into the live item store** — both the `items` SQL table and the `sam_store` kv blob (`sam_items` / `sam_{auctionId}_items`) that the app actually reads via `syncFromKeyValueDB()`/`get_all` on every navigation. This was an explicit choice: the user was told that a direct-table-only insert would be silently wiped by the next normal `save_items` call (which does a full delete-and-reinsert per `auction_id`), and chose to accept that risk rather than route through a review queue.
- Uses a transaction with `SELECT ... FOR UPDATE` on the kv row so two concurrent submissions can't compute the same `item_number`.
- Retitled to **"Silent Auction Form"** / **"Item Donation"** (was "Add Item" / "East Tennessee Corvette Club Silent Auction").
- Field order changed to: **ETCC Member Name** (dropdown populated from `sam_members`, formatted `"Last, First"`, sorted by last name) → Donor Name → Donor Email → Donor Phone → **Item Description** (large textarea) → Item Category (dropdown) → Item Value → Reserve Amount. `etcc_member_name` is stored as an extra field on the item's kv-blob JSON object (no corresponding SQL column — won't show up in exports that read straight from the `items` table).
- Submit button renamed **"Donate Item"**; a **Cancel** button was added beside it (reloads the page to clear the form).
- Item Value / Reserve Amount now **auto-format as whole-dollar currency** on blur (e.g. `499` → `$499`, no cents).
- Submission timestamp (`email_date` field, shown as "Submission Date" in tables) is stamped in **Eastern time** (`America/New_York`, matching `api.php`'s convention) in **zero-filled `mm/dd/yyyy hh:mm AM/PM`** format (e.g. `07/14/2026 01:25 PM`) — went through a couple of format iterations (started with seconds and non-zero-filled digits, ended at the format above).
- `source` field is recorded as plain **`"Added"`** (was briefly `"Added (Standalone)"`).

**5. Removed "+ Add Item" and "Import Donated Items" buttons** from the Load Item Emails toolbar (explicit request), since `add-item.php` now covers manual item entry as its own page. Underlying functions (`openAddItemModal()`/`saveAddItemModal()`/`closeAddItemModal()` + the modal HTML, and the original `importDonatedItems()`) left in place but orphaned, per the standing "don't delete, flag instead" convention. (The Add Item modal itself had also been restyled with a logo/heading/subtitle matching `sponsor-form.php`'s look shortly before this — that styling work is now moot since the modal has no UI entry point, but the HTML/CSS is still there.)

**6. Critical bug found & fixed: `sam_current_auction` was never syncing to the server.** While debugging why an `add-item.php` submission wasn't showing up anywhere in the app, root-caused to a bug in `api.php`'s `set` action: its key-allowlist check split every `sam_`-prefixed key on the *last* underscore to find a namespace suffix — but `"current_auction"` itself contains an underscore, so `sam_current_auction` was always misparsed as a namespaced key with suffix `"auction"` (not in the allow-list) and silently rejected. **This means the server has never known which auction was "current," for any user, for as long as this auto-sync mechanism has existed** — a real, previously-undiscovered bug independent of anything else this session. Fixed by checking the full suffix against the allow-list first (exact static match), before falling back to the namespaced-key split. Also added a one-time resync in `index.html`'s `init()` — right after `syncFromKeyValueDB()` — that re-fires `localStorage.setItem('sam_current_auction', ...)` with the browser's existing value, since a plain read wouldn't re-trigger the patched-`setItem` auto-sync-to-server hook; only an explicit write does. Confirmed working after both fixes were live: `sam_current_auction` now correctly reads `'a1782226412747'` server-side.

**7. Second "Donated Items" design — now mirrors Loaded Items directly.** Per explicit feedback ("the loaded items table is being updated when I add a new item but the donated items is not — need the loaded items table on the donated items page"), the screen was reworked to just show `Items.getAll()` — the exact same data, columns, and Edit/Delete actions as Loaded Items (11 columns instead of Loaded Items' 12, missing only "Date Loaded") — instead of querying the pending-donations queue. `renderDonatedItemsTable()` is now called from inside `refreshItemsTable()` too, so the screen stays live even when its modal isn't the one that triggered a change. The old pending-donations-queue functions (`importDonatedItemById`, `deletePendingDonation`, `importAllDonatedItems`, `importDonations`) are left in place but orphaned — see open item 1 above regarding the resulting functional gap for `donate-item.php`.

**8. Bug found & fixed: Edit was silently broken on the new Donated Items table.** `editItemByNumber()`/`saveItemEdit()` used hardcoded cell-index arithmetic assuming the Loaded Items table's 12-column layout (with an `off` adjustment only for the View All modal's optional leading checkbox column). Since Donated Items has 11 columns (no "Date Loaded"), every field after Submission Date was off by one — clicking Edit there was actually editing the correct item's data, but writing it into the wrong (hidden) row in the underlying Loaded Items table, so nothing appeared to happen. Fixed with two column-index maps, `ITEM_EDIT_COLS_MAIN` and `ITEM_EDIT_COLS_DONATED`, selected based on which `<tbody>` the matched row actually belongs to.

**9. Donated Items table column widths increased** for editing room: Donor Name 140px→200px, Donor Email 190px→240px, table `min-width` 1690px→1800px.

**10. Regression suite** (`test.html`) updated with 3 new suites covering all of the above: the Donated Items screen rework (mirroring + edit fix + column widths), the `api.php`/`sam_current_auction` fix + client resync, and the full `add-item.php` feature set (no password gate, field order, member dropdown format, buttons, dual-write behavior, date format, source, currency format). Confirmed green by the user before the checkpoint.

---

## Checkpoint procedure (unchanged, still the standing convention)

1. Update `test.html` for whatever changed (skip if nothing test-relevant changed).
2. `.\deploy.ps1 test.html` if it changed.
3. **Ask the user** to manually run https://etccapps.com/apps/sam/test.html and report pass/fail (automated running is broken — see "Known issues" below). Do not proceed until they say it's green.
4. Once green: `.\bump-version.ps1` (minor bump by default; `-Major` flag for major bumps) **if not already bumped earlier in the session** — check the footer span first, don't double-bump. Then `.\deploy.ps1 index.html`.
5. `git add` the changed files (never `git add -A`), commit with a `Checkpoint vX.Y: <short description>` message, `git push`. Commit and push **without asking** once tests are confirmed green.
6. Report the commit hash, version, and live URL back to the user.

Deploying individual files (`.\deploy.ps1 <file>`) happens continuously after every code change, **without being asked** — separate from the commit/checkpoint step. Never commit on every deploy — only at an explicit "checkpoint". Bare "test" (no other words) means **update** the regression suite only — never run it yourself.

**Known issue #1 (still open, not touched this session):** automated regression test running from a Claude Code session is blocked — Cloudflare challenges any CDP-automated browser (Puppeteer/`run-tests.js`, gitignored/not in git) with a 403 bot-check page. Workaround in continued active use: the user runs the suite manually and reports pass/fail verbally. This has now worked for every checkpoint from v3.10 through v4.3.

**Known issue #2 (new this session):** `api.php` cannot be reliably deployed via `deploy.ps1`/curl FTP — see open item 4 at the top of this doc for full detail. Workaround: manual upload via Hostinger File Manager when the automated deploy fails with `curl: (56)`.

---

## Architecture notes not yet in CLAUDE.md

- **`donated_items_pending` table** (new, `api.php`): `id` (auto-increment), `category_code`, `category_name`, `description`, `item_value`, `donor_name`, `donor_email`, `donor_phone`, `status` (`'pending'`/`'imported'`, default `'pending'`), `submitted_at`. Populated only by `donate-item.php`. Read via `get_pending_donations` (authenticated, returns all `status='pending'` rows), consumed via `mark_donations_imported` (authenticated, takes `{ids: [...]}`, sets `status='imported'`). **As of this doc, nothing in the app reads this queue** — see open item 1.
- **`donate-item.php`** (new, project root): public, no auth. Own lightweight `.env` loader (`donate_load_env()`), separate from `api.php`'s. Category codes/names hardcoded to match `index.html`'s `CATEGORIES` object — keep both in sync if categories ever change.
- **`add-item.php`** (new, project root): public, no auth (gate removed by request). Own lightweight `.env` loader (`additem_load_env()`). Writes to **both** the `items` SQL table and the `sam_store` kv blob in one transaction (`SELECT ... FOR UPDATE` on the kv row for the numbering-race guard). Determines the target `sam_store` key by reading `sam_current_auction` directly from `sam_store` (not from any session/cookie, since this page has none) — this is why the `sam_current_auction` bug (item 6 above) mattered so much for this specific page. Category codes hardcoded here too, same sync-manually caveat as `donate-item.php`.
- **`ITEM_EDIT_COLS_MAIN` / `ITEM_EDIT_COLS_DONATED`** (new, `index.html`): column-index maps used by `editItemByNumber()`/`saveItemEdit()` to support in-place row editing across the two different table layouts (Loaded Items' 12 columns vs. Donated Items' 11 — missing "Date Loaded"). If either table's column set changes again, these maps must be kept in sync, or Edit will silently target the wrong cells again (see bug #8 above for what that looks like).
- **`api.php`'s `set` action key-allowlist logic** (fixed this session): now checks the full suffix (everything after `sam_`) against `$ALLOWED_SUFFIXES` for an exact match *before* attempting to split off a namespace suffix on the last underscore. This matters for any future static key added to `$ALLOWED_SUFFIXES` that itself contains an underscore (like `current_auction`) — the old order-of-checks would have silently broken it the same way.
- **App init resync** (`index.html`, inside the `(async function init() { ... })()` IIFE, right after `await syncFromKeyValueDB();`): re-fires `localStorage.setItem('sam_current_auction', <existing value>)` on every load, specifically to push that value to the server now that `api.php` accepts it. This is a one-time-per-load no-op if the value hasn't changed from the app's own perspective, but it's necessary because only an explicit `setItem` call (not a read) triggers the patched auto-sync-to-server hook.
- **Orphaned/dead code accumulated this session** (deliberately left in place, not deleted, per the standing "protect existing features" convention): `openAddItemModal()`/`saveAddItemModal()`/`closeAddItemModal()` + `#add-item-modal` HTML (superseded by `add-item.php`); `importDonatedItemById()`/`deletePendingDonation()`/`importAllDonatedItems()`/`importDonations()` (superseded by the Donated Items screen's rework to mirror Loaded Items — see open item 1 for why this queue still matters); the original `importDonatedItems()` (superseded even earlier, when the modal-based design replaced the first single-button version). Plus everything already orphaned as of v4.0 (see prior status doc history if needed).

---

## Files touched this session

| File | Status | Notes |
|---|---|---|
| `index.html` | committed (v4.1/v4.2/v4.3, latest `0fb89f2`) | Reg # prose cleanup, Update Registrations buttons, Donated Items screen (built, then reworked), Add Item modal restyle, item-edit column-map fix, sam_current_auction resync |
| `api.php` | committed (`0fb89f2`) | `donated_items_pending` table + 2 new actions, then the `sam_current_auction` key-parsing bug fix. **Deploy is unreliable via `deploy.ps1` — see open item 4.** |
| `add-item.php` | **new file**, committed (`0fb89f2`) | Standalone staff item-entry page — see full description above |
| `donate-item.php` | **new file**, committed (`0fb89f2`) | Public donor item-donation form — see full description above |
| `test.html` | committed (`0fb89f2`) | 3 new suites this session covering all v4.3 work; confirmed green by the user |
| `CLAUDE.md` (shown as `Claude.md` in `git status` on this case-insensitive filesystem) | committed (`0fb89f2`) | Updated the Regression Tests location reference (Settings → Developer Tools → Developer nav submenu) |
| `deploy.ps1` | **no net change** | Temporarily modified during `api.php` deploy debugging (`--max-time 120`, then `--disable-epsv`) but reverted back to its original committed state before the checkpoint — confirmed via `git status` showing no diff |
| `.ftp-credentials` | **not tracked (gitignored)** | Password rotated mid-session after an accidental exposure — see open item 6 |
| `PROJECT_STATUS.md` | uncommitted (untracked) | this file — continuity doc, not app code |
