# CoachBoard — Acceptance Tests

This document contains the manual acceptance test scenarios for CoachBoard. Run through all scenarios before releasing a new version to production. Record the result of each test. Any failed scenario becomes a GitHub issue before the release continues.

---

## How to use this document

1. Copy the **Test Run** template at the bottom of this file
2. Work through each scenario in order
3. Mark each test `✅ pass` or `❌ fail`
4. For failures: create a GitHub issue, note the issue number in the Remarks column
5. Do not release until all scenarios pass

---

## 1. Authentication

### 1.1 — Request magic link (valid email)
**Context:** Administrator account exists and is active. User is not logged in.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Navigate to the app | Redirected to login page |
| 2 | Enter the administrator email address | — |
| 3 | Click the send button | Confirmation message shown — same message regardless of whether email is known |
| 4 | Open the email inbox | Magic link email received |
| 5 | Check email content | One login link, one sentence about expiry (15 minutes) |

**Result:** ✅ pass — Issue #1-5 — Remarks: ___

---

### 1.2 — Log in via magic link
**Context:** Magic link email has been received. Link has not been used yet.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Click the link in the email | Redirected to dashboard |
| 2 | Check the page | Active season shown, or prompt to create one |
| 3 | Check the bottom navigation | All five items visible |

**Result:** ✅ pass — Issue #6 — Remarks: ___

---

### 1.3 — Reuse magic link
**Context:** A magic link that has already been used once.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Click the same link again | Error message shown |
| 2 | Check error message | Clear message that link is invalid or expired |
| 3 | Check for link back to login | Link to request a new one is present |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 1.4 — Expired magic link
**Context:** A magic link that was generated more than 15 minutes ago.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Click the expired link | Error message shown |
| 2 | Check error message | Clear message that link is invalid or expired |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 1.5 — Request link for unknown email
**Context:** An email address that does not exist in the system.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Enter an unknown email address | — |
| 2 | Click the send button | Same confirmation message as for a known address |
| 3 | Check inbox | No email received |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 1.6 — Logout
**Context:** User is logged in.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Click the logout option in the navigation | Redirected to login page |
| 2 | Check for confirmation message | Logged out message shown |
| 3 | Navigate back using browser back button | Redirected to login page — session is gone |
| 4 | Try to access any page directly via URL | Redirected to login page |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

## 2. Season Management

### 2.1 — Create season without phases
**Context:** User is logged in as administrator. No active season exists.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Navigate to Settings → Seasons | Season list shown |
| 2 | Click New Season | Season form shown |
| 3 | Enter a season name | — |
| 4 | Set "Does this season have phases?" to No | Single start and end date fields shown |
| 5 | Enter a start date and end date | — |
| 6 | Select one or more training days | — |
| 7 | Submit the form | Success message shown with number of training sessions generated |
| 8 | Check the season list | New season appears |
| 9 | Open the season detail | Phase section not visible — only season date range shown |
| 10 | Check the dashboard | Active season name shown, no phase label |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: Meldingen gaan wijder dan het scherm, waardoor ze niet leesbaar zijn.

---

