# CoachBoard — Database Structure
**Version 1.1 — April 2026**

---

## 1. Design Principles

| Principle | Application |
|---|---|
| Naming | Table names singular, snake_case (e.g. `match`, `player`) |
| Primary key | `id` — unsigned integer, auto-increment on every table |
| Foreign keys | Naming pattern: `table_id` (e.g. `season_id`, `player_id`) |
| Timestamps | `created_at` and `updated_at` on every table |
| Soft deletion | `deleted_at` on tables where historical data has value |
| Indexing | Foreign keys and frequently searched columns are indexed |
| Normalisation | Third normal form — no redundant data |
| ENUM replacement | All categorical values stored as VARCHAR with fixed English keys; validated in application code |
| Attendance | Single table for training sessions and matches via polymorphic pattern |
| Field positions | Stored as x/y percentage — screen-size independent |
| Event timeline | Every match event records minute and half number |

### Valid String Values (replacing ENUM)

| Table | Column | Valid values |
|---|---|---|
| player | preferred_foot | `right`, `left` |
| player | preferred_line | `goalkeeper`, `defence`, `midfield`, `attack` |
| training_focus | focus | `attacking`, `defending`, `transitioning` |
| attendance | status | `present`, `absent`, `injured` |
| attendance | absence_reason | `sick`, `holiday`, `school`, `other` |
| formation_position | line | `goalkeeper`, `defence`, `midfield`, `attack` |
| match | home_away | `home`, `away` |
| match | match_type | `league`, `tournament`, `friendly` |
| match | status | `planned`, `prepared`, `active`, `finished` |
| match_event | event_type | `goal`, `own_goal`, `yellow_card`, `red_card`, `note` |
| match_event | scored_via | `open_play`, `free_kick`, `penalty` |
| match_event | zone | `tl`, `tm`, `tr`, `ml`, `mm`, `mr`, `bl`, `bm`, `br` |

---

## 2. Table Overview

| Group | Table | Description |
|---|---|---|
| Authentication | user | Staff members with email and roles |
| Authentication | magic_link | One-time login links |
| Structure | season | Season container |
| Structure | phase | Three phases per season |
| Structure | team | Team linked to a season |
| Players | player | Player data per season |
| Players | player_skill | Season baseline skill ratings |
| Training | training_session | Scheduled training sessions |
| Training | training_focus | KNVB focus areas per session |
| Matches | match | Match data and metadata |
| Matches | match_player | Player per match with position and playing time |
| Matches | match_half | Start/stop times per half |
| Matches | match_event | All events during a match |
| Matches | substitution | Recorded substitutions |
| Ratings | match_rating | Post-match star ratings per player |
| Attendance | attendance | Attendance for training sessions and matches |
| Formations | formation | Available formations |
| Formations | formation_position | Default positions per formation |

---

## 3. Authentication

### user

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| first_name | VARCHAR(100) | No | |
| email | VARCHAR(255) | No | Unique |
| is_administrator | TINYINT(1) | No | Default 0 |
| is_trainer | TINYINT(1) | No | Default 0 |
| is_coach | TINYINT(1) | No | Default 0 |
| is_assistant | TINYINT(1) | No | Default 0 |
| active | TINYINT(1) | No | Default 1 |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

### magic_link

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| user_id | INT UNSIGNED | No | FK → user.id |
| token | VARCHAR(255) | No | Unique, securely generated hash |
| expires_at | TIMESTAMP | No | Creation time + 15 minutes |
| used_at | TIMESTAMP | Yes | NULL until used |
| created_at | TIMESTAMP | No | Automatic |

**Verification flow:**
1. User submits email address
2. Application looks up address — link is only created for known, active accounts
3. Confirmation message is always neutral regardless of whether address exists
4. Token validated against existence, `expires_at`, and `used_at IS NULL`

---

## 4. Structure

### season

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| name | VARCHAR(50) | No | e.g. "2024-2025" |
| active | TINYINT(1) | No | Default 1 — one active season at a time |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |
| deleted_at | TIMESTAMP | Yes | Soft deletion |

### phase

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| season_id | INT UNSIGNED | No | FK → season.id |
| number | TINYINT | No | 1, 2 or 3 |
| label | VARCHAR(100) | Yes | Optional name |
| focus | TEXT | Yes | Season goal for this phase |
| start_date | DATE | No | |
| end_date | DATE | No | |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

### team

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| season_id | INT UNSIGNED | No | FK → season.id |
| name | VARCHAR(100) | No | e.g. "U15-1" |
| training_day_1 | TINYINT | Yes | 1=Monday through 7=Sunday |
| training_day_2 | TINYINT | Yes | 1=Monday through 7=Sunday |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

---

## 5. Players

