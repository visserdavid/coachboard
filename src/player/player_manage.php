<?php
declare(strict_types=1);

$activePage = 'settings';

Auth::requireRole('is_administrator');

$activeSeason = getActiveSeason();
$seasonRepo   = new SeasonRepository();
$playerRepo   = new PlayerRepository();

$team    = $activeSeason ? $seasonRepo->getTeamBySeason((int) $activeSeason['id']) : null;
$players = $team ? $playerRepo->getPlayersByTeam((int) $team['id'], true) : [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=settings"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('settings.squad')) ?></h1>
    <?php if ($team !== null): ?>
        <a href="<?= e(APP_URL) ?>/public/index.php?page=squad&action=create"
           class="btn btn--primary btn--sm"><?= e(t('player.new')) ?></a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($activeSeason === null): ?>
    <div class="card">
        <p class="text-muted"><?= e(t('dashboard.no_season')) ?></p>
    </div>
<?php elseif (empty($players)): ?>
    <p class="text-muted"><?= e(t('player.no_players')) ?></p>
<?php else: ?>
    <?php foreach ($players as $player): ?>
        <?php $isDeleted = $player['deleted_at'] !== null; ?>
        <div class="card <?= $isDeleted ? 'card--deleted' : '' ?>"
             style="<?= $isDeleted ? 'opacity:0.5;' : '' ?>">
            <div class="flex-between">
                <div class="flex" style="align-items:center;gap:0.75rem;">
                    <div class="player-circle player-circle--sm"
                         style="background:<?= $isDeleted ? 'var(--color-neutral)' : 'var(--color-primary)' ?>;">
                        <?= e(mb_strtoupper(mb_substr($player['first_name'], 0, 1))) ?>
                    </div>
                    <div>
                        <strong><?= e($player['first_name']) ?></strong>
                        <?php if ($player['squad_number'] !== null): ?>
                            <span class="text-sm text-muted"> #<?= (int) $player['squad_number'] ?></span>
                        <?php endif; ?>
                        <?php if ($isDeleted): ?>
                            <span class="badge badge--neutral" style="margin-left:0.25rem;"><?= e(t('player.deleted')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex gap-1">
                    <?php if (!$isDeleted): ?>
                        <a href="<?= e(APP_URL) ?>/public/index.php?page=squad&action=edit&id=<?= (int) $player['id'] ?>"
                           class="btn btn--secondary btn--sm"><?= e(t('action.edit')) ?></a>
                        <form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=squad&action=delete"
                              onsubmit="return confirm(<?= e(json_encode(t('player.delete_confirm'))) ?>)">
                            <input type="hidden" name="id" value="<?= (int) $player['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--sm">
                                <?= e(t('player.delete')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=squad&action=restore">
                            <input type="hidden" name="id" value="<?= (int) $player['id'] ?>">
                            <button type="submit" class="btn btn--secondary btn--sm">
                                <?= e(t('player.restore')) ?>
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
$title   = t('settings.squad');
require dirname(__DIR__, 2) . '/templates/layout.php';