### 2.2 — Create season with phases
**Context:** User is logged in as administrator.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Click New Season | Season form shown |
| 2 | Set "Does this season have phases?" to Yes | Phase fields appear |
| 3 | Enter two or more phases with labels, start dates, end dates | — |
| 4 | Select training days | — |
| 5 | Submit the form | Success message shown |
| 6 | Open the season detail | Phase cards visible with labels and date ranges |
| 7 | Check the dashboard | Active season name and current phase label shown |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: Tekst is krap op mobiel. Gebruik icoontjes (https://www.freeicons.org/icons/phosphor-fill)

---

### 2.3 — Phase validation: overlapping dates
**Context:** Season form open with phases enabled.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Enter two phases with overlapping date ranges | — |
| 2 | Submit the form | Error message shown — season not created |
| 3 | Check error message | Clear explanation that phases must not overlap |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 2.4 — Phase validation: gap between phases
**Context:** Season form open with phases enabled.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Enter two phases with a gap between end of phase 1 and start of phase 2 | — |
| 2 | Submit the form | Error message shown — season not created |
| 3 | Check error message | Clear explanation that phases must be continuous |

**Result:** ✅ pass — Issue #___ — Remarks: Vul automatisch datum in die ingaat op dag naar einde vorige fase

---

### 2.5 — Copy season from existing
**Context:** At least one season already exists.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Start creating a new season | — |
| 2 | Select mode: Copy from existing season | Dropdown with existing seasons appears |
| 3 | Select a source season | — |
| 4 | Complete and submit the form | New season created |
| 5 | Check the squad | Players from source season are present |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 2.6 — Switch active season
**Context:** Two or more seasons exist.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Open the season list | Current active season has active badge |
| 2 | Click Set Active on a different season | — |
| 3 | Check the season list | Active badge moved to selected season |
| 4 | Check the dashboard | Dashboard shows the newly active season |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 2.7 — Add manual training session
**Context:** Active season exists.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Open the season detail | — |
| 2 | Click Add Training Session | Date picker shown |
| 3 | Enter a date within the season range | — |
| 4 | Submit | Training session added |
| 5 | Enter a date outside the season range | — |
| 6 | Submit | Error message — session not created |
| 7 | Enter a date that already has a session | — |
| 8 | Submit | Error message — duplicate not created |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

## 3. Squad Management

### 3.1 — Add player
**Context:** Active season exists.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Navigate to Squad | Player list shown |
| 2 | Click Add Player | Player form shown |
| 3 | Fill in required fields | — |
| 4 | Submit | Player appears in squad list |

**Result:** ✅ pass — Issue #___ — Remarks: Toestemming ouders hier niet nodig. Na invoer terug naar Squad settings. Squad name.

---

### 3.2 — Edit player
**Context:** At least one player exists in the squad.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Open a player profile | Player details shown |
| 2 | Edit a field (e.g. preferred position) | — |
| 3 | Save | Changes reflected in the profile |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 3.3 — Remove player (soft delete)
**Context:** At least one player exists in the squad.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Remove a player from the squad | Player no longer visible in squad list |
| 2 | Check historical match data | Player still appears in past match records |
| 3 | Check historical attendance records | Player still appears in past training records |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

## 4. Match Management

### 4.1 — Create match
**Context:** Active season exists.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Navigate to Matches | Match list shown |
| 2 | Click Add Match | Match form shown |
| 3 | Fill in opponent, date, home/away, match type | — |
| 4 | Submit | Match appears in match list with status: planned |

**Result:** ✅ pass — Issue #___ — Remarks: ___

---

### 4.2 — Prepare match (line-up)
**Context:** A planned match exists.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Open the match | Match detail shown |
| 2 | Select starting eleven and substitutes | — |
| 3 | Save | Line-up saved, match status: prepared |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: Kan niet starten met meer dan 11 spelers. Kan geen spelers plaatsen.

---

### 4.3 — Live match: record goal
**Context:** A prepared match. Match has been started.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Start the match | Match status: active |
| 2 | Record a goal — select player, zone, type | — |
| 3 | Check the score | Score updated immediately |
| 4 | Check the event log | Goal recorded with player name, minute, zone |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

### 4.4 — Live match: record card
**Context:** Active match in progress.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Record a yellow card for a player | Card visible in event log |
| 2 | Record a red card for a player | Card visible in event log |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

### 4.5 — Finish match
**Context:** Active match in progress.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | End the match | Match status: finished |
| 2 | Check match list | Match shows final score |
| 3 | Check that match can no longer be edited live | Live controls no longer available |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

## 5. Training Attendance

### 5.1 — Register attendance
**Context:** A training session exists for today or a recent date.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Navigate to Training | Training session shown |
| 2 | Mark a player as present | Status saved |
| 3 | Mark a player as absent — select reason | Status and reason saved |
| 4 | Mark a player as injured | Status saved |
| 5 | Reload the page | All statuses retained |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

### 5.2 — Absence reason required
**Context:** Training attendance screen open.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Mark a player as absent without selecting a reason | — |
| 2 | Try to save | Validation error — reason is required for absence |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

## 6. General

### 6.1 — Mobile layout
**Context:** Any screen. Use a smartphone or browser device emulation.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Open the app on a small screen (375px wide) | Layout fits without horizontal scroll |
| 2 | Check the bottom navigation | All five items visible and tappable |
| 3 | Check forms | Inputs and buttons are large enough to tap comfortably |
| 4 | Check cards and lists | Text readable without zooming |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

### 6.2 — Unauthenticated access
**Context:** Not logged in.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Try to access `?page=match` directly | Redirected to login |
| 2 | Try to access `?page=player` directly | Redirected to login |
| 3 | Try to access `?page=season` directly | Redirected to login |
| 4 | Try to access `?page=training` directly | Redirected to login |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

### 6.3 — No credentials in source
**Context:** After any commit.

| Step | Action | Expected result |
|------|--------|-----------------|
| 1 | Check that `config/config.php` is not in the repository | File absent from GitHub |
| 2 | Check `.gitignore` | `config/config.php` listed |

**Result:** ✅ pass / ❌ fail — Issue #___ — Remarks: ___

---

## Test Run Template

Copy and fill in for each release.

```
## Test Run — [date] — [version or commit]

Tested by: ___
Environment: local / staging / production

| Scenario | Result | Issue | Remarks |
|----------|--------|-------|---------|
| 1.1 Request magic link (valid email)         | | | |
| 1.2 Log in via magic link                    | | | |
| 1.3 Reuse magic link                         | | | |
| 1.4 Expired magic link                       | | | |
| 1.5 Request link for unknown email           | | | |
| 1.6 Logout                                   | | | |
| 2.1 Create season without phases             | | | |
| 2.2 Create season with phases                | | | |
| 2.3 Phase validation: overlapping dates      | | | |
| 2.4 Phase validation: gap between phases     | | | |
| 2.5 Copy season from existing                | | | |
| 2.6 Switch active season                     | | | |
| 2.7 Add manual training session              | | | |
| 3.1 Add player                               | | | |
| 3.2 Edit player                              | | | |
| 3.3 Remove player (soft delete)              | | | |
| 4.1 Create match                             | | | |
| 4.2 Prepare match (line-up)                  | | | |
| 4.3 Live match: record goal                  | | | |
| 4.4 Live match: record card                  | | | |
| 4.5 Finish match                             | | | |
| 5.1 Register attendance                      | | | |
| 5.2 Absence reason required                  | | | |
| 6.1 Mobile layout                            | | | |
| 6.2 Unauthenticated access                   | | | |
| 6.3 No credentials in source                 | | | |

Failed scenarios: ___
Blocking issues: ___
Released: yes / no
```
