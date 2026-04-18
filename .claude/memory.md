# CoachBoard — Project Memory

This file tracks progress, decisions and context between Claude Code sessions.
Update this file at the end of every session.

---

## Project Status

**Current phase:** Setup
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
- [ ] Authentication (magic links)
- [ ] Season and phase management
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

*Update this section at the end of each session with what was last completed and what to do next.*

Next session: execute `prompts/01-project-setup.md` in Claude Code.
