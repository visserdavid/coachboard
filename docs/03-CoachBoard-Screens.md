# CoachBoard — Screens and User Flows
**Version 1.0 — April 2026**

---

## 1. Design Principles

| Principle | Application |
|---|---|
| Philosophy | Show what matters, when it matters — nothing more |
| Style | Simple, functional, calm — no unnecessary animations |
| Primary colour | Blue `#1259A8` |
| Accent colour | Orange `#E8720C` |
| Background | Light grey `#F8FAFC` |
| Typography | Clear and readable on a phone screen in sunlight |
| Navigation | Fixed bar at the bottom with five icons |
| Interaction | Designed for one hand, thumb use on a phone |
| Consistency | Same timeline pattern reused across matches and training sessions |
| Context awareness | Each screen shows only what is relevant to the current task |

---

## 2. Navigation

Fixed bar at the bottom, always visible. Five icons without text labels. Active section marked in primary blue.

| Position | Section | Icon | Access |
|---|---|---|---|
| 1 | Dashboard | House | All users |
| 2 | Matches | Football | All users |
| 3 | Training | Whistle | All users |
| 4 | Squad | Person silhouette | All users |
| 5 | Settings | Gear | Role-dependent |

---

## 3. Login

### Login Screen

- App name and tagline at the top
- Email address input field
- Button: "Send login link"
- Neutral confirmation message after submission — regardless of whether the address is known

### Verification Flow

```
User submits email address
→ Application looks up address (only active accounts receive a link)
→ Magic link created and sent
→ User taps link in email
→ Token validated for validity and single use
→ Logged in, redirected to dashboard
```

---

## 4. Dashboard

Landing page after login. Compact overview of what is relevant now.

### Upcoming Match
- Opponent, date, kick-off time
- Home or away as a small label
- Button to navigate directly to the match

### Last Three Results
- Opponent and final score per match
- Colour coding: green (win), grey (draw), red (loss)

### Season Statistics
- Matches played
- Wins, draws, losses as numbers
- Top scorer of the season

---

## 5. Matches

### 5.1 Match List

- Chronological timeline — page opens automatically at the next upcoming match
- Scroll up: future matches
- Scroll down: past matches
- Phases separated by a subtle section divider with centred phase label

**Per match card:**
- Date and kick-off time
- Opponent
- Home or away as a small label
- Final score with colour coding — past matches only
- Previous result against the same opponent if available — shown subtly

> Opponents are stored in normalised form (first letter capitalised, rest lowercase) to ensure reliable comparison.

---

### 5.2 Match Status Flow

```
planned → prepared → active → finished
```

Each transition is an explicit action with a confirmation step. Stepping back is possible after confirmation without data loss.

| Status | Screen | Transition to next |
|---|---|---|
| planned | Preparation step 1: attendance | "Next" |
| planned | Preparation step 2: lineup | "Confirm preparation" |
| prepared | Preparation overview | "Start match" |
| active | Live screen | "Close match" with confirmation |
| finished | Review | Post-match ratings |

---

### 5.3 Preparation — Step 1: Attendance

- Progress indicator at the top: step 1 of 2
- List of all squad players — everyone marked as present by default
- Per player: status button — present, absent, or injured
- If absent: dropdown for reason (`sick` / `holiday` / `school` / `other`)
- If injured: optional short text field
- At the bottom: button to add a guest player (name and squad number only)
- Button "Next" to step 2

**Guest player:** exists only within the context of this match — no season profile, no statistics outside the match.

---

### 5.4 Preparation — Step 2: Lineup

- Progress indicator at the top: step 2 of 2
- Dropdown at the top: choose from the 5 most recent matches as a template
  - Label per option: date + opponent
  - No selection: empty pitch as starting point
  - With template: absent players leave their position empty on the pitch
- Visual pitch with player circles in orange
- Fill a position: tap an empty position → choose player from list
- Replace a player: tap an occupied position → choose another player
- Bench shows all present players without a position automatically
- Last 5 training attendance visible per player as colour coding
- Injury note visible per player if present
- Button "Confirm preparation"

