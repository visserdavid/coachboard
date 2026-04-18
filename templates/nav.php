<?php declare(strict_types=1); ?>
<?php if (!empty($_SESSION['flash'])): ?>
<div class="flash-message" role="alert"><?= e($_SESSION['flash']) ?></div>
<?php unset($_SESSION['flash']); endif; ?>
<nav class="bottom-nav" role="navigation" aria-label="Main navigation">
    <a href="<?= APP_URL ?>/public/index.php?page=dashboard"
       class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.dashboard')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.dashboard')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/public/index.php?page=matches"
       class="nav-item <?= ($activePage ?? '') === 'matches' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.matches')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            <line x1="2" y1="12" x2="22" y2="12"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.matches')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/public/index.php?page=training"
       class="nav-item <?= ($activePage ?? '') === 'training' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.training')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.training')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/public/index.php?page=squad"
       class="nav-item <?= ($activePage ?? '') === 'squad' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.squad')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.squad')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/public/index.php?page=settings"
       class="nav-item <?= ($activePage ?? '') === 'settings' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.settings')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.settings')) ?></span>
    </a>
</nav>
<?php if (Auth::isLoggedIn()): ?>
<a href="<?= e(APP_URL) ?>/public/index.php?page=auth&action=logout"
   class="logout-link"
   aria-label="<?= e(t('auth.logout')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
</a>
<?php endif; ?>
