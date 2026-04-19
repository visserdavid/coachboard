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
- [x] Match preparation
- [x] Live match tracking
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

Last completed: Live match tracking — all four milestones done and pushed.

What was built (live match tracking):
- `src/match/MatchRepository.php`: extended with getMatchHalves, getHalfByNumber, createHalf, startHalf, stopHalf, resumeHalf, getMatchEvents, createEvent, deleteEvent, getSubstitutions, createSubstitution, deleteSubstitution, updatePlayingTime, updatePosition, moveToStartingEleven, moveToBench, getMatchPlayerById, setScore
- `src/match/MatchService.php`: extended with startHalf, stopHalf, resumeHalf, getCurrentMinute, getCurrentHalfNumber, calculatePlayingTime, registerGoal, registerCard, registerNote, deleteEvent, registerSubstitution, undoSubstitution, closeMatch, getScoreFromEvents
- `src/match/match_live.php`: full live screen — score header, half state machine, tab UI (Pitch/Players), pitch view (tappable starters), substitution bottom sheet, position change sheet, player stats tab (time/goals/assists/cards), event timeline, FAB, event registration flows (goal multi-step with zone, own goal, card, note), event deletion, close match flow (stop half + enter score + double confirm → finish)
- `src/match/match_review.php`: review screen — final score with win/draw/loss colour, event timeline, playing time per player, substitution log, reopen match (administrator only)
- `public/css/style.css`: live-score-header, live-tabs, live-player-row, live-player-stats, live-card, live-event-row styles
- `lang/en.json`: live.half.*, live.sub.*, live.position.*, live.event.*, live.close.*, live.minute keys added

Key decisions:
- All time calculations use server timestamps (match_half.started_at/stopped_at), never client timers
- calculatePlayingTime uses half timelines + substitution minutes to compute per-player seconds
- Half state machine: before / h1_running / half_time / h2_running / h2_stopped — drives UI buttons
- Close match: stop half + score entry + double confirm in one JS flow, single POST to close_match action
- Reopen match: sets status back to active, clears goals_scored/goals_conceded — admin only
- Event registration uses multi-step JS bottom sheet with confirmation summary showing player + minute
- Score calculated live from match_event records (not from match.goals_scored during active play)
- livePlayerName() function handles both regular and guest players

Next: **Parent livestream** — prompt 08 (public page with auto-refresh, lineup, timeline, score).