### player

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| team_id | INT UNSIGNED | No | FK → team.id |
| first_name | VARCHAR(100) | No | |
| squad_number | TINYINT UNSIGNED | Yes | |
| preferred_foot | VARCHAR(10) | Yes | `right` / `left` / null |
| preferred_line | VARCHAR(20) | Yes | `goalkeeper` / `defence` / `midfield` / `attack` |
| photo_path | VARCHAR(500) | Yes | Relative path to JPEG on server |
| photo_consent | TINYINT(1) | No | Default 0 — required for livestream |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |
| deleted_at | TIMESTAMP | Yes | Soft deletion |

### player_skill

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| player_id | INT UNSIGNED | No | FK → player.id |
| season_id | INT UNSIGNED | No | FK → season.id |
| pace | TINYINT UNSIGNED | Yes | Scale 1–5 |
| shooting | TINYINT UNSIGNED | Yes | Scale 1–5 |
| passing | TINYINT UNSIGNED | Yes | Scale 1–5 |
| dribbling | TINYINT UNSIGNED | Yes | Scale 1–5 |
| defending | TINYINT UNSIGNED | Yes | Scale 1–5 |
| physicality | TINYINT UNSIGNED | Yes | Scale 1–5 |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

**Index:** UNIQUE on `(player_id, season_id)` — one baseline per player per season

---

## 6. Training Sessions

### training_session

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| team_id | INT UNSIGNED | No | FK → team.id |
| date | DATE | No | |
| cancelled | TINYINT(1) | No | Default 0 — excluded from attendance calculation |
| notes | TEXT | Yes | Free description of session content |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

### training_focus

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| training_session_id | INT UNSIGNED | No | FK → training_session.id |
| focus | VARCHAR(20) | No | `attacking` / `defending` / `transitioning` |

---

## 7. Attendance

Single table for training sessions and matches via polymorphic pattern.

### attendance

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| player_id | INT UNSIGNED | No | FK → player.id |
| context_type | VARCHAR(20) | No | `training_session` or `match` |
| context_id | INT UNSIGNED | No | FK → training_session.id or match.id |
| status | VARCHAR(10) | No | `present` / `absent` / `injured` |
| absence_reason | VARCHAR(10) | Yes | `sick` / `holiday` / `school` / `other` |
| injury_note | VARCHAR(255) | Yes | Only when status = injured |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

**Index:** on `(context_type, context_id)` and on `player_id`

---

## 8. Formations

### formation

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| name | VARCHAR(50) | No | e.g. "4-4-2 diamond", "4-3-3" |
| outfield_players | TINYINT UNSIGNED | No | Default 10 |
| is_default | TINYINT(1) | No | Default 0 |

### formation_position

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| formation_id | INT UNSIGNED | No | FK → formation.id |
| position_label | VARCHAR(50) | No | e.g. "Right back", "Striker" |
| line | VARCHAR(20) | No | `goalkeeper` / `defence` / `midfield` / `attack` |
| pos_x | DECIMAL(5,2) | No | Horizontal 0–100 (% of pitch width) |
| pos_y | DECIMAL(5,2) | No | Vertical 0–100 (% of pitch length) |

---

## 9. Matches

### match

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| team_id | INT UNSIGNED | No | FK → team.id |
| formation_id | INT UNSIGNED | Yes | FK → formation.id |
| date | DATE | No | |
| kick_off_time | TIME | Yes | |
| opponent | VARCHAR(150) | No | Stored normalised for comparison |
| home_away | VARCHAR(5) | No | `home` / `away` |
| match_type | VARCHAR(15) | No | `league` / `tournament` / `friendly` |
| half_duration_minutes | TINYINT UNSIGNED | No | Default 45 |
| goals_scored | TINYINT UNSIGNED | Yes | Final score — own team |
| goals_conceded | TINYINT UNSIGNED | Yes | Final score — opponent |
| status | VARCHAR(10) | No | Default `planned` |
| livestream_token | VARCHAR(64) | Yes | Unique hash for parent link |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |
| deleted_at | TIMESTAMP | Yes | Soft deletion |

**Status values:** `planned` → `prepared` → `active` → `finished`

### match_player

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| match_id | INT UNSIGNED | No | FK → match.id |
| player_id | INT UNSIGNED | Yes | FK → player.id — null for guest players |
| is_guest | TINYINT(1) | No | Default 0 |
| guest_name | VARCHAR(100) | Yes | Only when is_guest = 1 |
| guest_squad_number | TINYINT UNSIGNED | Yes | Only when is_guest = 1 |
| in_starting_eleven | TINYINT(1) | No | 1 = starting eleven, 0 = bench |
| position_label | VARCHAR(50) | Yes | e.g. "Right back" |
| pos_x | DECIMAL(5,2) | Yes | Current position (% of width) |
| pos_y | DECIMAL(5,2) | Yes | Current position (% of length) |
| playing_time_seconds | INT UNSIGNED | No | Cumulative, updated live |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

