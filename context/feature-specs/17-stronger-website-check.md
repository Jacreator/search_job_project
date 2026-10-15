# Stronger Website Check

**Status: draft, awaiting James's approval.**

Make the "company name on the page" check in the verifier stricter, so generic names stop getting the +25 bonus on unrelated sites. Builds on spec 07.

## Problem

`WebsiteVerifier` only looks for the first word of the normalised name on the page. For "Smart Data Ltd" it looks for "smart", for "Digital Solutions UK" it looks for "digital". Almost any page contains those words, so the wrong site gets +25 confidence and can move from amber to green.

## Decisions For Approval

1. Replace the yes or no `mentionsName` with a three-level `App\Enums\NameOnPage`: `Full`, `Partial`, `None`.
2. Scores: `Full` +25 (as today), `Partial` +10, `None` 0. Cap stays at 100.
3. Rows already looked up keep their confidence. Re-scoring them would mean fetching every homepage again, so it is not part of this spec.

## Matching Rules

Both the name and the page text are prepared the same way: the name with `CompanyName::normalise()`; the page text lowercased, apostrophes dropped, and punctuation turned into spaces (so "sainsbury's" holds "sainsburys", as today).

- **Full**: the whole normalised name appears as a phrase on the page ("smart data" for "Smart Data Ltd").
- **Partial**: the name has three or more words, and its first two words appear as a phrase ("acme data" for "Acme Data Services Ltd").
- **None**: anything else. A one-word name ("ASK4 Ltd") gets `Full` when the word is on the page and `None` otherwise. There is no first-word-only match any more.
- Whole words only, through `TermMatcher`, so "smart data" does not match "smartdata" or "smart database".

## Code Changes

- `VerifiedPage`: `mentionsName` (bool) becomes `nameOnPage` (`NameOnPage`). `VerifiedPage::failed()` uses `None`.
- `WebsiteScorer::confidence()` adds the score for the level.
- Spec 07, `architecture-context.md`, and `testing-context.md` updated to the new rule.

## Check When Done

- Unit tests for the matching rules: one-word, two-word, and three-word names, each `Full`, `Partial` where it applies, and `None`; "Smart Data Ltd" on a page that only says "smart" or only "data" gives `None`; apostrophe and punctuation cases.
- `WebsiteScorer` tests: +25, +10, +0, and the cap.
- Existing verifier and enrich tests updated for `nameOnPage`. A fake homepage that only has the first word now gives the lower confidence.
- Live check on the 10 Sheffield sponsors from spec 07: list the confidences before and after, so James can see what changed.
