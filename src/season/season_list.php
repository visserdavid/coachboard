<?php
declare(strict_types=1);

$activePage = 'settings';

require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';

$repo    = new SeasonRepository();
$seasons = $repo->getAllSeasons();

foreach ($seasons as &$season) {
    $range = $repo->getSeasonDateRange((int) $season['id']);
    $season['season_start'] = $range['season_start'];
    $season['season_end']   = $range['season_end'];
}
unset($season);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=settings"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('season.title')) ?></h1>
    <a href="<?= e(APP_URL) ?>/index.php?page=season&action=new" class="btn btn--primary btn--sm">
        <?= e(t('season.new')) ?>
    </a>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (empty($seasons)): ?>
    <p class="text-muted"><?= e(t('season.no_seasons')) ?></p>
<?php else: ?>
    <?php foreach ($seasons as $s): ?>
        <div class="card">
            <div class="flex-between">
                <div>
                    <div class="flex gap-1" style="align-items:center; margin-bottom:0.25rem;">
                        <strong><?= e($s['name']) ?></strong>
                        <?php if ($s['active']): ?>
                            <span class="badge badge--success"><?= e(t('season.active')) ?></span>
                        <?php endif; ?>
                        <?php if ($s['has_phases']): ?>
                            <span class="badge badge--primary"><?= e(t('season.has_phases')) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($s['season_start'] && $s['season_end']): ?>
                        <span class="text-sm text-muted">
                            <?= e(date('d M Y', strtotime($s['season_start']))) ?>
                            – <?= e(date('d M Y', strtotime($s['season_end']))) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex gap-1">
                    <a href="<?= e(APP_URL) ?>/index.php?page=season&action=detail&id=<?= (int) $s['id'] ?>"
                       class="btn btn--secondary btn--sm"><?= e(t('action.edit')) ?></a>
                    <?php if (!$s['active']): ?>
                        <form method="POST" action="<?= e(APP_URL) ?>/index.php?page=season&action=set_active"
                              onsubmit="return confirm(<?= e(json_encode(t('season.set_active_confirm'))) ?>)">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="btn btn--accent btn--sm">
                                <?= e(t('season.set_active')) ?>
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
$title   = t('season.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
