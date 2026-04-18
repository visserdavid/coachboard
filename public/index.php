<?php
declare(strict_types=1);

// Bootstrap
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/src/core/helpers.php';
require_once dirname(__DIR__) . '/src/core/Database.php';
require_once dirname(__DIR__) . '/src/core/Auth.php';
require_once dirname(__DIR__) . '/src/core/Mailer.php';
require_once dirname(__DIR__) . '/src/auth/AuthService.php';
require_once dirname(__DIR__) . '/src/season/SeasonRepository.php';
require_once dirname(__DIR__) . '/src/season/SeasonService.php';

// PHPMailer autoloader (when installed via Composer)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Timezone
date_default_timezone_set(APP_TIMEZONE);

// Session — secure settings
Auth::configureSessionCookieParams();
session_start();

// Router
$page   = preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['page'] ?? 'dashboard'));
$action = preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['action'] ?? ''));

// Auth pages are public; everything else requires login
if ($page === 'auth') {
    switch ($action) {
        case 'verify':
            require dirname(__DIR__) . '/src/auth/verify.php';
            break;
        case 'logout':
            require dirname(__DIR__) . '/src/auth/logout.php';
            break;
        default:
            // Show logged-out confirmation if redirected from logout
            if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
                $_SESSION['flash'] = t('auth.logged_out');
            }
            require dirname(__DIR__) . '/src/auth/login.php';
            break;
    }
    exit;
}

// All other pages require authentication
Auth::requireLogin();

// Lazy-load active season context into session
if (!isset($_SESSION['active_season'])) {
    $seasonRepo = new SeasonRepository();
    $_SESSION['active_season'] = $seasonRepo->getActiveSeason();
    $_SESSION['active_phases'] = $seasonRepo->getPhasesBySeason(
        (int) ($_SESSION['active_season']['id'] ?? 0)
    );
}

// Season pages manage their own output and layout (like auth pages)
if ($page === 'season') {
    Auth::requireRole('is_administrator');
    switch ($action) {
        case 'new':
            require dirname(__DIR__) . '/src/season/season_form.php';
            break;
        case 'detail':
            require dirname(__DIR__) . '/src/season/season_detail.php';
            break;
        case 'set_active':
            require dirname(__DIR__) . '/src/season/set_active.php';
            break;
        case 'add_training':
            require dirname(__DIR__) . '/src/season/add_training.php';
            break;
        default:
            require dirname(__DIR__) . '/src/season/season_list.php';
            break;
    }
    exit;
}

$activePage = $page;

ob_start();

switch ($page) {
    case 'dashboard':
        $activeSeason = getActiveSeason();
        $currentPhase = getCurrentPhase();
        ?>
        <div class="page-header">
            <h1 class="page-title"><?= e(t('dashboard.title')) ?></h1>
        </div>
        <?php if ($activeSeason !== null): ?>
            <div class="card" style="border-left:3px solid var(--color-primary);">
                <div class="text-sm text-muted mb-1"><?= e(t('season.active')) ?></div>
                <strong><?= e($activeSeason['name']) ?></strong>
                <?php if (seasonHasPhases() && $currentPhase !== null): ?>
                    <span class="badge badge--primary" style="margin-left:0.5rem;">
                        <?= e($currentPhase['label'] ?: t('phase.label', ['number' => $currentPhase['number']])) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p class="text-muted"><?= e(t('dashboard.no_season')) ?></p>
                <?php if (!empty($_SESSION['user']['is_administrator'])): ?>
                    <a href="<?= e(APP_URL) ?>/public/index.php?page=season&action=new"
                       class="btn btn--primary btn--sm mt-1"><?= e(t('season.new')) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="card">
            <p class="text-muted text-sm mb-1"><?= e(t('dashboard.upcoming_match')) ?></p>
            <p><?= e(t('dashboard.no_upcoming_match')) ?></p>
        </div>
        <div class="card">
            <p class="text-muted text-sm mb-1"><?= e(t('dashboard.last_results')) ?></p>
            <p><?= e(t('dashboard.no_results')) ?></p>
        </div>
        <div class="card">
            <p class="text-muted text-sm mb-1"><?= e(t('dashboard.season_stats')) ?></p>
            <p><?= e(t('dashboard.no_top_scorer')) ?></p>
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
        <a href="<?= e(APP_URL) ?>/public/index.php?page=season&action=list"
           class="card card--link">
            <div class="flex-between">
                <strong><?= e(t('settings.season')) ?></strong>
                <span class="text-muted">›</span>
            </div>
        </a>
        <div class="card">
            <p><?= e(t('settings.squad')) ?></p>
        </div>
        <div class="card">
            <p><?= e(t('settings.staff')) ?></p>
        </div>
        <div class="card">
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
$title   = t('nav.' . $page);

require dirname(__DIR__) . '/templates/layout.php';
