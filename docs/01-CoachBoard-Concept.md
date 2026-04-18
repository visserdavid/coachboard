# CoachBoard — Concept Document
**Version 1.0 — April 2026**

---

## Philosophy

> *CoachBoard shows what matters, when it matters. Nothing more.*

Most coaching tools try to offer everything for everyone. The result is screens full of buttons, statistics nobody reads, and features that distract rather than help — especially on the sideline, where attention belongs on the match.

CoachBoard takes a deliberate opposite approach. It gives coaches a calm, focused tool that supports their decisions without getting in the way. Every feature earns its place by being directly useful in practice.

---

## 1. Introduction

CoachBoard is an open-source web application for football coaches. It covers the full coaching workflow: squad management, match preparation, live match tracking, training attendance, and player development — without the complexity and subscription costs of commercial alternatives.

The application runs in the browser, hosted on a domain of the coach's choice, and is optimised for smartphone use on the sideline.

### Out of scope — deliberate choices

- Parent contact details: privacy risk; communication happens via existing channels
- Match locations: maintained in league applications, duplicating adds no value
- Cancellation notifications: handled by league platforms
- Match announcements: personal communication outside the app
- Squad export as image: rarely used in practice
- Time blocks for substitutions: too rigid for real match situations

---

## 2. Technical Foundation

### 2.1 Stack

| Component | Choice | Rationale |
|---|---|---|
| Frontend | HTML, CSS, JavaScript | Mobile-first, runs in the browser |
| Backend | PHP | Runs on standard shared hosting |
| Database | MySQL | Available on most hosting plans; robust for concurrent access |
| Hosting | Any domain / subdomain | Fully independent of other applications |
| Authentication | Magic links via email | No passwords required |
| Live refresh | Automatic every 60 seconds | Reliable on all devices |
| Localisation | JSON language files | All visible text in a single file per language |

### 2.2 Localisation

All text visible to the user is stored in language files (`lang/en.json`). The application uses a helper function `t('key')` to retrieve the correct string. Switching languages requires only swapping the language file — no code changes.

The default language is English. Additional language files can be contributed independently.

### 2.3 Design Principles

- One screen, one purpose — each screen serves a single task
- Show context-relevant information only — the live match screen shows match data, not season statistics
- Defaults that work — forms open with sensible values; exceptions require deliberate action
- No feature without a clear use case in daily coaching practice

---

## 3. Users and Roles

### 3.1 Authentication

Login is handled via magic links. The user enters their email address and receives a one-time login link. The email address is verified against the database — only known and active addresses receive a link. The confirmation message after submission is always neutral, regardless of whether the address is known. Staff members are added by the administrator.

### 3.2 Roles and Permissions

Staff members can hold multiple roles simultaneously.

| Section | Administrator | Trainer | Coach | Assistant |
|---|---|---|---|---|
| Squad management | ✓ | | | ✓ |
| Training sessions | ✓ | ✓ | | |
| Matches | ✓ | | ✓ | |
| Staff management | ✓ | | | |
| Season management | ✓ | | | |

### 3.3 Staff Profile

- First name
- Email address
- Role(s): trainer, coach, assistant — multiple roles per person allowed

---

## 4. Seasons and Phases

### 4.1 Season

- A season has a name (e.g. "2024-2025")
- Creating a new season copies the current squad as a starting point
- Players can be added, removed or modified per season
- Past seasons remain fully accessible

### 4.2 Phases

Each season is divided into three phases with configurable start and end dates.

| Phase | Period | Purpose |
|---|---|---|
| Phase 1 | Season start to autumn break | Building and settling |
| Phase 2 | Autumn break to winter break | Development and adjustment |
| Phase 3 | Winter break to season end | Consolidation and finish |

Matches and training sessions are automatically assigned to a phase based on date. Statistics can be filtered by phase or full season.

### 4.3 Season Goals

Each phase has an optional short focus description, visible to all staff as a shared thread through the season.

---

## 5. Squad Management

### 5.1 Player Data

| Field | Type | Values / notes |
|---|---|---|
| First name | Text | |
| Squad number | Integer | |
| Preferred foot | String | `right` / `left` / null |
| Preferred line | String | `goalkeeper` / `defence` / `midfield` / `attack` |
| Photo | JPEG upload | Used on player card and livestream |
| Photo consent | Boolean | Required for display on public livestream |

**Note on preferred foot:** this field records a preference, not a technical assessment. Null is a valid value.

### 5.2 Skill Profile

Six skills rated on a scale of 1 to 5, displayed as a radar chart.

| Skill | Description |
|---|---|
| Pace | Sprint speed and acceleration |
| Shooting | Technique and accuracy |
| Passing | Ball control and vision |
| Dribbling | Close control and 1v1 |
| Defending | Anticipation and duels |
| Physicality | Stamina and strength |

- **Season baseline:** entered manually at the start of a season
- **Match rating:** optional post-match rating per player; average displayed on profile rounded to whole stars

---

## 6. Attendance

The same structure is used for both training sessions and matches.

| Status | Reason (if applicable) | Notes |
|---|---|---|
| `present` | | |
| `absent` | `sick` / `holiday` / `school` / `other` | |
| `injured` | | Optional short note, e.g. "ankle, expected 2 weeks" |

