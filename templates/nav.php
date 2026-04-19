<?php declare(strict_types=1); ?>
<?php if (!empty($_SESSION['flash'])): ?>
<div class="flash-message" role="alert"><?= e($_SESSION['flash']) ?></div>
<?php unset($_SESSION['flash']); endif; ?>
<nav class="bottom-nav" role="navigation" aria-label="Main navigation">
    <a href="<?= APP_URL ?>/index.php?page=dashboard"
       class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.dashboard')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.dashboard')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/index.php?page=match"
       class="nav-item <?= ($activePage ?? '') === 'match' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.matches')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <polygon points="12,8 15.8,10.8 14.4,15.2 9.6,15.2 8.2,10.8"/>
            <line x1="12" y1="8" x2="12" y2="2"/>
            <line x1="15.8" y1="10.8" x2="21.5" y2="8.9"/>
            <line x1="14.4" y1="15.2" x2="17.9" y2="20.1"/>
            <line x1="9.6" y1="15.2" x2="6.1" y2="20.1"/>
            <line x1="8.2" y1="10.8" x2="2.5" y2="8.9"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.matches')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/index.php?page=training"
       class="nav-item <?= ($activePage ?? '') === 'training' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.training')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="12,3 20,20 4,20"/>
            <line x1="7.5" y1="13" x2="16.5" y2="13"/>
            <rect x="3" y="20" width="18" height="2" rx="1"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.training')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/index.php?page=squad"
       class="nav-item <?= ($activePage ?? '') === 'squad' ? 'nav-item--active' : '' ?>"
       aria-label="<?= e(t('nav.squad')) ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        <span class="nav-label"><?= e(t('nav.squad')) ?></span>
    </a>
    <a href="<?= APP_URL ?>/index.php?page=settings"
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
<a href="<?= e(APP_URL) ?>/index.php?page=auth&action=logout"
   class="logout-link"
   aria-label="<?= e(t('auth.logout')) ?>">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
</a>
<?php endif; ?>
