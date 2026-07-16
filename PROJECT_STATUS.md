# SAM Project Status

**Last updated:** 2026-07-15 (later same-day session — checkpoints v4.4 and v4.5: verified the settings-password guard is holding, added a Developer > Import Members screen, wired member email auto-fill + confirmation-email routing into `add-item.php`, and reworked Donated Items to checkbox-based bulk delete)

This file exists so a brand-new Claude Code session can resume this work with zero prior conversation context. Read this alongside `CLAUDE.md` (architecture/rules) before touching code.

---

## Current state (as of this doc)

- **Deployed version:** **v4.5** (`index.html` footer `#app-version`) — deployed code matches the latest checkpoint commit, no drift this time.
- **Git:** `main` branch, last commit `5a70315` ("Checkpoint v4.5: Import Members screen, member-email auto-fill, Donated Items bulk delete"), pushed to `origin` (https://github.com/BWERepo/ETCCSAM.git). Commits since the prior doc: `9024ff7` (Checkpoint v4.4, trivial cleanup), `bc3edc5` (prior PROJECT_STATUS.md update), `5a70315` (this session's real work).
- **Regression suite (`test.html`) is current and was confirmed green by the user** before the v4.5 checkpoint.
- **No uncommitted app-code work** as of this doc — verify with `git status` regardless.

### ⚠️ Open items carried into the next session

1. **A second "settings password corrupted again" report this session turned out NOT to be a repeat of the corruption bug** — but the underlying question ("why did it fail?") was never fully closed out. Re-verified via the same safe diagnostic pattern (temporary presence/length-only checks added to `add-item.php`, then removed) that: the server-side guard (`sam_guard_settings_passwords`, added last session) is confirmed **live**; the stored `settingsPassword` was stable at 42 characters, not the default, no stray whitespace, and hadn't changed since it was last set correctly. This means the value itself was fine — the user's browser-side check also confirmed the *cached* copy matched (same length, not-default). The most likely explanation is a typing/transcription issue at the actual password prompt (autofill, trailing paste artifact, etc.), not a code bug. **To close this out**: the user was given a console snippet that sets a new password and programmatically fills+submits the auth prompt (bypassing manual typing entirely) to confirm this theory — **no confirmation of the outcome was received before the session moved on.** If the password issue recurs, start here: confirm whether that bypass method worked, rather than re-diagnosing from scratch.
2. **`api.php` deploys are occasionally unreliable via `deploy.ps1`** (a `curl: (56) response reading failed` issue, file doesn't actually update despite the transfer showing 100%). This session's `api.php`-adjacent work didn't hit it, but it's not fixed — if `deploy.ps1 api.php` reports `FAILED`, verify with a temporary marker in the `health` action (add a distinguishing field, deploy, curl `{"action":"health"}`, check for it, remove it, redeploy) rather than trusting the reported result either way. Manual upload via Hostinger File Manager is the fallback that has always worked.
3. **`donate-item.php` remains fully removed** (not just orphaned) — `add-item.php` ("Silent Auction Form") is the only item-donation entry point now. Its old SQL-side backend (`donated_items_pending` table, `get_pending_donations`/`mark_donations_imported` in `api.php`) is still there, unused, per the "flag, don't delete" convention.
4. **The diagnosis playbook for password issues** (add a temporary, read-only, presence/length-only diagnostic to `add-item.php`, gated by a `$_GET` param, never print the actual value or even partial character data — a character-code diagnostic was attempted this session and correctly blocked by the permission system as partial credential exposure) worked well again this session. Keep using it; always remove the diagnostic and redeploy clean afterward.

---

## What was accomplished this session (checkpoints v4.4 → v4.5)

### Checkpoint v4.4 — trivial cleanup, no functional change
The only diff was a stray blank line left in `add-item.php` from a diagnostic-add/remove cycle during the prior session's password investigation. Bumped and committed mainly to formalize what was already live in production from the prior (very large) session.

### Settings-password guard — re-verified, and a false alarm investigated
Before doing any new feature work, re-confirmed (via a temporary `health`-action marker) that the server-side guard added last session — `sam_guard_settings_passwords()` in `api.php`, hooked into both `save_settings` and the generic `set` action — is genuinely deployed and active. It is. When the user then reported the settings password "corrupted again," the same investigation loop from last session was re-run and, this time, found **no actual corruption**: the stored value was stable, correct-length, non-default, and matched the browser's own cached copy. See open item 1 above — the working theory is a transcription issue at the password prompt, not a recurrence of the original bug, but this was never conclusively confirmed before moving on to feature work.

### Developer > Import Members (new screen)
Added a new left-nav Developer submenu item, **"📇 Import Members"** (`data-screen="import-members"`), gated by the same settings-password requirement as the rest of that menu (added to the `developerScreens` array and `screenNames` map in `navigate()`). New screen `#screen-import-members`:
- A CSV file input + **Import** button (`importMembersCsv()`).
- A live table (`#import-members-table`) listing every currently-imported member's Last Name / First Name / Email, with a count and a **Delete All** button (`clearImportedMembers()`).
- Column matching is deliberately simple and flexible: `IMPORT_MEMBERS_COL_ALIASES` maps common header variants (e.g. "Last" / "Last Name" / "Surname" → `last_name`) case-insensitively, and **only** Last Name, First Name, and Email are required/imported — no `member_number` requirement, unlike the older, still-orphaned `importMemberCSV()` (tied to a Settings card removed back in v4.0).
- Saves via the existing `DB.saveMembers()` — the same `sam_members` store already read elsewhere (the ETCC Member Name dropdown, the old orphaned Member Database modal).
- `refreshImportMembersTable()` is called from `navigate()` whenever this screen is shown, so it's always current.

### `add-item.php` — Member Email auto-fill + confirmation-email routing
- New **read-only "Member Email"** field added directly under the ETCC Member Name dropdown (`#f-member-email`).
- Each member `<option>` now carries a `data-email` attribute (from `sam_members`' `primary_email` field); selecting a name calls `samFillMemberEmail()` (a small inline script) to copy that attribute into the Member Email field.
- **On a successful "Donate Item" submission**, if a valid member email was resolved, it now **overrides** the Settings-configured `donationEmailTo` as the confirmation email's **To:** recipient — so the email goes straight to the submitting member instead of (or in addition to, via CC/BCC still coming from Settings) a static staff address. Falls back to the Settings To address when no member (or no email on file for that member) was selected, preserving the prior behavior for that case.

### Donated Items — checkbox column + bulk delete (replaces per-row Delete)
Per explicit request:
- Added a **leading checkbox column** (`.donated-item-row-check`, one per row, plus a header check-all checkbox `#donated-items-check-all-th`).
- Added **"Check All" / "Uncheck All"** buttons to the card-header toolbar, above the table (`selectAllDonatedItemRows(checked)`).
- **Removed the per-row Delete button** — the Actions column now only has Edit.
- Added a new **"Delete Item"** button that bulk-deletes every checked row (`deleteCheckedDonatedItems()`): collects `data-item-number` from all checked boxes, confirms once, filters those out of `Items.getAll()`, saves, cleans up any matching winner records (same orphan-prevention `deleteItemByNumber()` already does), then calls `refreshItemsTable()` (which re-renders both Loaded Items and Donated Items) and `persistItemLoadScreenToDB()`.
- Table `colgroup`/empty-state `colspan`/`min-width` updated for the extra column (11→12 colspan, 1800px→1836px min-width).
- **Fixed a regression this introduced**: `editItemByNumber()`/`saveItemEdit()`'s "does this row have a leading checkbox column" check was hardcoded to look for the View All modal's specific `.item-view-all-checkbox` CSS class. Donated Items' new checkbox uses a different class, so Edit would have silently mis-targeted cells there (the same *category* of bug as the original Donated Items edit fix from two sessions ago, just a new trigger). Fixed by generalizing into a shared `rowCheckboxOffset(tr)` helper — hoisted to module scope so both `editItemByNumber()` and `saveItemEdit()` can use it — that detects **any** `<input type="checkbox">` in a row's first cell, regardless of class name.

### Regression suite
`test.html` updated with new suites for all of the above (Donated Items checkbox/bulk-delete + the offset-fix, Developer > Import Members, and `add-item.php`'s Member Email auto-fill/routing), plus a fix to the now-stale wording in the existing Donated Items suite (it previously said "same Edit/Delete actions" and an 11-column table — now Edit-only actions and 12 columns). Confirmed green by the user before the v4.5 checkpoint.

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

**Known issue #2 (intermittent, not fully resolved):** `api.php` deploys via `deploy.ps1`/curl FTP sometimes fail with `curl: (56) response reading failed`, and the file doesn't actually update on the server despite the transfer showing 100%. Verify with a temporary `health`-action marker when it matters. Manual upload via Hostinger File Manager is the fallback that has always worked.

---

## Architecture notes not yet in CLAUDE.md

- **`rowCheckboxOffset(tr)`** (new, `index.html`, module-scope function near `ITEM_EDIT_COLS_MAIN`/`ITEM_EDIT_COLS_DONATED`): returns `1` if a row's first `<td>` contains any `<input type="checkbox">`, else `0`. Used by `editItemByNumber()`/`saveItemEdit()` to correctly offset cell-index lookups regardless of *which* table's checkbox column (View All's `.item-view-all-checkbox` or Donated Items' `.donated-item-row-check`) is present. If a third table ever gains a leading checkbox column and needs inline editing, this helper already supports it with no changes needed.
- **`IMPORT_MEMBERS_COL_ALIASES`** (new, `index.html`): maps `last_name`/`first_name`/`email` to arrays of accepted (normalized, lowercase, non-alnum-stripped) CSV header aliases, used by `importMembersCsv()`. Update this if a new common header variant needs supporting.
- **`add-item.php`'s `memberEmail` field**: posted alongside `etccMemberName`, populated client-side via each `<option>`'s `data-email` attribute (sourced from `sam_members`' `primary_email`). Server-side, `$values['memberEmail']` — if non-empty and `FILTER_VALIDATE_EMAIL`-valid — overrides `$emailTo` (normally read from `sam_settings.donationEmailTo`) before the confirmation email is sent. CC/BCC still come from Settings regardless.
- **Donated Items table is now 12 columns** (was 11): leading checkbox, then the same 11 as before (Item #, Submission Date, Source, Category, Description, Value, Reserve, Donor Name, Donor Email, Donor Phone, Actions-with-only-Edit). Keep `colgroup`/`<thead>`/`renderDonatedItemsTable()`/empty-state `colspan` in sync if this table's columns change again — this is the same class of bug (`ITEM_EDIT_COLS_DONATED`, `rowCheckboxOffset`) that's already bitten this table twice.
- **`ETCCCheckpoint`** (a global Claude Code skill, not part of this repo — lives at `C:\Users\Admin\.claude\skills\ETCCCheckpoint\SKILL.md`) now handles "bump version + deploy + commit + push" for either this project or CarShow, as a lighter-weight alternative to the full `ETCCSAMCheckpoint`/`ETCCCarShowCheckpoint` skills (which additionally gate on a green test run). Worth knowing about if a future session sees an unexpected version bump/deploy with no corresponding test-confirmation step in the transcript — that's this skill, working as designed, not a violation of the checkpoint procedure above.

---

## Files touched this session

| File | Status | Notes |
|---|---|---|
| `index.html` | committed (`5a70315`) | Developer > Import Members screen + nav wiring, Donated Items checkbox column/bulk-delete + `rowCheckboxOffset()` fix |
| `add-item.php` | committed (`5a70315`, plus a no-op cleanup in `9024ff7`) | Member Email field, `samFillMemberEmail()`, confirmation-email To: override; several temporary read-only diagnostics added/removed during password re-verification (none left in production) |
| `test.html` | committed (`5a70315`) | New suites for all of the above; confirmed green by the user before the checkpoint |
| `PROJECT_STATUS.md` | this file, being committed now | continuity doc, not app code |