---

### 5.5 Live Screen

Two tabs at the top, easy to switch with one tap. Orange floating action button at the bottom right for registering events.

#### Tab 1 — Pitch

- Visual lineup with player circles
- Current score visible at the top
- Tap a player on the pitch: options for substitution or position change
  - Substitution: choose incoming player from bench → confirmation step
  - Position change: choose new position on the pitch
- Start/stop half button prominently placed

#### Tab 2 — Players

- List of all players — pitch players and bench separated
- Per player: name, squad number, playing time in minutes
- Goals and assists as a small number
- Card as a coloured icon — only visible if one was received
- No table lines — distinction via whitespace or subtle background colour

#### Registering Events (floating action button)

| Event | Steps |
|---|---|
| Goal | Tap goal → choose scorer → optional assist → choose zone → saved |
| Own goal | Tap own goal → choose zone → saved |
| Free kick goal | Same as goal, flagged as free kick |
| Penalty | Tap penalty → choose taker → scored or missed → zone if scored → saved |
| Card | Tap card → yellow or red → choose player → saved |
| Note | Tap note → type text → saved |

---

### 5.6 Review

#### Summary
- Final score displayed prominently at the top
- Chronological event timeline
- Playing time per player in minutes

#### Post-Match Ratings
- List of all players present
- Per player: six skills rated via star selection (1–5)
- Optional — can be filled in or adjusted later as long as the season is active

---

### 5.7 Parent Livestream

- Separate public page via unique hash URL
- Auto-refreshes every 60 seconds — no login required for viewers
- Content:
  - Visual lineup — updated when substitutions are made
  - Current score
  - Chronological event timeline: goals with zone, substitutions, cards, notes
  - Player photo on events — only when photo consent is given
- Link is copyable from the preparation overview or live screen

---

## 6. Training Sessions

### 6.1 Training List

Same timeline approach as matches.

- Page opens at the next upcoming training session
- Phases separated by section divider
- Cancelled sessions shown dimmed

**Per training card:**
- Date and day of the week
- KNVB focus icons if filled in:
  - Attacking: arrow pointing up
  - Defending: shield
  - Transitioning: double arrow
- Attendance as `present/total` for past sessions
- Number of absences for upcoming sessions if already known

---

### 6.2 Training Detail

One screen for both upcoming and past training sessions.

- Date, day and time at the top
- Focus selection — multiple choices possible
- Session notes — free text field
- Attendance per player — everyone present by default
- Status options: present, absent (with reason), injured (with optional note)
- Attendance always editable, before and after the session

---

## 7. Squad

### 7.1 Player List

- List of all active players in the current season
- Per player: photo or initials circle, name, squad number, preferred line as label
- Sortable by name or squad number

### 7.2 Player Profile

One scrollable page. Edit button in the top right for users with the appropriate permissions.

| Section | Content |
|---|---|
| Header | Photo or initials circle, name large, squad number |
| Basic info | Preferred foot, preferred line |
| Skill radar | Six scores displayed as a hexagonal radar chart |
| Season statistics | Playing time, matches, goals, assists, training attendance percentage |
| Average rating | Season average displayed as whole stars — only shown if ratings exist |

---

## 8. Settings

Four sections via a simple list menu. Visibility per section depends on the role of the logged-in user.

### 8.1 Squad

- Create new player: name and squad number only
- Deactivate or delete player

> Additional profile details are filled in via the player profile page

### 8.2 Season

- Current season: view phases and edit focus description per phase
- Create new season: name, start/end date, configure phases, set training days
- Start new season from a copy of the current squad
- Past seasons are accessible but not editable

### 8.3 Staff

- Add staff members via email address
- Assign roles: trainer, coach, assistant — multiple roles per person allowed
- Deactivate staff members

### 8.4 Formations

- Manage available formations
- Set default formation
- Create new formation with positions on the pitch

