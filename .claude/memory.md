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
- [x] Squad management
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

Last completed: Squad management — all four milestones done and pushed.

What was built (squad management):
- `src/player/PlayerRepository.php`: getPlayersByTeam, getPlayerById, createPlayer, updatePlayer, updatePhotoPath, deletePlayer, restorePlayer, squadNumberExists, getSkillsByPlayer, saveSkills, getAverageRatingsByPlayer, getPlayerSeasonStats
- `src/player/PlayerService.php`: createPlayer, updatePlayer, deletePlayer, uploadPhoto, saveSkills, copyPlayersToSeason — with validation
- `src/player/player_list.php`: squad list with photo/initials circle, sortable by number or name
- `src/player/player_profile.php`: full profile with SVG radar chart, season stats, average ratings
- `src/player/player_edit.php`: two-section form — basic details (admin/assistant) and skill baseline (admin/trainer)
- `src/player/player_delete.php`, `player_restore.php`: soft delete and restore
- `src/player/player_create.php`: minimal create form (name, squad number, photo consent) → redirects to edit
- `src/player/player_manage.php`: settings squad page showing all players (including deleted) with add/delete/restore buttons
- `public/index.php`: squad routing before ob_start(); settings links to squad manage and season list based on role

Key decisions:
- Squad routing follows same pattern as season pages (before ob_start, files manage own ob/layout)
- PlayerRepository handles raw data access; PlayerService handles validation and business logic
- Photo upload saves to public/img/players/ with filename player_{id}_{timestamp}.jpg; stored path is relative (img/players/...)
- Radar chart is inline SVG generated server-side in player_profile.php
- player_manage.php is the settings-accessible page; player_list.php is the regular squad nav page (active players only)

Previous session built:
- `database/schema.sql`: has_phases + team_training_day table
- `src/season/SeasonRepository.php`, `SeasonService.php`
- Season screens: list, form, detail, set_active, add_training
- `src/core/helpers.php`: season context helpers

Next: **Training sessions** — training list, detail, attendance, focus.
