# SAM Project Status

**Last updated:** 2026-07-15 (session covering: Donated Items screen finalized, a critical settings-password-corruption bug found and fixed both client- and server-side, two unrelated login/reset Content-Type bugs, `donate-item.php` fully removed, a new HTML donation-confirmation email, Settings screen restructuring, and a new `ETCCCheckpoint` skill)

This file exists so a brand-new Claude Code session can resume this work with zero prior conversation context. Read this alongside `CLAUDE.md` (architecture/rules) before touching code.

---

## Current state (as of this doc)

- **Deployed version:** app footer still reads **v4.3** — but the deployed code is actually *ahead* of the v4.3 checkpoint commit. This session's fixes were shipped via `deploy.ps1 <file>` per-file deploys (as always) and committed via the new lightweight `ETCCCheckpoint` skill (commit + push only, no version bump, no full-checkpoint test gate). **The version number needs bumping at the next proper checkpoint** — don't assume "v4.3" on the footer means the code matches the v4.3 checkpoint commit.
- **Git:** `main` branch, last commit `c669922` ("Fix settings-password corruption, Content-Type login bugs; item-donation email overhaul"), pushed to `origin` (https://github.com/BWERepo/ETCCSAM.git). Commit before that: `d7fe3c0` (previous PROJECT_STATUS.md update), before that `0fb89f2` (Checkpoint v4.3).
- **Regression suite (`test.html`) has been updated** to cover everything in this doc, but **has not been run** since these changes landed — the "Checkpoint procedure" below still applies; get a manual green from the user before bumping the version.
- **No uncommitted work** as of this doc (verify with `git status` regardless — that's cheap and this session had a lot of back-and-forth).

### ⚠️ Open items carried into the next session

1. **Version number is stale.** The footer says v4.3 but a large amount of work has shipped since that checkpoint (see below). Bump the version at the next proper checkpoint — don't skip this because the number "looks recent."
2. **`api.php` deploys are still occasionally unreliable via `deploy.ps1`.** This session it worked cleanly most of the time, but the underlying `curl: (56) response reading failed` issue from the prior session was never root-caused — it's a Hostinger/curl-FTP quirk specific to this one file. If a `deploy.ps1 api.php` reports `FAILED`, **verify with a temporary marker in the `health` action** (add a distinguishing field, deploy, curl `{"action":"health"}`, check for the marker, then remove it and redeploy) rather than assuming success or failure. Manual upload via Hostinger File Manager is the fallback that has always worked.
3. **`donate-item.php` is gone — for good, not just orphaned.** It was removed both locally and from the live server (`FTP DELE`, confirmed via a 404) per explicit request ("we only use add-item.php"). Its SQL-side backend (the `donated_items_pending` table and `api.php`'s `get_pending_donations`/`mark_donations_imported` actions) is deliberately left in place, unused, per the "flag, don't delete" convention — but its JS-side helper functions in `index.html` (`importDonatedItemById`, `deletePendingDonation`, `importAllDonatedItems`, `importDonations`) were **deleted outright**, not left orphaned, since nothing could ever call them again. If a future donation-review UI is ever wanted, it would need to be rebuilt from scratch against that still-live backend.
4. **Settings-password corruption should be resolved for good now** (see the full writeup below) — but if it recurs, the diagnosis playbook is: add a temporary read-only diagnostic to `add-item.php` (gated by a `$_GET` param, prints only presence/length booleans and whether a value equals a known default — **never** the actual password value), deploy, curl it, then remove it and redeploy once done. This pattern was used successfully several times this session and always cleaned up afterward — don't leave a diagnostic endpoint live in production.
5. **A real FTP password was exposed in a prior session's transcript** (documented in the previous version of this doc) — already resolved; the user rotated it and updated `.ftp-credentials` themselves. No further action needed, just don't repeat the mistake: never `cat`/`Read` `.ftp-credentials`, `.env`, or any other credentials file directly.

---

## What was accomplished this session

This session picked up immediately after the v4.3 checkpoint (see the prior version of this doc, still in git history at commit `d7fe3c0`, for the full v4.0→v4.3 writeup). Rough chronological summary:

### 1. Donated Items screen — finalized design
Continuing from v4.3's first cut (a review UI for the `donated_items_pending` queue), several rounds of polish landed:
- "Reg #"-style column tweaks, removed the subtitle/metric-row/"Pending Donations" wording, removed the "Import All" button, removed the "Date Loaded" column.
- **Then the real functional bug surfaced**: user reported an item added via `add-item.php` wasn't showing up in Donated Items. Root cause — the screen was still querying the `donated_items_pending` queue, but `add-item.php` writes straight into the live item list instead (a deliberate architectural choice from v4.3). The queue and the screen had diverged from what was actually being used.
- **Fix**: reworked the Donated Items screen to just mirror Loaded Items directly (`Items.getAll()`, same 11-of-12 columns — no "Date Loaded" — same Edit/Delete actions), called from inside `refreshItemsTable()` so it stays live automatically.
- **Follow-on bug**: `editItemByNumber()`/`saveItemEdit()` used hardcoded column-index arithmetic assuming Loaded Items' 12-column layout. Donated Items has 11 (missing "Date Loaded"), so every field after Submission Date was off by one — Edit silently wrote into the wrong (hidden) row. Fixed with two column-index maps, `ITEM_EDIT_COLS_MAIN` and `ITEM_EDIT_COLS_DONATED`, chosen based on which `<tbody>` the matched row is actually in.
- Donor Name/Email columns widened (140→200px / 190→240px) for editing room.

### 2. `sam_current_auction` bug — confirmed fixed and working
The `api.php` key-parsing bug from v4.3 (which silently rejected every `sam_current_auction` write, so the server never knew which auction was current) was confirmed fully resolved this session: after the fix deployed and the app was reloaded once, `sam_current_auction` correctly reads `'a1782226412747'` server-side. New `add-item.php` submissions land in the right place.

### 3. `donate-item.php` — created, used briefly, then removed entirely
Added a **Donation Notification Email** setting and wired it into `donate-item.php` (email a copy of each submission). Then discovered the user had actually been using **`add-item.php`** ("Silent Auction Form") the whole time — `donate-item.php` was getting no traffic. **Explicit instruction: "eliminate donate-item.php since we only use the add-item.php."** Removed the file locally and from the live server (verified via `FTP DELE` + a 404 check). The notification-email feature was ported onto `add-item.php` instead (see #6 below). The JS helper functions tied only to `donate-item.php`'s queue were deleted outright from `index.html` (not left orphaned) since nothing could reach them anymore; the SQL-side backend (`donated_items_pending` table, `get_pending_donations`/`mark_donations_imported` actions in `api.php`) was left in place per the "flag, don't delete" convention.

### 4. `add-item.php` polish
- ETCC Member Name dropdown reformatted to **"Last, First"**, sorted by last name.
- Submission Date now stamped in **Eastern time** (`America/New_York`), formatted **zero-filled `mm/dd/yyyy hh:mm AM/PM`** (e.g. `07/14/2026 01:25 PM`) — went through a couple of format iterations before landing here.
- `source` recorded as plain **`"Added"`**.
- Item Value / Reserve Amount now **auto-format as whole-dollar currency** on blur (no cents).

### 5. Critical bug: settings-password (and login-password) corruption — root-caused and fixed at both layers
User reported both passwords "corrupted" twice in one afternoon. Root cause: `saveSettings()` (the main Settings-screen "Save Settings" button) **rebuilt the entire settings object from scratch** using only the input fields on that screen — which has no field for `password` or `settingsPassword` (they have their own dedicated change-password handlers elsewhere). `DB.saveSettings()` does a **full overwrite** of the stored blob, so every "Save Settings" click silently dropped both passwords, and `DB.getSettings()`'s `Object.assign(DEFAULT_SETTINGS, ...)` fallback then reverted `settingsPassword` to the hardcoded default `'Gladiator#1'` and left `password` undefined. The same bug also silently dropped `externalApiUrl`, `bidRowHeight`, `memberImportDate`, and `regImportDate` (any setting without a form field on that screen).

**Client-side fix:** `saveSettings()` now merges the form fields **onto** `DB.getSettings()` (`Object.assign({}, DB.getSettings(), {...formFields})`) instead of building from scratch — nothing without a form field gets dropped anymore.

**Server-side fix (the durable backstop):** added `sam_guard_settings_passwords($incoming, $pdo)` to `api.php`, hooked into **both** settings-write paths — `save_settings` and the generic `set` action (which the client's auto-sync-on-`localStorage.setItem` hook fires on every write, including `sam_settings`). For `password` and `settingsPassword`, the guard keeps the **existing stored value** unless the incoming value is a genuine change: non-empty, different from what's stored, and (for `settingsPassword` specifically, which has a known public default) not equal to `'Gladiator#1'`. A settings save can no longer blank, drop, or default-revert either password — even from a stale tab or a future client bug. Trade-off: `settingsPassword` can never be set back **to** the literal default `'Gladiator#1'`, which is fine since that's the exact corruption signature being guarded against.

**Recovery process used this session** (documented in case it's needed again): reset the login password via the app's built-in "Forgot password?" flow (see #7 — this required its own bug fix first), then set the settings password directly via the browser console (`DB.getSettings()` → set `.settingsPassword` → `DB.saveSettings()`) since the in-app "Change Password" button was giving the user trouble. Verified server-side each time via a temporary, read-only diagnostic added to `add-item.php` (presence/length/equals-default booleans only, never the actual password value) — always removed and redeployed clean afterward.

### 6. Item donation email — replaced with a proper HTML confirmation, moved to its own Settings card
- Removed the "Item Donation Notifications" card (`donationNotifyEmail`/`donationNotifyCc`).
- Added a new **"Item Donation Confirmation Email"** card, positioned directly below the Security card, with four fields: **To, CC, BCC, Subject** (default subject: "New Item Donation Submitted"). To/CC/BCC accept comma/semicolon-separated multiple addresses. New settings keys: `donationEmailTo`, `donationEmailCc`, `donationEmailBcc`, `donationEmailSubject`.
- **A "To" address is required to enable sending** — CC/BCC alone do not trigger it. (This requirement was briefly removed per a request, then explicitly reverted back to "To required" per an immediate follow-up request — the code and the Settings hint text both reflect "To required" as of this doc.)
- `security-helpers.php`'s `sam_send_mail()` extended with two new trailing (backward-compatible) parameters: `$bcc` and `$html`. New `sam_parse_addr_list()` helper parses comma/semicolon-separated address lists, keeping only entries that pass `FILTER_VALIDATE_EMAIL`. BCC recipients get an extra `RCPT TO` (so they're actually delivered) but deliberately **no `Bcc:` header** (a header would defeat the point of a blind copy).
- `add-item.php`'s confirmation email is now **professional HTML**, not plain text: a centered 64×64 ETCC logo (absolute URL, since email clients can't resolve relative/local paths — `https://etccapps.com/apps/sam/Images/ETCClogoWhiteBackground.png`), an "Item Donation Submitted" heading, a club subtitle, a clean bordered details table (Item #, Member Name, Category, Description, Value, Reserve, Donor info), and a footer.

### 7. Unrelated bug found & fixed: "Forgot password?" / `reset-password.html` Content-Type 415
While chasing the password-corruption symptom, discovered that clicking **"Forgot password?"** on the login screen displayed the raw error text `"Content-Type must be application/json"` instead of the intended "reset link emailed" message. Root cause: that click handler used plain `fetch()` instead of `secureApiFetch()`, so it never sent the `Content-Type: application/json` header that `api.php` requires on all POST requests — a completely separate, pre-existing bug that happened to surface at the same time as the password issue and initially looked related. Fixed by switching to `secureApiFetch()`. The exact same bug existed in `reset-password.html`'s submit handler (plain `fetch()`, no header) — fixed by adding the header explicitly.

### 8. Settings screen — Security card restructured
- "App Access Password" relabeled **"Login Password"**.
- The Settings Password field + its "Change Password" button, previously duplicated in a separate **"Security & Version"** card, were **moved into the same Security card**, directly under Login Password.
- The **"Security & Version" card was removed entirely** — this also removed the "Last Deploy Date" field (`#inp-deploy-date`); `loadSettings()`/`saveSettings()` were updated to null-guard/drop the now-nonexistent element references (the `deployDate` value itself is preserved via the settings merge, just with no form field to edit it from anymore).

### 9. Developer menu auto-expand + focus on settings-password entry
Per explicit request: entering the correct settings password at the auth gate now **expands the Developer submenu** (`#nav-developer-submenu` → `display: block`, `#nav-developer`'s `aria-expanded` → `'true'`) and **moves keyboard focus** to the Developer nav item (`#nav-developer` gained `tabindex="0"` since it's a `<div>`, not natively focusable). This happens in `submitAuthPassword()`, so it fires regardless of what originally triggered the auth gate.

### 10. New skill: `ETCCCheckpoint`
Created `C:\Users\Admin\.claude\skills\ETCCCheckpoint\SKILL.md` — a lightweight **commit + push only** skill (no deploy, no test) usable in either the CarShow or SilentAuctionManager project, whichever is currently active. Explicitly distinct from `ETCCCarShowCheckpoint` (full test+deploy+commit+push) and `ETCCSAMEnd` (this skill — rewrites `PROJECT_STATUS.md` then commits+pushes). Created after repeated confusion this session where `/BWECheckpoint` kept getting invoked for SAM work by habit; the user clarified they wanted a shared commit+push skill for CarShow/SAM specifically, not tied to a version bump or deploy cycle.

### 11. Regression suite
`test.html` updated with suites covering everything above: the Donated Items mirror + edit-column-map fix, the `sam_current_auction` fix, `add-item.php`'s full feature set, `donate-item.php`'s removal, the Content-Type bug fixes, the `saveSettings()` merge fix (a real functional test — reproduces the old bug's behavior, then asserts the fix), the server-side `sam_guard_settings_passwords()` backstop, the Security card restructure, the Developer-menu auto-expand/focus behavior, and the new Item Donation Confirmation Email card + HTML email. **Not yet run** — get a manual green from the user before the next version bump.

---

## Checkpoint procedure (unchanged, still the standing convention)

1. Update `test.html` for whatever changed (skip if nothing test-relevant changed).
2. `.\deploy.ps1 test.html` if it changed.
3. **Ask the user** to manually run https://etccapps.com/apps/sam/test.html and report pass/fail (automated running is broken — see "Known issues" below). Do not proceed until they say it's green.
4. Once green: `.\bump-version.ps1` (minor bump by default; `-Major` flag for major bumps) **if not already bumped earlier in the session** — check the footer span first, don't double-bump. Then `.\deploy.ps1 index.html`.
5. `git add` the changed files (never `git add -A`), commit with a `Checkpoint vX.Y: <short description>` message, `git push`. Commit and push **without asking** once tests are confirmed green.
6. Report the commit hash, version, and live URL back to the user.

Deploying individual files (`.\deploy.ps1 <file>`) happens continuously after every code change, **without being asked** — separate from the commit/checkpoint step. Never commit on every deploy — only at an explicit "checkpoint". Bare "test" (no other words) means **update** the regression suite only — never run it yourself.

**New this session:** a bare "commit, and push" (or invoking the new `ETCCCheckpoint` skill) is a **lighter-weight action** than the full checkpoint procedure above — it skips the version bump and the test-green gate entirely. Use judgment: if the user says "checkpoint" in the full sense (implying a release), follow the numbered procedure; if they say "commit and push" or explicitly invoke `ETCCCheckpoint`, just do steps 1-4 of that skill (status → stage named files → commit → push) with no version bump.

**Known issue #1 (still open):** automated regression test running from a Claude Code session is blocked — Cloudflare challenges any CDP-automated browser (Puppeteer/`run-tests.js`, gitignored/not in git) with a 403 bot-check page. Workaround: the user runs the suite manually and reports pass/fail verbally.

**Known issue #2 (intermittent, not fully resolved):** `api.php` deploys via `deploy.ps1`/curl FTP sometimes fail with `curl: (56) response reading failed`, and the file doesn't actually update on the server despite the transfer showing 100%. This session it mostly worked, but don't assume a `FAILED` output means definite failure or an `OK` output means definite success for this one file specifically — verify with a temporary `health`-action marker when it matters (e.g. before password-guard changes). Manual upload via Hostinger File Manager is the fallback that has always worked.

---

## Architecture notes not yet in CLAUDE.md

- **`sam_guard_settings_passwords($incoming, PDO $pdo)`** (new, `api.php`): the server-side backstop against settings-password corruption. Hooked into `save_settings` and the generic `set` action (only when `key === 'sam_settings'`). For `password` and `settingsPassword`, preserves the existing stored value unless the incoming value is non-empty, different from what's stored, and — for `settingsPassword` — not equal to its known default `'Gladiator#1'`. If nothing is stored yet for a field, the guard steps aside (first-time set always works).
- **`ITEM_EDIT_COLS_MAIN` / `ITEM_EDIT_COLS_DONATED`** (`index.html`): column-index maps used by `editItemByNumber()`/`saveItemEdit()` so in-place row editing works correctly on both Loaded Items (12 columns) and Donated Items (11 columns, missing "Date Loaded"). If either table's columns change again, these maps must be kept in sync or Edit will silently target the wrong cells.
- **`sam_parse_addr_list($raw)`** (new, `security-helpers.php`): splits a comma/semicolon-separated string into validated email addresses, silently dropping anything that fails `FILTER_VALIDATE_EMAIL`. Used for the Item Donation Confirmation Email's To/CC/BCC fields (each can hold multiple addresses).
- **`sam_send_mail($to, $subject, $body, $env, $cc = '', $bcc = '', $html = false)`** (`security-helpers.php`, signature extended this session): `$bcc` recipients get an extra `RCPT TO` only (no header — true blind copy); `$html` switches `Content-Type` to `text/html`. Both new params are backward-compatible defaults — the password-reset email caller in `api.php` is unaffected.
- **Settings keys renamed/added this session:** `donationEmailTo` / `donationEmailCc` / `donationEmailBcc` / `donationEmailSubject` (replacing the removed `donationNotifyEmail` / `donationNotifyCc`). `deployDate` still exists as a settings key but no longer has a Settings-screen input (the "Security & Version" card that held it was removed) — it's preserved by the `saveSettings()` merge, just not editable from that screen anymore.
- **`add-item.php`** now sends its confirmation email as HTML (see the mail-helper changes above) using an absolute logo URL (`https://etccapps.com/apps/sam/Images/ETCClogoWhiteBackground.png`) — a relative path would not resolve in most email clients.
- **Orphaned/dead code, updated this session:** `donate-item.php` itself is **gone** (not orphaned — deleted, both locally and on the server). Its SQL-side backend (`donated_items_pending` table, `get_pending_donations`/`mark_donations_imported` actions in `api.php`) is left in place, orphaned, per convention. Its JS-side helpers (`importDonatedItemById`, `deletePendingDonation`, `importAllDonatedItems`, `importDonations`) were **deleted outright** from `index.html` — the one case this session where code was actually removed rather than flagged, because nothing could ever call them again once `donate-item.php` no longer existed. Everything orphaned as of the prior v4.3 doc (the Add Item modal + its handlers, superseded by `add-item.php`) remains orphaned, not deleted.

---

## Files touched this session

| File | Status | Notes |
|---|---|---|
| `index.html` | committed (`c669922`) | Donated Items mirror rework + edit-column-map fix, Settings restructure (Security card, new Item Donation Confirmation Email card), saveSettings() merge fix, Developer-menu auto-expand/focus, deleted donate-item.php's JS helpers |
| `api.php` | committed (`c669922`) | `sam_guard_settings_passwords()` server-side password guard, hooked into `save_settings` and `set` |
| `add-item.php` | committed (`c669922`) | Member-name dropdown format, zero-filled date/time, whole-dollar currency, HTML confirmation email (logo + styled table), several temporary read-only diagnostics added/removed during the password-corruption investigation (none left in production) |
| `security-helpers.php` | committed (`c669922`) | `sam_send_mail()` extended with `$bcc`/`$html`, new `sam_parse_addr_list()` helper |
| `reset-password.html` | committed (`c669922`) | Content-Type header fix for the `reset_password` fetch call |
| `donate-item.php` | **deleted**, committed (`c669922`) | Removed locally and from the live server (confirmed 404) — superseded entirely by `add-item.php` |
| `test.html` | committed (`c669922`) | New suites for everything in this doc; **not yet run** — get a manual green before the next version bump |
| `C:\Users\Admin\.claude\skills\ETCCCheckpoint\SKILL.md` | new file (not part of this repo/commit — lives in the global Claude skills directory) | Commit+push-only skill for CarShow/SilentAuctionManager |
| `PROJECT_STATUS.md` | this file, being committed now | continuity doc, not app code |
