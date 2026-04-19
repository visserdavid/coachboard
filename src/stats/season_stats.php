<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/stats/StatsRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';

$activeSeason = getActiveSeason();
if ($activeSeason === null) {
    ob_start();
    ?>
    <div class="page-header">
        <h1 class="page-title"><?= e(t('stats.title')) ?></h1>
    </div>
    <div class="card">
        <p class="text-muted"><?= e(t('dashboard.no_season')) ?></p>
    </div>
    <?php
    $content = ob_get_clean();
    $title   = t('stats.title');
    require dirname(__DIR__, 2) . '/templates/layout.php';
    return;
}

$seasonId  = (int) $activeSeason['id'];
$statsRepo = new StatsRepository();
$seasonRepo = new SeasonRepository();

// Resolve team for the active season
$pdo  = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT * FROM team WHERE season_id = ? LIMIT 1');
$stmt->execute([$seasonId]);
$team = $stmt->fetch() ?: null;

if ($team === null) {
    ob_start();
    ?>
    <div class="page-header">
        <h1 class="page-title"><?= e(t('stats.title')) ?></h1>
    </div>
    <div class="card">
        <p class="text-muted"><?= e(t('stats.no_data')) ?></p>
    </div>
    <?php
    $content = ob_get_clean();
    $title   = t('stats.title');
    require dirname(__DIR__, 2) . '/templates/layout.php';
    return;
}

$teamId = (int) $team['id'];
$phases = $seasonRepo->getPhasesBySeason($seasonId);

// Phase filter
$activePhaseId = isset($_GET['phase_id']) ? (int) $_GET['phase_id'] : null;
$activePhase   = null;
if ($activePhaseId !== null) {
    foreach ($phases as $ph) {
        if ((int) $ph['id'] === $activePhaseId) {
            $activePhase = $ph;
            break;
        }
    }
    if ($activePhase === null) {
        $activePhaseId = null;
    }
}

// Summary
$summary = $activePhaseId !== null
    ? $statsRepo->getPhaseSummary($teamId, $activePhaseId)
    : $statsRepo->getSeasonSummary($teamId, $seasonId);

// Top performers
if ($activePhaseId !== null) {
    $topScorers  = $statsRepo->getTopScorersByPhase($teamId, $activePhaseId);
    $topAssists  = $statsRepo->getTopAssistsByPhase($teamId, $activePhaseId);
} else {
    $topScorers  = $statsRepo->getTopScorers($teamId, $seasonId);
    $topAssists  = $statsRepo->getTopAssists($teamId, $seasonId);
}

// Playing time balance and training attendance are always full-season
$playingTime = $statsRepo->getPlayingTimeBalance($teamId, $seasonId);
$attendance  = $statsRepo->getTrainingAttendanceRanking($teamId, $seasonId);

// Max minutes for the bar visual
$maxMinutes = !empty($playingTime) ? max(array_column($playingTime, 'playing_time_minutes')) : 0;

ob_start();
?>
<div class="page-header">
    <h1 class="page-title"><?= e(t('stats.title')) ?></h1>
</div>

<!-- Phase filter -->
<?php if (seasonHasPhases() && !empty($phases)): ?>
<div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem;">
    <a href="?page=stats"
       class="badge <?= $activePhaseId === null ? 'badge--primary' : 'badge--neutral' ?>"
       style="text-decoration:none;">
        <?= e(t('stats.filter.full_season')) ?>
    </a>
    <?php foreach ($phases as $ph): ?>
        <a href="?page=stats&phase_id=<?= (int) $ph['id'] ?>"
           class="badge <?= $activePhaseId === (int) $ph['id'] ? 'badge--primary' : 'badge--neutral' ?>"
           style="text-decoration:none;">
            <?= e($ph['label'] ?: t('stats.filter.phase', ['number' => $ph['number']])) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Section 1: Season summary -->
