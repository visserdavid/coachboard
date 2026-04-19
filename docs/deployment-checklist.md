# CoachBoard — Deployment Checklist

This checklist must be completed before every release to production. Work through each section in order. Do not skip items. Any blocking issue becomes a GitHub issue before the deployment continues.

---

## 1. Code

- [ ] All milestones for this release are committed and pushed to GitHub
- [ ] No uncommitted local changes (`git status` is clean)
- [ ] `config/config.php` is not in the repository — check GitHub to confirm
- [ ] `.gitignore` lists `config/config.php` and `.claude/settings.local.json`
- [ ] No debug output left in code (`var_dump`, `print_r`, `echo` used for debugging)
- [ ] No hardcoded user-facing strings — all text goes through `t('key')`
- [ ] All new language keys are present in `lang/en.json`

---

## 2. Database

- [ ] `database/schema.sql` reflects the current state of the database
- [ ] All schema changes since the last release are documented in the commit history
- [ ] Schema can be imported into a clean MySQL database without errors
- [ ] Any new tables or columns have been applied to the production database
- [ ] `database/seed.sql` admin email has been updated to the real address

---

## 3. Configuration

- [ ] `config/config.php` exists on the server (copied from `config.example.php`)
- [ ] `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` point to the production database
- [ ] `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS` are correct for the live mail account
- [ ] `MAIL_FROM` and `MAIL_FROM_NAME` are set correctly
- [ ] `APP_URL` is set to `https://voetbalapp.dessentie.nu` — no trailing slash
- [ ] `APP_TIMEZONE` is set to `Europe/Amsterdam`

---

## 4. Server

- [ ] PHP version on the server is 8.1 or higher — verify with `phpinfo()` or hosting panel
- [ ] PDO and PDO_MySQL extensions are enabled
- [ ] `mbstring` extension is enabled (required for `utf8mb4`)
- [ ] HTTPS is active on `voetbalapp.dessentie.nu`
- [ ] `.htaccess` is in place and URL routing works — test by visiting a non-root URL
- [ ] PHP error display is off — errors must not be shown to users
- [ ] PHP error logging is on — errors must be written to a log file

---

## 5. Security

- [ ] HTTPS active and HTTP redirects to HTTPS
- [ ] `config/config.php` not accessible via browser — test by visiting the URL directly
- [ ] Sessions use `httponly`, `samesite=Strict` and `secure` cookie settings
- [ ] Magic link tokens expire after 15 minutes and can only be used once
- [ ] No raw PHP error messages visible in the browser on the live site
- [ ] No sensitive data in URL query strings (tokens are never echoed in output)

---

## 6. Functionality

Run the full acceptance test suite (`docs/acceptance-tests.md`) against the production environment after deployment. As a minimum, verify these critical paths immediately after going live:

- [ ] Login page loads at `https://voetbalapp.dessentie.nu`
- [ ] Magic link email is received and works
- [ ] Dashboard loads after login
- [ ] Bottom navigation works on a smartphone
- [ ] Active season is shown on the dashboard

---

## 7. Backup

- [ ] A database backup has been made before applying any schema changes
- [ ] Backup is stored outside the web root — not in `public/`

---

## Deployment Run Template

Copy and fill in for each release.

```
## Deployment — [date] — [version or commit]

Deployed by: ___
Target environment: production
URL: https://voetbalapp.dessentie.nu

### 1. Code
- [ ] All milestones committed and pushed
- [ ] No uncommitted changes
- [ ] config/config.php not in repository
- [ ] No debug output in code
- [ ] All language keys present in en.json

### 2. Database
- [ ] schema.sql reflects current state
- [ ] Schema changes applied to production
- [ ] seed.sql admin email correct

### 3. Configuration
- [ ] config/config.php present on server
- [ ] Database credentials correct
- [ ] Mail credentials correct
- [ ] APP_URL set correctly
- [ ] APP_TIMEZONE set correctly

### 4. Server
- [ ] PHP 8.1+ confirmed
- [ ] PDO and PDO_MySQL enabled
- [ ] HTTPS active
- [ ] .htaccess in place
- [ ] PHP error display off
- [ ] PHP error logging on

### 5. Security
- [ ] HTTP redirects to HTTPS
- [ ] config/config.php not accessible via browser
- [ ] Session cookie settings correct
- [ ] No raw errors visible in browser

### 6. Functionality
- [ ] Login page loads
- [ ] Magic link works
- [ ] Dashboard loads
- [ ] Navigation works on smartphone
- [ ] Active season shown

### 7. Backup
- [ ] Database backup made before schema changes

Blocking issues: ___
Deployed: yes / no
Notes: ___
```
