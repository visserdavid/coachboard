<?php
declare(strict_types=1);

$activePage = 'settings';

$formationRepo = new FormationRepository();
$formations    = $formationRepo->getAllFormations();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Count positions per formation
$pdo = Database::getInstance()->getConnection();
$positionCounts = [];
foreach ($formations as $f) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM formation_position WHERE formation_id = ?');
    $stmt->execute([(int) $f['id']]);
    $positionCounts[(int) $f['id']] = (int) $stmt->fetchColumn();
}

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=settings"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('formation.title')) ?></h1>
    <a href="<?= e(APP_URL) ?>/index.php?page=formation&action=create"
       class="btn btn--primary btn--sm"><?= e(t('formation.add')) ?></a>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (empty($formations)): ?>
    <div class="card">
        <p class="text-muted"><?= e(t('formation.no_formations')) ?></p>
    </div>
<?php else: ?>
    <?php foreach ($formations as $f): ?>
        <div class="card">
            <div class="flex-between" style="align-items:flex-start;">
                <div>
                    <div class="flex gap-1" style="align-items:center;margin-bottom:0.25rem;">
                        <strong><?= e($f['name']) ?></strong>
                        <?php if ($f['is_default']): ?>
                            <span class="badge badge--success"><?= e(t('formation.default')) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-sm text-muted">
                        <?= (int) $f['outfield_players'] + 1 ?> <?= e(t('formation.players_label')) ?>
                        · <?= $positionCounts[(int) $f['id']] ?> <?= e(t('formation.positions')) ?>
                    </span>
                </div>
                <div class="flex gap-1" style="flex-shrink:0;margin-left:0.5rem;">
                    <?php if (!$f['is_default']): ?>
                        <form method="POST"
                              action="<?= e(APP_URL) ?>/index.php?page=formation&action=set_default">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <button type="submit" class="btn btn--secondary btn--sm">
                                <?= e(t('formation.set_default')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= e(APP_URL) ?>/index.php?page=formation&action=edit&id=<?= (int) $f['id'] ?>"
                       class="btn btn--secondary btn--sm"><?= e(t('action.edit')) ?></a>
                    <?php if (!$f['is_default']): ?>
                        <form method="POST"
                              action="<?= e(APP_URL) ?>/index.php?page=formation&action=delete"
                              onsubmit="return confirm(<?= e(json_encode(t('formation.delete_confirm'))) ?>)">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--sm">
                                <?= e(t('action.delete')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php

$content = ob_get_clean();
$title   = t('formation.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
