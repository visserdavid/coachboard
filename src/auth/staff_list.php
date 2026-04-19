<?php
declare(strict_types=1);

$staffRepo = new StaffRepository();
$staff     = $staffRepo->getAllStaff();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=settings"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('staff.title')) ?></h1>
    <a href="<?= e(APP_URL) ?>/public/index.php?page=staff&action=create"
       class="btn btn--primary btn--sm"><?= e(t('staff.add')) ?></a>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (empty($staff)): ?>
    <div class="card">
        <p class="text-muted"><?= e(t('staff.no_staff')) ?></p>
    </div>
<?php else: ?>
    <?php foreach ($staff as $member): ?>
        <div class="card <?= $member['active'] ? '' : 'card--muted' ?>"
             style="<?= $member['active'] ? '' : 'opacity:0.55;' ?>">
            <div class="flex-between" style="align-items:flex-start;">
                <div>
                    <strong><?= e($member['first_name']) ?></strong>
                    <div class="text-sm text-muted"><?= e($member['email']) ?></div>
                    <div class="flex gap-1" style="flex-wrap:wrap;margin-top:0.35rem;">
                        <?php if ($member['is_administrator']): ?>
                            <span class="badge badge--primary"><?= e(t('staff.role.administrator')) ?></span>
                        <?php endif; ?>
                        <?php if ($member['is_trainer']): ?>
                            <span class="badge badge--neutral"><?= e(t('staff.role.trainer')) ?></span>
                        <?php endif; ?>
                        <?php if ($member['is_coach']): ?>
                            <span class="badge badge--neutral"><?= e(t('staff.role.coach')) ?></span>
                        <?php endif; ?>
                        <?php if ($member['is_assistant']): ?>
                            <span class="badge badge--neutral"><?= e(t('staff.role.assistant')) ?></span>
                        <?php endif; ?>
                        <?php if (!$member['active']): ?>
                            <span class="badge badge--danger"><?= e(t('staff.inactive')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex gap-1" style="flex-shrink:0;margin-left:0.5rem;">
                    <a href="<?= e(APP_URL) ?>/public/index.php?page=staff&action=edit&id=<?= (int) $member['id'] ?>"
                       class="btn btn--secondary btn--sm"><?= e(t('action.edit')) ?></a>
                    <?php if ($member['active']): ?>
                        <form method="POST"
                              action="<?= e(APP_URL) ?>/public/index.php?page=staff&action=deactivate"
                              onsubmit="return confirm(<?= e(json_encode(t('staff.deactivate_confirm'))) ?>)">
                            <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--sm">
                                <?= e(t('staff.deactivate')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST"
                              action="<?= e(APP_URL) ?>/public/index.php?page=staff&action=reactivate">
                            <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                            <button type="submit" class="btn btn--secondary btn--sm">
                                <?= e(t('staff.reactivate')) ?>
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
$title   = t('staff.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
