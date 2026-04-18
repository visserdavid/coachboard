<?php
declare(strict_types=1);

// Bootstrap
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/src/core/helpers.php';
require_once dirname(__DIR__) . '/src/core/Database.php';
require_once dirname(__DIR__) . '/src/core/Auth.php';

// Timezone
date_default_timezone_set(APP_TIMEZONE);

// Session — secure settings
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => isset($_SERVER['HTTPS']),
]);

// Router
$page = preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['page'] ?? 'dashboard'));

// Public pages (no login required)
$publicPages = ['login', 'auth-verify'];

if (!in_array($page, $publicPages, true)) {
    Auth::requireLogin();
}

// Render
$activePage = $page;

ob_start();

switch ($page) {
    case 'login':
        ?>
        <div class="text-center" style="padding: 3rem 1rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin-bottom: 0.25rem;">
                <?= e(t('app.name')) ?>
            </h1>
            <p class="text-muted mb-2"><?= e(t('app.tagline')) ?></p>
            <form method="post" action="<?= e(APP_URL) ?>/public/index.php?page=auth-request" style="margin-top: 2rem;">
                <div class="form-group">
                    <input
                        type="email"
                        name="email"
                        class="form-input"
                        placeholder="<?= e(t('auth.email_placeholder')) ?>"
                        required
                        autocomplete="email"
                    >
                </div>
                <button type="submit" class="btn btn--primary btn--full">
                    <?= e(t('auth.send_link')) ?>
                </button>
            </form>
        </div>
        <?php
        break;

    case 'dashboard':
        ?>
        <div class="page-header">
            <h1 class="page-title"><?= e(t('dashboard.title')) ?></h1>
        </div>
        <div class="card">
            <p class="text-muted"><?= e(t('dashboard.upcoming_match')) ?></p>
            <p><?= e(t('dashboard.no_upcoming_match')) ?></p>
        </div>
        <div class="card">
            <p class="text-muted"><?= e(t('dashboard.last_results')) ?></p>
            <p><?= e(t('dashboard.no_results')) ?></p>
        </div>
        <?php
        break;

    case 'matches':
        ?>
        <div class="page-header">
            <h1 class="page-title"><?= e(t('match.title')) ?></h1>
        </div>
        <p class="text-muted"><?= e(t('match.no_matches')) ?></p>
        <?php
        break;

    case 'training':
        ?>
        <div class="page-header">
            <h1 class="page-title"><?= e(t('training.title')) ?></h1>
        </div>
        <p class="text-muted"><?= e(t('training.no_sessions')) ?></p>
        <?php
        break;

    case 'squad':
        ?>
        <div class="page-header">
            <h1 class="page-title"><?= e(t('player.title')) ?></h1>
        </div>
        <p class="text-muted"><?= e(t('player.no_players')) ?></p>
        <?php
        break;

    case 'settings':
        ?>
        <div class="page-header">
            <h1 class="page-title"><?= e(t('settings.title')) ?></h1>
        </div>
        <div class="card card--link">
            <p><?= e(t('settings.squad')) ?></p>
        </div>
        <div class="card card--link">
            <p><?= e(t('settings.season')) ?></p>
        </div>
        <div class="card card--link">
            <p><?= e(t('settings.staff')) ?></p>
        </div>
        <div class="card card--link">
            <p><?= e(t('settings.formations')) ?></p>
        </div>
        <?php
        break;

    default:
        http_response_code(404);
        ?>
        <div class="text-center" style="padding: 3rem 1rem;">
            <p class="text-muted"><?= e(t('error.not_found')) ?></p>
            <a href="<?= e(APP_URL) ?>/public/index.php" class="btn btn--primary mt-2">
                <?= e(t('nav.dashboard')) ?>
            </a>
        </div>
        <?php
        break;
}

$content = ob_get_clean();
$title = t('nav.' . $page) ?: t('app.name');

require dirname(__DIR__) . '/templates/layout.php';
