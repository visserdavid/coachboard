<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/training/TrainingRepository.php';
require_once dirname(__DIR__, 2) . '/src/training/TrainingService.php';
require_once dirname(__DIR__, 2) . '/src/player/PlayerRepository.php';

$trainingRepo = new TrainingRepository();
$trainingService = new TrainingService();
$playerRepo   = new PlayerRepository();

$id      = (int) ($_GET['id'] ?? 0);
$session = $trainingRepo->getSessionById($id);

if ($session === null) {
    $_SESSION['flash'] = t('error.not_found');
    redirect(APP_URL . '/public/index.php?page=training');
}

$teamId      = (int) $session['team_id'];
$isCancelled = (bool) $session['cancelled'];
$today       = date('Y-m-d');
$isPast      = $session['date'] < $today;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'save_content' && !$isCancelled) {
        $focus = array_filter((array) ($_POST['focus'] ?? []), fn($v) => in_array($v, ['attacking', 'defending', 'transitioning'], true));
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $trainingService->saveSessionContent($id, array_values($focus), $notes);
        $_SESSION['flash'] = t('training.content_saved');
        redirect(APP_URL . '/public/index.php?page=training&action=detail&id=' . $id);
    }

    if ($postAction === 'save_attendance' && !$isCancelled) {
        $rawAttendance = (array) ($_POST['att'] ?? []);
        $attendance    = [];
        foreach ($rawAttendance as $playerId => $data) {
            $status = $data['status'] ?? 'present';
            if (!in_array($status, ['present', 'absent', 'injured'], true)) {
                $status = 'present';
            }
            $reason = null;
            $note   = null;
            if ($status === 'absent') {
                $reason = $data['absence_reason'] ?? null;
                if (!in_array($reason, ['sick', 'holiday', 'school', 'other'], true)) {
                    $reason = null;
                }
            }
            if ($status === 'injured') {
                $note = trim($data['injury_note'] ?? '') ?: null;
            }
            $attendance[(int) $playerId] = [
                'status'         => $status,
                'absence_reason' => $reason,
                'injury_note'    => $note,
            ];
        }
        $trainingService->saveAttendance($id, $attendance);
        $_SESSION['flash'] = t('training.attendance_saved');
        redirect(APP_URL . '/public/index.php?page=training&action=detail&id=' . $id);
    }
}

// Reload session in case notes changed (POST redirect already handled above)
$session = $trainingRepo->getSessionById($id);

$currentFocus   = $trainingRepo->getFocusBySession($id);
$players        = $playerRepo->getPlayersByTeam($teamId);
$attRecords     = $trainingRepo->getAttendanceBySession($id);
$attendanceMap  = [];
foreach ($attRecords as $att) {
    $attendanceMap[(int) $att['player_id']] = $att;
}

$dayOfWeek  = t('week.' . date('N', strtotime($session['date'])));
$dateLabel  = $dayOfWeek . ' ' . date('j M Y', strtotime($session['date']));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=training"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;"><?= e($dateLabel) ?></h1>
    <span></span>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($isCancelled): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);">
        <strong style="color:var(--color-danger);"><?= e(t('training.cancelled')) ?></strong>
    </div>
<?php endif; ?>

<?php
if (seasonHasPhases() && $session['phase_id'] !== null): ?>
    <div class="text-sm text-muted mb-1" style="padding: 0 0.25rem;">
        <?= e($session['phase_label'] ?: t('phase.label', ['number' => $session['phase_number']])) ?>
    </div>
<?php endif; ?>

<!-- Cancel / Reinstate -->
<?php if (!$isCancelled): ?>
    <form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=training&action=cancel"
          onsubmit="return confirm(<?= e(json_encode(t('training.cancel_confirm'))) ?>)">
        <input type="hidden" name="session_id" value="<?= $id ?>">
        <div style="margin-bottom:0.75rem; text-align:right;">
            <button type="submit" class="btn btn--danger btn--sm">
                <?= e(t('training.cancel')) ?>
            </button>
        </div>
    </form>
<?php else: ?>
    <form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=training&action=reinstate">
        <input type="hidden" name="session_id" value="<?= $id ?>">
        <div style="margin-bottom:0.75rem; text-align:right;">
            <button type="submit" class="btn btn--secondary btn--sm">
                <?= e(t('training.reinstate')) ?>
            </button>
        </div>
    </form>
