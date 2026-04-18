<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/player/PlayerRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';

$activeSeason = getActiveSeason();
$seasonRepo   = new SeasonRepository();
$playerRepo   = new PlayerRepository();

$team    = $activeSeason ? $seasonRepo->getTeamBySeason((int) $activeSeason['id']) : null;
$players = $team ? $playerRepo->getPlayersByTeam((int) $team['id']) : [];

$sort = preg_replace('/[^a-z]/', '', $_GET['sort'] ?? 'number');
if ($sort === 'name') {
    usort($players, fn($a, $b) => strcmp($a['first_name'], $b['first_name']));
} else {
    // Default: squad number (nulls last), then name
    usort($players, function ($a, $b) {
        if ($a['squad_number'] === null && $b['squad_number'] === null) {
            return strcmp($a['first_name'], $b['first_name']);
        }
        if ($a['squad_number'] === null) return 1;
        if ($b['squad_number'] === null) return -1;
        return $a['squad_number'] <=> $b['squad_number'];
    });
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <h1 class="page-title"><?= e(t('player.title')) ?></h1>
    <div class="flex gap-1">
        <a href="?page=squad&sort=number"
           class="btn btn--sm <?= $sort !== 'name' ? 'btn--primary' : 'btn--secondary' ?>">
            #
        </a>
        <a href="?page=squad&sort=name"
           class="btn btn--sm <?= $sort === 'name' ? 'btn--primary' : 'btn--secondary' ?>">
            A–Z
        </a>
    </div>
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
        <a href="<?= e(APP_URL) ?>/public/index.php?page=squad&action=profile&id=<?= (int) $player['id'] ?>"
           class="card card--link">
            <div class="flex" style="align-items:center; gap:0.75rem;">
                <?php if (!empty($player['photo_path'])): ?>
                    <img src="<?= e(APP_URL . '/public/' . $player['photo_path']) ?>"
                         alt="<?= e($player['first_name']) ?>"
                         style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <?php else: ?>
                    <div class="player-circle player-circle--md"
                         style="background:var(--color-primary);flex-shrink:0;">
                        <?= e(mb_strtoupper(mb_substr($player['first_name'], 0, 1))) ?>
                    </div>
                <?php endif; ?>
                <div style="flex:1;min-width:0;">
                    <div class="flex-between">
                        <strong><?= e($player['first_name']) ?></strong>
                        <?php if ($player['squad_number'] !== null): ?>
                            <span class="badge badge--neutral">#<?= (int) $player['squad_number'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($player['preferred_line'])): ?>
                        <div class="text-sm text-muted">
                            <?= e(t('player.line.' . $player['preferred_line'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
<?php

$content = ob_get_clean();
$title   = t('player.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
