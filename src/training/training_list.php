<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/training/TrainingRepository.php';
require_once dirname(__DIR__, 2) . '/src/training/TrainingService.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';

$activeSeason = getActiveSeason();
$seasonRepo   = new SeasonRepository();
$trainingRepo = new TrainingRepository();

$team     = $activeSeason ? $seasonRepo->getTeamBySeason((int) $activeSeason['id']) : null;
$teamId   = $team ? (int) $team['id'] : null;
$sessions = $teamId ? $trainingRepo->getSessionsByTeam($teamId) : [];

$today = date('Y-m-d');

$sessionIds = array_map('intval', array_column($sessions, 'id'));
$focusMap   = $trainingRepo->getFocusForSessions($sessionIds);
$summaryMap = $trainingRepo->getAttendanceSummariesBySessions($sessionIds);

// Find anchor: first session on or after today; fallback to last session
$anchorId = null;
foreach ($sessions as $session) {
    if ($session['date'] >= $today) {
        $anchorId = (int) $session['id'];
        break;
    }
}
if ($anchorId === null && !empty($sessions)) {
    $anchorId = (int) end($sessions)['id'];
}

// Group sessions by phase (or single group when no phases)
$groups = [];
if (seasonHasPhases()) {
    $currentPhaseId  = -1;
    foreach ($sessions as $session) {
        $phaseId = $session['phase_id'] !== null ? (int) $session['phase_id'] : null;
        if ($phaseId !== $currentPhaseId) {
            $groups[] = [
                'phase'    => $phaseId !== null ? [
                    'id'     => $phaseId,
                    'number' => $session['phase_number'],
                    'label'  => $session['phase_label'],
                ] : null,
                'sessions' => [],
            ];
            $currentPhaseId = $phaseId;
        }
        $groups[count($groups) - 1]['sessions'][] = $session;
    }
} else {
    $groups = [['phase' => null, 'sessions' => $sessions]];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <h1 class="page-title"><?= e(t('training.title')) ?></h1>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($activeSeason === null): ?>
    <div class="card">
        <p class="text-muted"><?= e(t('dashboard.no_season')) ?></p>
    </div>
<?php elseif (empty($sessions)): ?>
    <p class="text-muted"><?= e(t('training.no_sessions')) ?></p>
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

        <?php foreach ($group['sessions'] as $session): ?>
            <?php
            $sid         = (int) $session['id'];
            $isCancelled = (bool) $session['cancelled'];
            $isPast      = $session['date'] < $today;
            $isAnchor    = $sid === $anchorId;
            $focus       = $focusMap[$sid] ?? [];
            $summary     = $summaryMap[$sid] ?? ['present' => 0, 'absent' => 0, 'injured' => 0];
            $dayOfWeek   = t('week.' . date('N', strtotime($session['date'])));
            $dateLabel   = $dayOfWeek . ' ' . date('j M', strtotime($session['date']));
            ?>

            <?php if ($isAnchor): ?>
                <div id="session-anchor"></div>
            <?php endif; ?>

            <?php if ($isCancelled): ?>
                <div class="card" style="opacity:0.5;">
                    <div class="flex-between">
                        <span class="text-sm"><?= e($dateLabel) ?></span>
                        <span class="badge badge--neutral"><?= e(t('training.cancelled')) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= e(APP_URL) ?>/public/index.php?page=training&action=detail&id=<?= $sid ?>"
                   class="card card--link">
                    <div class="flex-between">
                        <div>
                            <div><?= e($dateLabel) ?></div>
                            <?php if (!empty($focus)): ?>
                                <div class="flex gap-1" style="margin-top:0.3rem; align-items:center;">
                                    <?php foreach ($focus as $f): ?>
                                        <span class="training-focus-icon training-focus-icon--<?= e($f) ?>"
                                              title="<?= e(t('training.focus.' . $f)) ?>">
                                            <?php if ($f === 'attacking'): ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                                    <path d="M8 13V3M8 3L4 7M8 3L12 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            <?php elseif ($f === 'defending'): ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                                    <path d="M8 2L3 4.5V8.5C3 11 5 13.5 8 14.5C11 13.5 13 11 13 8.5V4.5L8 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                </svg>
                                            <?php elseif ($f === 'transitioning'): ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                                    <path d="M3 5L1.5 7L3 9M1.5 7H9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M13 7L14.5 9L13 11M14.5 9H6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-muted" style="text-align:right; flex-shrink:0; margin-left:0.5rem;">
                            <?php if ($isPast): ?>
                                <?php $total = $summary['present'] + $summary['absent'] + $summary['injured']; ?>
                                <?php if ($total > 0): ?>
                                    <?= $summary['present'] ?>/<?= $total ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php $absentCount = $summary['absent'] + $summary['injured']; ?>
                                <?php if ($absentCount > 0): ?>
                                    <span style="color:var(--color-danger);">
                                        <?= $absentCount ?> <?= e(t('attendance.absent')) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var anchor = document.getElementById('session-anchor');
    if (anchor) {
        anchor.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
});
</script>
<?php

$content = ob_get_clean();
$title   = t('training.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
