<?php
declare(strict_types=1);

// Never display the raw token — only read it from the query string
$token = $_GET['token'] ?? '';

if ($token === '') {
    redirect(APP_URL . '/index.php?page=auth&action=login');
}

$authService = new AuthService();
$user        = $authService->verifyToken($token);

if ($user !== null) {
    redirect(APP_URL . '/index.php?page=dashboard');
}

// Token invalid or expired — show error, never reveal the token value
$title   = t('auth.login');
$content = '';
ob_start();
?>
<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:80vh; padding:2rem 1rem;">
    <h1 style="font-size:2rem; font-weight:800; color:var(--color-primary); margin-bottom:0.25rem; text-align:center;">
        <?= e(t('app.name')) ?>
    </h1>
    <div class="card" style="max-width:360px; width:100%; margin-top:1.5rem; text-align:center;">
        <p class="text-danger mb-2"><?= e(t('auth.invalid_token')) ?></p>
        <a href="<?= e(APP_URL) ?>/index.php?page=auth&action=login" class="btn btn--primary btn--full">
            <?= e(t('auth.login')) ?>
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/templates/layout.php';
