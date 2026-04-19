<?php
declare(strict_types=1);

$activePage   = 'match';
$activeSeason = getActiveSeason();
$seasonRepo   = new SeasonRepository();
$matchRepo    = new MatchRepository();

$team    = $activeSeason ? $seasonRepo->getTeamBySeason((int) $activeSeason['id']) : null;
$teamId  = $team ? (int) $team['id'] : null;
$matches = $teamId ? $matchRepo->getMatchesByTeam($teamId) : [];

$today = date('Y-m-d');

// Find anchor: next upcoming match or last match
$anchorId = null;
foreach ($matches as $m) {
    if ($m['date'] >= $today && in_array($m['status'], ['planned', 'prepared', 'active'], true)) {
        $anchorId = (int) $m['id'];
        break;
    }
}
if ($anchorId === null && !empty($matches)) {
    $anchorId = (int) end($matches)['id'];
}

// Group by phase
$groups = [];
if (seasonHasPhases()) {
    $currentPhaseId = -1;
    foreach ($matches as $m) {
        $phaseId = $m['phase_id'] !== null ? (int) $m['phase_id'] : null;
        if ($phaseId !== $currentPhaseId) {
            $groups[] = [
                'phase'   => $phaseId !== null ? [
                    'id'     => $phaseId,
                    'number' => $m['phase_number'],
                    'label'  => $m['phase_label'],
                ] : null,
                'matches' => [],
            ];
            $currentPhaseId = $phaseId;
        }
        $groups[count($groups) - 1]['matches'][] = $m;
    }
} else {
    $groups = [['phase' => null, 'matches' => $matches]];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <h1 class="page-title"><?= e(t('match.title')) ?></h1>
    <?php if ($teamId): ?>
        <a href="<?= e(APP_URL) ?>/index.php?page=match&action=create"
           class="btn btn--primary btn--sm"><?= e(t('match.new')) ?></a>
    <?php endif; ?>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($activeSeason === null): ?>
    <div class="card">
        <p class="text-muted"><?= e(t('dashboard.no_season')) ?></p>
    </div>
<?php elseif (empty($matches)): ?>
    <p class="text-muted"><?= e(t('match.no_matches')) ?></p>
<?php else: ?>
    <?php foreach ($groups as $group): ?>

        <?php if (seasonHasPhases() && $group['phase'] !== null): ?>
            <div class="section-divider">
                <?php
                $ph = $group['phase'];
                echo e($ph['label'] ?: t('phase.label', ['number' => $ph['number']]));
                ?>
            </div>
        <?php endif; ?>

        <?php foreach ($group['matches'] as $m): ?>
            <?php
            $mid        = (int) $m['id'];
            $isFinished = $m['status'] === 'finished';
            $isPrepared = $m['status'] === 'prepared';
            $isActive   = $m['status'] === 'active';
            $dateLabel  = date('j M', strtotime($m['date']));
            $isAnchor   = $mid === $anchorId;

            // Score colour for finished matches
            $scoreClass = '';
            if ($isFinished && $m['goals_scored'] !== null && $m['goals_conceded'] !== null) {
                $gs = (int) $m['goals_scored'];
                $gc = (int) $m['goals_conceded'];
                $scoreClass = $gs > $gc ? 'badge--success' : ($gs === $gc ? 'badge--neutral' : 'badge--danger');
            }

            // Previous result against same opponent (upcoming only)
            $prevResult = null;
            if (!$isFinished && $teamId !== null) {
                $prevResult = $matchRepo->getPreviousOpponentResult($teamId, $m['opponent']);
            }
            ?>

            <?php if ($isAnchor): ?>
                <div id="match-anchor"></div>
            <?php endif; ?>

            <a href="<?= e(APP_URL) ?>/index.php?page=match&action=<?= $isFinished ? 'review' : ($isActive ? 'live' : 'prepare') ?>&id=<?= $mid ?>"
               class="card card--link">
                <div class="flex-between">
                    <div>
                        <div class="text-sm text-muted"><?= e($dateLabel) ?><?php if ($m['kick_off_time']): ?> · <?= e(substr($m['kick_off_time'], 0, 5)) ?><?php endif; ?></div>
                        <div style="font-weight:600; margin-top:0.15rem;"><?= e($m['opponent']) ?></div>
                        <div style="display:flex; gap:0.4rem; margin-top:0.25rem; align-items:center; flex-wrap:wrap;">
                            <span class="badge badge--neutral" style="font-size:0.7rem;">
                                <?= e(t('match.' . $m['home_away'])) ?>
                            </span>
                            <?php if ($m['match_type'] !== 'league'): ?>
                                <span class="badge badge--primary" style="font-size:0.7rem;">
                                    <?= e(t('match.type.' . $m['match_type'])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($isPrepared): ?>
                                <span class="badge badge--success" style="font-size:0.7rem;">
                                    <?= e(t('match.status.prepared')) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($isActive): ?>
                                <span class="badge badge--accent" style="font-size:0.7rem;">
                                    <?= e(t('match.status.active')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($prevResult !== null): ?>
                            <?php
                            $pg = (int) $prevResult['goals_scored'];
                            $pc = (int) $prevResult['goals_conceded'];
                            $letter = $pg > $pc ? t('match.result.win') : ($pg === $pc ? t('match.result.draw') : t('match.result.loss'));
                            ?>
                            <div class="text-sm text-muted" style="margin-top:0.3rem;">
                                <?= e(t('match.previous_result')) ?>: <?= e($letter) ?> <?= $pg ?>–<?= $pc ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isFinished && $m['goals_scored'] !== null): ?>
                        <div class="badge <?= e($scoreClass) ?>" style="font-size:1rem; font-weight:800; min-width:52px; text-align:center;">
                            <?= (int) $m['goals_scored'] ?>–<?= (int) $m['goals_conceded'] ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:1.25rem;">›</span>
                    <?php endif; ?>
                </div>
            </a>

        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var anchor = document.getElementById('match-anchor');
    if (anchor) {
        anchor.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
});
</script>
<?php

$content = ob_get_clean();
$title   = t('match.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