<div class="card">
    <h2 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
        <?= e(t('stats.season_summary')) ?>
    </h2>

    <?php if ($summary['matches_played'] === 0): ?>
        <p class="text-muted text-sm"><?= e(t('stats.no_data')) ?></p>
    <?php else: ?>
        <!-- Results row -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;text-align:center;margin-bottom:0.75rem;">
            <div>
                <div style="font-size:1.5rem;font-weight:800;color:var(--color-success);"><?= $summary['wins'] ?></div>
                <div class="text-sm text-muted"><?= e(t('stats.wins')) ?></div>
            </div>
            <div>
                <div style="font-size:1.5rem;font-weight:800;color:var(--color-neutral);"><?= $summary['draws'] ?></div>
                <div class="text-sm text-muted"><?= e(t('stats.draws')) ?></div>
            </div>
            <div>
                <div style="font-size:1.5rem;font-weight:800;color:var(--color-danger);"><?= $summary['losses'] ?></div>
                <div class="text-sm text-muted"><?= e(t('stats.losses')) ?></div>
            </div>
        </div>

        <!-- Goals -->
        <div style="padding-top:0.75rem;border-top:1px solid var(--color-border);">
            <div class="flex-between mb-1">
                <span class="text-sm text-muted"><?= e(t('stats.goals_scored')) ?></span>
                <strong><?= $summary['goals_scored'] ?></strong>
            </div>
            <div class="flex-between">
                <span class="text-sm text-muted"><?= e(t('stats.goals_conceded')) ?></span>
                <strong><?= $summary['goals_conceded'] ?></strong>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Section 2: Top performers -->
<div class="card">
    <h2 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
        <?= e(t('stats.top_scorers')) ?>
    </h2>
    <?php if (empty($topScorers)): ?>
        <p class="text-muted text-sm"><?= e(t('stats.no_data')) ?></p>
    <?php else: ?>
        <?php foreach ($topScorers as $i => $scorer): ?>
            <div class="flex-between" style="margin-bottom:0.4rem;">
                <span class="text-sm">
                    <span class="text-muted" style="min-width:1.2rem;display:inline-block;"><?= $i + 1 ?>.</span>
                    <?= e($scorer['name']) ?>
                </span>
                <strong><?= (int) $scorer['goals'] ?></strong>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
        <?= e(t('stats.top_assists')) ?>
    </h2>
    <?php if (empty($topAssists)): ?>
        <p class="text-muted text-sm"><?= e(t('stats.no_data')) ?></p>
    <?php else: ?>
        <?php foreach ($topAssists as $i => $row): ?>
            <div class="flex-between" style="margin-bottom:0.4rem;">
                <span class="text-sm">
                    <span class="text-muted" style="min-width:1.2rem;display:inline-block;"><?= $i + 1 ?>.</span>
                    <?= e($row['name']) ?>
                </span>
                <strong><?= (int) $row['assists'] ?></strong>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Section 3: Playing time balance -->
<div class="card">
    <h2 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
        <?= e(t('stats.playing_time')) ?>
    </h2>
    <?php if (empty($playingTime)): ?>
        <p class="text-muted text-sm"><?= e(t('stats.no_data')) ?></p>
    <?php else: ?>
        <?php foreach ($playingTime as $row): ?>
            <div style="margin-bottom:0.6rem;">
                <div class="flex-between" style="margin-bottom:0.2rem;">
                    <span class="text-sm"><?= e($row['name']) ?></span>
                    <span class="text-sm text-muted">
                        <?= $row['playing_time_minutes'] ?> <?= e(t('stats.minutes')) ?>
                    </span>
                </div>
                <?php if ($maxMinutes > 0): ?>
                    <div style="height:4px;background:var(--color-border);border-radius:2px;">
                        <div style="height:4px;background:var(--color-primary);border-radius:2px;width:<?= round($row['playing_time_minutes'] / $maxMinutes * 100) ?>%;"></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Section 4: Training attendance -->
<div class="card">
    <h2 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
        <?= e(t('stats.attendance')) ?>
    </h2>
    <?php if (empty($attendance)): ?>
        <p class="text-muted text-sm"><?= e(t('stats.no_data')) ?></p>
    <?php else: ?>
        <?php foreach ($attendance as $row): ?>
            <div class="flex-between" style="margin-bottom:0.5rem;">
                <span class="text-sm"><?= e($row['name']) ?></span>
                <span class="text-sm text-muted">
                    <?= $row['present'] ?>/<?= $row['total'] ?>
                    <strong style="color:var(--color-text);margin-left:0.25rem;"><?= $row['percentage'] ?>%</strong>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php

$content = ob_get_clean();
$title   = t('stats.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
