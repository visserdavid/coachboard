# CoachBoard

> *Shows what matters, when it matters. Nothing more.*

CoachBoard is an open-source web application for football coaches. It covers the full coaching workflow — squad management, match preparation, live match tracking, training attendance and player development — without the complexity and subscription costs of commercial alternatives.

Built to run in the browser on any standard PHP hosting environment, optimised for smartphone use on the sideline.

---

## Philosophy

Most coaching tools try to offer everything for everyone. The result is screens full of buttons, statistics nobody reads, and features that distract rather than help — especially on the sideline, where attention belongs on the match.

CoachBoard takes a deliberate opposite approach. Every feature earns its place by being directly useful in daily coaching practice.

---

## Features

- **Squad management** — player profiles with skill ratings, photo and attendance history
- **Match preparation** — lineup builder with position assignments and substitution planning
- **Live match tracking** — real-time event registration: goals, cards, substitutions and notes
- **Match review** — post-match player ratings and match summary
- **Parent livestream** — secure public page that updates every 60 seconds, no login required
- **Training sessions** — attendance registration, session notes and training focus per phase
- **Statistics** — playing time balance, goals, cards and attendance per player and season
- **Season phases** — divide a season into three phases with individual focus goals
- **Formation management** — create and manage custom formations with saved player positions
- **Staff management** — invite staff members and assign roles per season
- **Magic link login** — no passwords; staff log in via a one-time link sent to their email
- **Role-based access** — administrator, trainer, coach and assistant roles
- **Localisation** — all visible text stored in a single language file (`lang/en.json`)

---

## Technology

| Component | Choice |
|---|---|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP |
| Database | MySQL |
| Authentication | Magic links via email |
| Localisation | JSON language files |

CoachBoard runs on standard shared hosting. No special server configuration required.

---

## Getting Started

### Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- A web server (Apache or Nginx)
- An email account for sending magic links

### Installation

1. Clone the repository:
   ```
   git clone https://github.com/visserdavid/coachboard.git
   ```

2. Copy the example configuration file and fill in your database and mail credentials:
   ```
   cp config/config.example.php config/config.php
   ```

3. Import the database schema:
   ```
   mysql -u youruser -p yourdb < database/schema.sql
   ```

4. Point your web server to the `public/` directory.

5. Open the application in your browser and log in with the administrator email address you configured.

---

## Localisation

All user-facing text is stored in `lang/en.json`. To add a new language, copy that file, translate the values and set the active language in your configuration.

```json
{
  "app.name": "CoachBoard",
  "match.start": "Start match",
  "attendance.present": "Present"
}
```

---

## Documentation

Full documentation is available in the `docs/` directory:

- [`01-CoachBoard-Concept.md`](docs/01-CoachBoard-Concept.md) — philosophy, features and design decisions
- [`02-CoachBoard-Database.md`](docs/02-CoachBoard-Database.md) — database structure and relationships
- [`03-CoachBoard-Screens.md`](docs/03-CoachBoard-Screens.md) — screens, user flows and interface design

---

## Contributing

Contributions are welcome. If you find a bug or have a feature suggestion, please open an issue. Pull requests are accepted for bug fixes and improvements that align with the CoachBoard philosophy: focused, minimal, directly useful.

---

## License

MIT License — see [LICENSE](LICENSE) for details.
