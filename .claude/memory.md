# CoachBoard — Project Memory

This file tracks progress, decisions and context between Claude Code sessions.
Update this file at the end of every session.

---

## Project Status

**Current phase:** Core feature development
**Last updated:** April 2026

---

## Completed Milestones

- [x] GitHub repository created: https://github.com/visserdavid/coachboard
- [x] Local repository cloned and configured
- [x] Three documentation files added to `docs/`
- [x] README.md written
- [x] `.gitignore` configured
- [x] `prompts/` directory created and added to `.gitignore`
- [x] `.claude/settings.local.json` configured with permissions
- [x] Milestone 1 — Directory structure and configuration
- [x] Milestone 2 — Database schema
- [x] Milestone 3 — Core PHP files
- [x] Milestone 4 — Base layout, stylesheet and entry point
- [x] Authentication (magic links)
- [x] Season and phase management
- [ ] Squad management
- [ ] Training sessions
- [ ] Match preparation
- [ ] Live match tracking
- [ ] Parent livestream
- [ ] Post-match review and ratings
- [ ] Statistics
- [ ] Settings (staff, formations, seasons)

---

## Key Decisions Made

### Architecture
- PHP backend, MySQL database, vanilla JS frontend — no frameworks
- Single entry point via `public/index.php` with `?page=` routing
- PDO singleton pattern for database access
- All categorical database values stored as VARCHAR — no ENUM

### Authentication
- Magic links via email — no passwords
- Tokens expire after 15 minutes, single use only
- Neutral confirmation message regardless of whether email is known

### Localisation
- All user-facing text via `t('key')` helper
- Language file: `lang/en.json`
- Dutch translation to be added after the application is working

### Lineup
- Visual pitch with player circles (orange `#E8720C`)
- Positions stored as x/y percentage — screen-size independent
- Substitutions via tap, not drag — more reliable on a phone

### Livestream
- Unique hash URL per match — no login required for viewers
- Auto-refresh every 60 seconds
- Player photo shown on events only when photo consent is given

### Statistics
- Minimal scope: playing time, goals, assists, attendance, average rating
- No trend lines — average skill rating per season shown as whole stars
- Filtered by phase or full season

### Guest players
- Exist only within one match context
- No season profile, no statistics outside the match
- Stored in `match_player` with `is_guest = 1`

---

## Open Questions

*None at this stage. Add questions here as they arise during development.*

---

## Notes for Next Session

Last completed: Season and phase management — all three milestones done and pushed.

What was built:
- `database/schema.sql`: added `has_phases` to `season`, replaced `training_day_1/2` on `team` with a new `team_training_day` table for flexible training days
- `src/season/SeasonRepository.php`: full data layer for seasons, phases, teams, training days
- `src/season/SeasonService.php`: createNewSeason, createSeasonFromCopy, setActiveSeason, generateTrainingSchedule, addManualTrainingSession, validatePhases
- Season screens: list, create form (blank/copy modes, optional phases, training days), detail (edit phases/training days, add manual session), set_active, add_training
- `src/core/helpers.php`: getActiveSeason(), getActivePhases(), getCurrentPhase(), seasonHasPhases()
- `public/index.php`: lazy-loads season context on every authenticated request; settings page links to season management; dashboard shows active season and current phase

Key decisions:
- Season routing handled before `ob_start()` in index.php (same pattern as auth), because season screen files manage their own ob/layout
- When creating a season, a team is created automatically with the same name as the season
- `generateTrainingSchedule` is NOT called inside `createNewSeason` — the caller (season_form.php) calls it after creation and uses the return value for the success message count
- Seasons are created as inactive (active=0); first activation is done via set_active

Next: **Squad management** — player creation, profile page, skill baselines.
