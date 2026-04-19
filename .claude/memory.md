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
- [x] Training sessions
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

Last completed: Training sessions — all three milestones done and pushed.

What was built (training sessions):
- `src/training/TrainingRepository.php`: getSessionsByTeam (with phase join), getSessionById, createSession, updateSession, getFocusBySession, setFocus, getAttendanceBySession, getAttendanceByPlayer, saveAttendance (manual upsert), getRecentAttendance, getFocusForSessions (batch), getAttendanceSummariesBySessions (batch)
- `src/training/TrainingService.php`: cancelSession, reinstateSession, addManualSession, saveSessionContent, saveAttendance, getAttendanceSummary, getRecentAttendanceForLineup
- `src/training/training_list.php`: chronological timeline with phase dividers (section-divider), focus icons, past attendance summary (present/total), upcoming absence count; JS scroll to anchor session
- `src/training/training_detail.php`: header with date/phase, cancel/reinstate form, section 1 content (focus checkboxes + notes), section 2 attendance (all active players with status toggle buttons + reason/note sub-fields via JS), section 3 summary (past sessions only)
- `src/training/training_cancel.php`, `training_reinstate.php`: POST handlers with redirect
- `public/index.php`: training routing block before ob_start(); require TrainingRepository/TrainingService
- `public/css/style.css`: training-focus-icon, attendance-row, attendance-buttons, att-btn styles
- `lang/en.json`: added training.session, training.add_manual, training.content_saved, training.cancel, training.reinstate, training.reinstated, training.attendance_save, training.attendance_saved, training.summary

Key decisions:
- Training routing uses same if/$page pattern as squad/season (before ob_start, files manage own ob/layout)
- Attendance upsert done manually (SELECT + INSERT/UPDATE) since attendance table has no unique constraint
- Batch loading (getFocusForSessions, getAttendanceSummariesBySessions) for the list view to avoid N+1 queries
- getRecentAttendanceForLineup uses two queries: first gets last 5 session IDs, then fetches all attendance for those sessions
- Attendance past/total in list uses sum of all attendance records (not squad size) — shows 0/0 if no attendance recorded
- Focus icons use inline SVG; attacking=orange, defending=blue, transitioning=green CSS theme colors

Next: **Match preparation** — match list, preparation flow (attendance + lineup), status transitions.
