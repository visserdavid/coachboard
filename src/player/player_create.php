<?php
declare(strict_types=1);

$activePage = 'settings';

Auth::requireRole('is_administrator');

$activeSeason = getActiveSeason();
if ($activeSeason === null) {
    $_SESSION['flash'] = t('dashboard.no_season');
    redirect(APP_URL . '/index.php?page=settings');
}

$seasonRepo = new SeasonRepository();
$team       = $seasonRepo->getTeamBySeason((int) $activeSeason['id']);

if ($team === null) {
    $_SESSION['flash'] = t('error.general');
    redirect(APP_URL . '/index.php?page=settings');
}

$playerSvc = new PlayerService();
$errors    = [];
$values    = ['first_name' => '', 'squad_number' => '', 'photo_consent' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['first_name']    = trim($_POST['first_name'] ?? '');
    $values['squad_number']  = trim($_POST['squad_number'] ?? '');
    $values['photo_consent'] = !empty($_POST['photo_consent']) ? '1' : '';

    try {
        $data = [
            'team_id'       => (int) $team['id'],
            'first_name'    => $values['first_name'],
            'squad_number'  => $values['squad_number'] !== '' ? (int) $values['squad_number'] : null,
            'photo_consent' => !empty($values['photo_consent']) ? 1 : 0,
        ];

        $playerSvc->createPlayer($data);
        $_SESSION['flash'] = t('player.saved');
        redirect(APP_URL . '/index.php?page=squad&action=manage');

    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (Exception $e) {
        $errors[] = t('error.general');
    }
}

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=squad&action=manage"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('player.new')) ?></h1>
    <span></span>
</div>

<?php if (!empty($errors)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);">
        <?php foreach ($errors as $err): ?>
            <p class="text-danger text-sm"><?= e($err) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="card">
        <div class="form-group">
            <label class="form-label"><?= e(t('player.name')) ?></label>
            <input type="text" name="first_name" class="form-input"
                   value="<?= e($values['first_name']) ?>" required maxlength="100" autofocus>
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('player.squad_number')) ?></label>
            <input type="number" name="squad_number" class="form-input"
                   value="<?= e($values['squad_number']) ?>"
                   min="1" max="99" placeholder="—">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" name="photo_consent" value="1"
                       <?= $values['photo_consent'] ? 'checked' : '' ?>>
                <span class="text-sm"><?= e(t('player.photo_consent')) ?></span>
            </label>
        </div>

        <button type="submit" class="btn btn--primary btn--full">
            <?= e(t('action.save')) ?>
        </button>
    </div>
</form>
<?php

$content = ob_get_clean();
$title   = t('player.new');
require dirname(__DIR__, 2) . '/templates/layout.php';