**Index:** UNIQUE on `(match_id, player_id)` for registered players

### match_half

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| match_id | INT UNSIGNED | No | FK → match.id |
| number | TINYINT UNSIGNED | No | 1 or 2 |
| started_at | TIMESTAMP | Yes | Moment half was started |
| stopped_at | TIMESTAMP | Yes | NULL while half is in progress |
| created_at | TIMESTAMP | No | Automatic |

**Index:** UNIQUE on `(match_id, number)`

### match_event

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| match_id | INT UNSIGNED | No | FK → match.id |
| half | TINYINT UNSIGNED | No | 1 or 2 |
| minute | TINYINT UNSIGNED | No | Match minute |
| event_type | VARCHAR(15) | No | `goal` / `own_goal` / `yellow_card` / `red_card` / `note` |
| player_id | INT UNSIGNED | Yes | FK → player.id |
| assist_player_id | INT UNSIGNED | Yes | FK → player.id |
| scored_via | VARCHAR(15) | Yes | `open_play` / `free_kick` / `penalty` |
| penalty_scored | TINYINT(1) | Yes | Only for penalty events |
| zone | VARCHAR(2) | Yes | `tl` `tm` `tr` `ml` `mm` `mr` `bl` `bm` `br` |
| note_text | TEXT | Yes | Only for note events |
| created_at | TIMESTAMP | No | Automatic |

### substitution

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| match_id | INT UNSIGNED | No | FK → match.id |
| half | TINYINT UNSIGNED | No | 1 or 2 |
| minute | TINYINT UNSIGNED | No | Match minute |
| player_off_id | INT UNSIGNED | No | FK → player.id |
| player_on_id | INT UNSIGNED | No | FK → player.id |
| created_at | TIMESTAMP | No | Automatic |

---

## 10. Post-Match Ratings

### match_rating

| Column | Type | Null | Notes |
|---|---|---|---|
| id | INT UNSIGNED | No | Primary key |
| match_id | INT UNSIGNED | No | FK → match.id |
| player_id | INT UNSIGNED | No | FK → player.id |
| pace | TINYINT UNSIGNED | Yes | Scale 1–5 |
| shooting | TINYINT UNSIGNED | Yes | Scale 1–5 |
| passing | TINYINT UNSIGNED | Yes | Scale 1–5 |
| dribbling | TINYINT UNSIGNED | Yes | Scale 1–5 |
| defending | TINYINT UNSIGNED | Yes | Scale 1–5 |
| physicality | TINYINT UNSIGNED | Yes | Scale 1–5 |
| created_at | TIMESTAMP | No | Automatic |
| updated_at | TIMESTAMP | No | Automatic |

**Index:** UNIQUE on `(match_id, player_id)`

---

## 11. Indexes

| Table | Index on | Type | Purpose |
|---|---|---|---|
| user | email | UNIQUE | Login lookup |
| magic_link | token | UNIQUE | Token verification |
| magic_link | user_id | INDEX | FK |
| phase | season_id | INDEX | FK |
| player | team_id | INDEX | FK |
| player | deleted_at | INDEX | Soft deletion filter |
| player_skill | (player_id, season_id) | UNIQUE | One baseline per season |
| training_session | (team_id, date) | INDEX | Filter by period |
| attendance | (context_type, context_id) | INDEX | Polymorphic lookups |
| attendance | player_id | INDEX | FK |
| match | (team_id, date) | INDEX | Filter by period |
| match | livestream_token | UNIQUE | Livestream page lookup |
| match_player | (match_id, player_id) | UNIQUE | One record per player |
| match_event | match_id | INDEX | FK |
| match_half | (match_id, number) | UNIQUE | One half per number |
| substitution | match_id | INDEX | FK |
| match_rating | (match_id, player_id) | UNIQUE | One rating per player |

---

## 12. Relationships

| From | To | Relation | Notes |
|---|---|---|---|
| season | phase | 1 → N | Each season has three phases |
| season | team | 1 → N | Structure supports multiple teams |
| team | player | 1 → N | Players per team per season |
| team | training_session | 1 → N | Sessions per team |
| team | match | 1 → N | Matches per team |
| player | player_skill | 1 → N | One baseline per season |
| training_session | training_focus | 1 → N | Multiple focus areas per session |
| training_session / match | attendance | 1 → N | Via polymorphic pattern |
| formation | formation_position | 1 → N | Positions per formation |
| match | match_player | 1 → N | All players per match |
| match | match_half | 1 → N | Start/stop per half |
| match | match_event | 1 → N | Events in chronological order |
| match | substitution | 1 → N | Substitutions per match |
| match | match_rating | 1 → N | Post-match ratings |