<?php endif; ?>

<!-- Section 1: Session content -->
<div class="card">
    <div class="form-label" style="margin-bottom:0.5rem;"><?= e(t('training.focus')) ?></div>
    <?php if ($isCancelled): ?>
        <div class="flex gap-1" style="margin-bottom:0.75rem; flex-wrap:wrap;">
            <?php if (empty($currentFocus)): ?>
                <span class="text-muted text-sm">—</span>
            <?php endif; ?>
            <?php foreach ($currentFocus as $f): ?>
                <span class="training-focus-icon training-focus-icon--<?= e($f) ?>"
                      style="padding:0.2rem 0.6rem; border-radius:var(--radius-full); width:auto;">
                    <?= e(t('training.focus.' . $f)) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if ($session['notes']): ?>
            <div class="form-label"><?= e(t('training.notes')) ?></div>
            <p class="text-sm"><?= e($session['notes']) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <form method="POST"
              action="<?= e(APP_URL) ?>/public/index.php?page=training&action=detail&id=<?= $id ?>">
            <input type="hidden" name="_action" value="save_content">
            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:0.75rem;">
                <?php foreach (['attacking', 'defending', 'transitioning'] as $f): ?>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="focus[]" value="<?= e($f) ?>"
                               <?= in_array($f, $currentFocus, true) ? 'checked' : '' ?>>
                        <span class="training-focus-icon training-focus-icon--<?= e($f) ?>">
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
                        <?= e(t('training.focus.' . $f)) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(t('training.notes')) ?></label>
                <textarea name="notes" class="form-textarea"><?= e($session['notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn--secondary btn--sm">
                <?= e(t('action.save')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Section 2: Attendance -->
<div class="card">
    <div class="flex-between mb-1">
        <strong><?= e(t('training.attendance')) ?></strong>
    </div>

    <?php if (empty($players)): ?>
        <p class="text-muted text-sm"><?= e(t('player.no_players')) ?></p>
    <?php elseif ($isCancelled): ?>
        <?php foreach ($players as $player): ?>
            <?php $att = $attendanceMap[(int) $player['id']] ?? null; ?>
            <div class="attendance-row">
                <div class="flex-between">
                    <span>
                        <?= e($player['first_name']) ?>
                        <?php if ($player['squad_number'] !== null): ?>
                            <span class="text-muted text-sm">#<?= (int) $player['squad_number'] ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($att): ?>
                        <span class="badge <?php
                            echo $att['status'] === 'present' ? 'badge--success' :
                                ($att['status'] === 'injured' ? 'badge--accent' : 'badge--danger');
                        ?>"><?= e(t('attendance.' . $att['status'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($att && $att['status'] === 'absent' && $att['absence_reason']): ?>
                    <div class="text-sm text-muted"><?= e(t('attendance.reason.' . $att['absence_reason'])) ?></div>
                <?php endif; ?>
                <?php if ($att && $att['status'] === 'injured' && $att['injury_note']): ?>
                    <div class="text-sm text-muted"><?= e($att['injury_note']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <form method="POST"
              action="<?= e(APP_URL) ?>/public/index.php?page=training&action=detail&id=<?= $id ?>">
            <input type="hidden" name="_action" value="save_attendance">
            <?php foreach ($players as $player): ?>
                <?php
                $pid    = (int) $player['id'];
                $att    = $attendanceMap[$pid] ?? null;
                $status = $att['status'] ?? 'present';
                ?>
                <div class="attendance-row" id="att-row-<?= $pid ?>">
                    <div class="flex-between">
                        <span>
                            <?= e($player['first_name']) ?>
                            <?php if ($player['squad_number'] !== null): ?>
                                <span class="text-muted text-sm">#<?= (int) $player['squad_number'] ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <input type="hidden" name="att[<?= $pid ?>][status]" id="status-<?= $pid ?>"
                           value="<?= e($status) ?>">
                    <div class="attendance-buttons">
                        <?php foreach (['present', 'absent', 'injured'] as $s): ?>
                            <button type="button"
                                    class="att-btn att-btn--<?= $s ?><?= $status === $s ? ' att-btn--active' : '' ?>"
                                    data-player="<?= $pid ?>"
                                    data-status="<?= $s ?>"
                                    onclick="setAttStatus(<?= $pid ?>, '<?= $s ?>')">
                                <?= e(t('attendance.' . $s)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <!-- Absence reason -->
                    <div class="att-sub" id="att-reason-<?= $pid ?>"
                         style="display:<?= $status === 'absent' ? 'block' : 'none' ?>;">
                        <select name="att[<?= $pid ?>][absence_reason]" class="form-select"
                                style="margin-top:0.25rem;">
                            <?php foreach (['sick', 'holiday', 'school', 'other'] as $r): ?>
                                <option value="<?= $r ?>"
                                    <?= ($att['absence_reason'] ?? '') === $r ? 'selected' : '' ?>>
                                    <?= e(t('attendance.reason.' . $r)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Injury note -->
                    <div class="att-sub" id="att-injury-<?= $pid ?>"
                         style="display:<?= $status === 'injured' ? 'block' : 'none' ?>;">
                        <input type="text"
                               name="att[<?= $pid ?>][injury_note]"
                               class="form-input"
                               style="margin-top:0.25rem;"
                               placeholder="<?= e(t('attendance.injury_note')) ?>"
                               value="<?= e($att['injury_note'] ?? '') ?>">
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn--primary btn--full mt-2">
                <?= e(t('training.attendance_save')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Section 3: Summary (past sessions only) -->
<?php if ($isPast && !$isCancelled): ?>
    <?php
    $summary = $trainingService->getAttendanceSummary($id);
    ?>
    <div class="card">
        <strong><?= e(t('training.summary')) ?></strong>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; text-align:center; margin-top:0.75rem; margin-bottom:0.75rem;">
            <div>
                <div style="font-size:1.4rem; font-weight:800; color:var(--color-success);"><?= $summary['present'] ?></div>
                <div class="text-sm text-muted"><?= e(t('attendance.present')) ?></div>
            </div>
            <div>
                <div style="font-size:1.4rem; font-weight:800; color:var(--color-danger);"><?= $summary['absent'] ?></div>
                <div class="text-sm text-muted"><?= e(t('attendance.absent')) ?></div>
            </div>
            <div>
                <div style="font-size:1.4rem; font-weight:800; color:var(--color-accent);"><?= $summary['injured'] ?></div>
                <div class="text-sm text-muted"><?= e(t('attendance.injured')) ?></div>
            </div>
        </div>
        <?php
        $notPresent = array_filter($attRecords, fn($r) => $r['status'] !== 'present');
        if (!empty($notPresent)):
        ?>
            <?php foreach ($notPresent as $att): ?>
                <div class="attendance-row">
                    <div class="flex-between">
                        <span><?= e($att['first_name']) ?>
                            <?php if ($att['squad_number'] !== null): ?>
                                <span class="text-muted text-sm">#<?= (int) $att['squad_number'] ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="badge <?= $att['status'] === 'injured' ? 'badge--accent' : 'badge--danger' ?>">
                            <?= e(t('attendance.' . $att['status'])) ?>
                        </span>
                    </div>
                    <?php if ($att['status'] === 'absent' && $att['absence_reason']): ?>
                        <div class="text-sm text-muted"><?= e(t('attendance.reason.' . $att['absence_reason'])) ?></div>
                    <?php endif; ?>
                    <?php if ($att['status'] === 'injured' && $att['injury_note']): ?>
                        <div class="text-sm text-muted"><?= e($att['injury_note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function setAttStatus(playerId, status) {
    document.getElementById('status-' + playerId).value = status;

    var buttons = document.querySelectorAll('[data-player="' + playerId + '"]');
    buttons.forEach(function (btn) {
        btn.classList.remove('att-btn--active');
        if (btn.dataset.status === status) {
            btn.classList.add('att-btn--active');
        }
    });

    var reasonEl = document.getElementById('att-reason-' + playerId);
    var injuryEl = document.getElementById('att-injury-' + playerId);
    if (reasonEl) reasonEl.style.display = status === 'absent'  ? 'block' : 'none';
    if (injuryEl) injuryEl.style.display = status === 'injured' ? 'block' : 'none';
}
</script>
<?php

$content = ob_get_clean();
$title   = $dateLabel;
require dirname(__DIR__, 2) . '/templates/layout.php';
