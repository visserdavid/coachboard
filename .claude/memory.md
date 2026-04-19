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

Last completed: Match preparation — all four milestones done and pushed.

What was built (match preparation):
- `src/match/MatchRepository.php`: getMatchesByTeam (with phase join), getMatchById, createMatch, updateMatch, setStatus, setFormation, deleteMatch, getMatchPlayers, saveMatchPlayer, updateMatchPlayer, removeMatchPlayer, clearMatchPlayers, getPreviousOpponentResult, getRecentMatchesForTemplate, setLivestreamToken
- `src/match/FormationRepository.php`: getAllFormations, getFormationById, getPositionsByFormation, getDefaultFormation
- `src/match/MatchService.php`: createMatch (validates + normalises opponent), updateMatch, deleteMatch, setStatus, confirmPreparation (validates 11 starters), generateLivestreamToken, loadLineupFromTemplate, saveLineup, addGuestPlayer, removeGuestPlayer, ensureAllPresentPlayersInRoster
- `src/match/match_list.php`: timeline with phase dividers, anchor scroll to next upcoming match, score badges with win/draw/loss colouring, previous result against same opponent, status badges (prepared/active)
- `src/match/match_create.php`: form for creating a match (opponent, date, kick-off, home/away radio, match type radio, half duration), redirects to prepare step 1 on success
- `src/match/match_prepare.php`: attendance step 1, status toggle buttons (present/absent/injured), guest player add/remove, present count tracking in JS, "Next" button enabled only when ≥11 present
- `src/match/match_lineup.php`: formation pitch view (green background with CSS), player circles (orange filled / dashed empty), tap to open bottom-sheet modal, assign/clear positions, bench list with attendance dots and injury notes, template selector, formation selector, confirm button (validates 11 starters)
- `src/match/match_live.php`, `match_review.php`: placeholder stubs
- `public/index.php`: match routing block added, requires MatchRepository/FormationRepository/MatchService
- `templates/nav.php`: Matches nav link updated to ?page=match
- `public/css/style.css`: pitch-wrap, pitch-inner, pitch-position, pitch-circle, pitch-name, bench-player, att-dots/att-dot, player-modal-overlay/player-modal styles
- `lang/en.json`: match.previous_result, match.result.win/draw/loss, match.created, match.prepare.*, match.guest.*, match.lineup.*

Key decisions:
- Attendance for match stored with context_type='match' using same polymorphic attendance table
- TrainingRepository::saveAttendance reused for match attendance (context_type param makes it generic)
- match_lineup.php uses JS in-memory roster array and submits full lineup on each change (no AJAX)
- Pitch is a CSS aspect-ratio container (padding-top 150%) with absolute-positioned circles at x/y%
- Formation positions ordered by pos_y ASC for natural top-to-bottom rendering
- ensureAllPresentPlayersInRoster called on lineup page load to add any present players not yet in roster
- Guest players excluded from template loading (no player_id to match)
- nav.php updated: ?page=matches → ?page=match; active class checks 'match' not 'matches'
- $activePage = 'match' set at top of each match page file

Next: **Live match tracking** — prompt 07 (start/stop halves, event registration, substitutions, score).