---

## 9. Language File Structure

All visible text is stored in `lang/en.json`. The application retrieves strings via a helper function `t('key')`.

### Key Structure

Keys follow a dot-notation pattern: `section.element`.

**Examples:**

```json
{
  "app.name": "CoachBoard",
  "app.tagline": "Shows what matters, when it matters.",

  "nav.dashboard": "Dashboard",
  "nav.matches": "Matches",
  "nav.training": "Training",
  "nav.squad": "Squad",
  "nav.settings": "Settings",

  "auth.email_placeholder": "Your email address",
  "auth.send_link": "Send login link",
  "auth.link_sent": "If this address is known, you will receive a login link shortly.",

  "match.status.planned": "Planned",
  "match.status.prepared": "Prepared",
  "match.status.active": "In progress",
  "match.status.finished": "Finished",

  "match.home": "Home",
  "match.away": "Away",
  "match.type.league": "League",
  "match.type.tournament": "Tournament",
  "match.type.friendly": "Friendly",

  "match.start": "Start match",
  "match.close": "Close match",
  "match.close_confirm": "Are you sure you want to close this match?",

  "event.goal": "Goal",
  "event.own_goal": "Own goal",
  "event.free_kick": "Free kick",
  "event.penalty": "Penalty",
  "event.yellow_card": "Yellow card",
  "event.red_card": "Red card",
  "event.note": "Note",
  "event.scored": "Scored",
  "event.missed": "Missed",

  "zone.tl": "Top left",
  "zone.tm": "Top centre",
  "zone.tr": "Top right",
  "zone.ml": "Middle left",
  "zone.mm": "Centre",
  "zone.mr": "Middle right",
  "zone.bl": "Bottom left",
  "zone.bm": "Bottom centre",
  "zone.br": "Bottom right",

  "attendance.present": "Present",
  "attendance.absent": "Absent",
  "attendance.injured": "Injured",
  "attendance.reason.sick": "Sick",
  "attendance.reason.holiday": "Holiday",
  "attendance.reason.school": "School",
  "attendance.reason.other": "Other",

  "player.preferred_foot.right": "Right",
  "player.preferred_foot.left": "Left",
  "player.line.goalkeeper": "Goalkeeper",
  "player.line.defence": "Defence",
  "player.line.midfield": "Midfield",
  "player.line.attack": "Attack",

  "skill.pace": "Pace",
  "skill.shooting": "Shooting",
  "skill.passing": "Passing",
  "skill.dribbling": "Dribbling",
  "skill.defending": "Defending",
  "skill.physicality": "Physicality",

  "training.focus.attacking": "Attacking",
  "training.focus.defending": "Defending",
  "training.focus.transitioning": "Transitioning",
  "training.cancelled": "Cancelled",

  "action.save": "Save",
  "action.cancel": "Cancel",
  "action.confirm": "Confirm",
  "action.next": "Next",
  "action.back": "Back",
  "action.edit": "Edit",
  "action.delete": "Delete",
  "action.add": "Add",
  "action.copy_link": "Copy link",

  "guest.label": "Guest player",
  "guest.add": "Add guest player",

  "livestream.title": "Live",
  "livestream.score": "Score",
  "livestream.lineup": "Lineup",
  "livestream.timeline": "Timeline",

  "season.phase_1": "Phase 1",
  "season.phase_2": "Phase 2",
  "season.phase_3": "Phase 3"
}
```

This file serves as the single source of truth for all user-facing text. A Dutch translation (`lang/nl.json`) or any other language can be added without touching application code.

---

## 10. Database Changes Identified During Screen Design

| Table | Change | Reason |
|---|---|---|
| match | Status value `prepared` added | Separate step between confirming preparation and starting the match |
| match_player | Columns added: `is_guest`, `guest_name`, `guest_squad_number` | Guest players exist only within one match context |
| team | Columns added: `training_day_1`, `training_day_2` | Fixed training days for automatic schedule generation |
