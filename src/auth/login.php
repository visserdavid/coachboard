<?php
declare(strict_types=1);

// Already logged in — go straight to dashboard
if (Auth::isLoggedIn()) {
    redirect(APP_URL . '/public/index.php?page=dashboard');
}

$linkSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email !== '') {
        $authService = new AuthService();
        $authService->requestLink($email);
    }
    // Always show the neutral confirmation — never reveal whether address is known
    $linkSent = true;
}

$title   = t('auth.login');
$content = '';
ob_start();
?>
<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:80vh; padding:2rem 1rem;">
    <h1 style="font-size:2rem; font-weight:800; color:var(--color-primary); margin-bottom:0.25rem; text-align:center;">
        <?= e(t('app.name')) ?>
    </h1>
    <p class="text-muted mb-2" style="text-align:center;"><?= e(t('app.tagline')) ?></p>

    <?php if ($linkSent): ?>
        <div class="card" style="max-width:360px; width:100%; text-align:center; margin-top:1.5rem;">
            <p><?= e(t('auth.link_sent')) ?></p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(APP_URL) ?>/public/index.php?page=auth&action=login"
              style="width:100%; max-width:360px; margin-top:1.5rem;">
            <div class="form-group">
                <label class="form-label" for="email"><?= e(t('auth.email_placeholder')) ?></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="<?= e(t('auth.email_placeholder')) ?>"
                    required
                    autocomplete="email"
                    autofocus
                >
            </div>
            <button type="submit" class="btn btn--primary btn--full">
                <?= e(t('auth.send_link')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/templates/layout.php';