The injury note is visible when composing a lineup, giving the coach immediate context.

---

## 7. Training Sessions

### 7.1 Season Schedule

- Two fixed training days per week, configured when creating a season
- The application generates all training dates for the full season automatically
- Individual dates can be marked as cancelled — these are excluded from attendance calculations

### 7.2 Session Content

| Field | Type | Notes |
|---|---|---|
| Focus (KNVB function) | Multiple choice | `attacking` / `defending` / `transitioning` |
| Session notes | Free text | Description of content or observations |

**KNVB functions:** attacking (build-up and scoring), defending (disrupting build-up and preventing goals), transitioning (switching quickly between the two).

### 7.3 Link to Lineup

Attendance over the last 5 training sessions is visible per player when composing a match lineup.

---

## 8. Lineup

### 8.1 Three Contexts, One Component

| Context | Editable | Notes |
|---|---|---|
| Preparation | Yes — fully | Choose formation, place players |
| Live management | Yes — substitutions and position changes | With confirmation step |
| Parent livestream | No — read only | Updated when substitutions are made |

### 8.2 Visual Display

- Top-down view of a green pitch divided into four horizontal zones: goalkeeper, defence, midfield, attack
- Players shown as circles with first name below
- Positions stored as x/y percentages — works on any screen size

### 8.3 Formation

- Choose from a list (e.g. 4-4-2, 4-3-3, 4-2-3-1)
- Default: 4-4-2 diamond midfield
- Changing formation repositions players automatically
- Individual positions can be adjusted manually afterwards

### 8.4 Substitutions

- Tap a player on the pitch → list of available substitutes appears → choose incoming player
- Position change: tap a player → choose new position on the pitch
- All changes require a confirmation step
- Actions can be undone in case of a mistake

---

## 9. Matches

### 9.1 Match Data

| Field | Type | Values / notes |
|---|---|---|
| Date | Date | |
| Kick-off time | Time | |
| Opponent | Text | Stored in normalised form for comparison |
| Home or away | String | `home` / `away` |
| Match type | String | `league` / `tournament` / `friendly` |
| Half duration | Integer (minutes) | Default 45, configurable per match |
| Final score | Two integers | Can be entered manually without live tracking |

### 9.2 Match Status Flow

```
planned → prepared → active → finished
```

Each transition is an explicit action with a confirmation step. Stepping back is possible without data loss.

| Status | Meaning |
|---|---|
| `planned` | Preparation in progress |
| `prepared` | Lineup confirmed, match not yet started |
| `active` | Match in progress |
| `finished` | Match closed |

### 9.3 Match Flow

- Coach starts and stops each half manually
- Half-time is automatically recorded as the gap between two halves
- Closing a match requires a confirmation step
- A half that was stopped accidentally can be resumed without data loss

### 9.4 Guest Players

A guest player exists only within the context of one match. No season profile, no statistics outside the match. Only name and squad number are recorded.

### 9.5 Live Events

| Event | Recorded data |
|---|---|
| Goal | Scorer, optional assist, zone (3×3 grid) |
| Own goal | Zone (3×3 grid) |
| Goal from free kick | Same as goal, flagged as free kick |
| Penalty | Taker, scored or missed, zone if scored |
| Yellow card | Player, minute |
| Red card | Player, minute |
| Note | Free text visible on the livestream |

### 9.6 Goal Zone Grid (3×3)

```
[ Top left    | Top centre    | Top right    ]
[ Middle left | Centre        | Middle right ]
[ Bottom left | Bottom centre | Bottom right ]
```

Database codes: `tl`, `tm`, `tr`, `ml`, `mm`, `mr`, `bl`, `bm`, `br`

### 9.7 Parent Livestream

- Secured via a unique hash URL (e.g. `yourdomain.com/live/a3f8c2d9`)
- Auto-refreshes every 60 seconds — no login required for viewers
- Link is copyable from the preparation screen or live screen
- Displays:
  - Visual lineup — updated when substitutions are made
  - Current score
  - Chronological event timeline: goals with zone, substitutions, cards, notes
  - Player photo on events — only when photo consent is given

### 9.8 Post-Match Rating

- Optional rating of six skills per player present, using stars (1–5)
- Designed for quick input — tap stars per skill
- Not required per match
- Season average displayed on the player profile, rounded to whole stars

---

## 10. Statistics

Filtered by phase or full season. Focused on information that directly supports coaching decisions.

### Per Player
- Matches played and training sessions attended
- Total playing time in minutes
- Goals and assists
- Training attendance percentage
- Average skill rating (whole stars, current season only)

### Per Match
- Final score, goalscorers, assists
- Playing time per player
- Cards

### Per Season / Phase
- Results: wins, draws, losses
- Top scorer and most assists
- Playing time balance — overview of minutes per player

---

## 11. Feature Overview

| Module | Status |
|---|---|
| Squad management | In scope |
| Season and phase management | In scope |
| Training sessions | In scope |
| Lineup | In scope |
| Matches | In scope |
| Attendance | In scope |
| Parent livestream | In scope |
| Statistics | In scope |
| User management | In scope |
| Localisation (i18n) | In scope |
| Time blocks for substitutions | Out of scope |
| Lineup export as image | Out of scope |
| Parent contact details | Out of scope |
| Match location data | Out of scope |
