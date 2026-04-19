# CoachBoard — Claude Code Instructions

## Project

CoachBoard is an open-source web application for football coaches. It covers squad management, match preparation, live match tracking, training attendance and player development.

**Repository:** https://github.com/visserdavid/coachboard
**Hosting target:** Standard PHP shared hosting with MySQL
**Live URL:** voetbalapp.dessentie.nu (subdomain, independent of WordPress)

## Philosophy

> *Shows what matters, when it matters. Nothing more.*

Every feature, screen and technical decision must reflect this philosophy. If something does not directly support a coaching decision, it does not belong in the application.

## Technology

- **Frontend:** HTML, CSS, vanilla JavaScript — no frameworks
- **Backend:** PHP 8.1+
- **Database:** MySQL with PDO — prepared statements only, no string interpolation in queries
- **Authentication:** Magic links via email — no passwords
- **Localisation:** All visible text via `t('key')` helper — never hardcode user-facing strings

## Directory Structure

```
coachboard/
├── config/
│   ├── config.example.php    ← committed template
│   └── config.php            ← local only, in .gitignore
├── database/
│   └── schema.sql
├── docs/
│   ├── 01-CoachBoard-Concept.md
│   ├── 02-CoachBoard-Database.md
│   └── 03-CoachBoard-Screens.md
├── lang/
│   └── en.json
├── public/
│   ├── css/style.css
│   ├── js/app.js
│   ├── img/
│   └── index.php             ← single entry point
├── src/
│   ├── core/
│   │   ├── Database.php      ← PDO singleton
│   │   ├── Auth.php          ← session and authentication
│   │   └── helpers.php       ← t() and utilities
│   ├── auth/
│   ├── match/
│   ├── player/
│   ├── season/
│   └── training/
├── templates/
│   ├── layout.php            ← HTML wrapper
│   └── nav.php               ← bottom navigation
└── .htaccess
```

## Coding Conventions

- PHP files use strict types: `declare(strict_types=1);`
- Class files are named with PascalCase: `Database.php`, `MatchEvent.php`
- Functions and variables use camelCase: `getPlayerById()`, `$matchId`
- Database columns use snake_case — match the schema exactly
- All user-facing strings go through `t('key')` — never hardcode text
- All categorical database values are VARCHAR with fixed English keys — no ENUM
- Every query uses prepared statements via PDO
- Errors are caught and handled gracefully — never expose raw PHP errors to the user

## Colour Scheme

```
Primary:    #1259A8   (blue — main actions, active states, headers)
Accent:     #E8720C   (orange — player circles, stars, floating button)
Background: #F8FAFC   (light grey — page background)
Text:       #1a1a2e   (near black — body text)
Success:    #2a7d2a   (green — win, present)
Neutral:    #888888   (grey — draw, muted text)
Danger:     #c0392b   (red — loss, red card)
```

## Database Conventions

- All categorical fields use VARCHAR with fixed English string values
- Valid values are documented in `docs/02-CoachBoard-Database.md` section 1
- Foreign keys use `ON DELETE RESTRICT` unless explicitly documented otherwise
- Soft deletion via `deleted_at TIMESTAMP NULL` — never hard delete players, matches or seasons
- All tables include `created_at` and `updated_at` timestamps

### Key valid values

| Table | Column | Valid values |
|---|---|---|
| player | preferred_foot | `right`, `left` |
| player | preferred_line | `goalkeeper`, `defence`, `midfield`, `attack` |
| training_focus | focus | `attacking`, `defending`, `transitioning` |
| attendance | status | `present`, `absent`, `injured` |
| attendance | absence_reason | `sick`, `holiday`, `school`, `other` |
| match | home_away | `home`, `away` |
| match | match_type | `league`, `tournament`, `friendly` |
| match | status | `planned`, `prepared`, `active`, `finished` |
| match_event | event_type | `goal`, `own_goal`, `yellow_card`, `red_card`, `note` |
| match_event | scored_via | `open_play`, `free_kick`, `penalty` |
| match_event | zone | `tl`, `tm`, `tr`, `ml`, `mm`, `mr`, `bl`, `bm`, `br` |

## Security Rules

- Never commit `config/config.php` — it contains database and mail credentials
- Magic link tokens expire after 15 minutes and can only be used once
- All user input is validated and sanitised before use in queries
- Sessions are started with secure settings: `httponly`, `samesite=strict`
- File uploads (player photos) are validated for type and size before saving

## Git Workflow

Commit after every meaningful milestone — not just at the end of a prompt. Use clear, descriptive commit messages in the imperative: "Add player profile page", not "Added player profile".

Always verify `.gitignore` excludes `config/config.php` and `.claude/settings.local.json` before committing.

## Approach to Complex Decisions

For straightforward implementation tasks, proceed directly. For architectural decisions, security considerations or database design choices, think through consequences and edge cases before writing code. When uncertain between two approaches, choose the simpler one that fits the philosophy.

## Documentation

Full specifications are in `docs/`:
- `01-CoachBoard-Concept.md` — philosophy, features, design decisions
- `02-CoachBoard-Database.md` — complete schema with all tables and indexes
- `03-CoachBoard-Screens.md` — all screens, user flows, colour scheme, language keys

When in doubt about intended behaviour, refer to these documents first.

## Memory

After completing each milestone, update `.claude/memory.md`:
- Mark the milestone as completed by changing `[ ]` to `[x]`
- Add any decisions made or issues encountered under "Key Decisions Made"
- Update "Notes for Next Session" with what was last completed and what comes next

Keep memory.md current — it is the primary way to resume work after 
a session is interrupted.


## Bug Reports and Issues

When the user reports a problem, bug, error message or unexpected behaviour, always follow this order:

1. **Create a GitHub issue first** before attempting any fix. Use a PowerShell here-string to write the body correctly:
$body = @"
## Description

What happened:
[error or problem description]

## Steps to reproduce
[if known]

## Expected behaviour
[what should happen]

## Environment
Local (Laragon) or production
"@
$body | gh issue create --title "Short descriptive title" --label "bug" --body-file -

2. **Note the issue number** returned by the CLI (e.g. #12)

3. **Fix the problem in the code**

4. **Reference the issue in the commit message** — "closes #12" marks the issue as resolved automatically:
git add .
git commit -m "Fix [short description] — closes #12"
git push
If the problem is an improvement rather than a bug, use --label "enhancement" instead of --label "bug".