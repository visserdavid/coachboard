<?php
declare(strict_types=1);

$activePage   = 'match';
$matchRepo    = new MatchRepository();
$matchService = new MatchService();
$playerRepo   = new PlayerRepository();
$trainingRepo = new TrainingRepository();
$errors       = [];

$id    = (int) ($_GET['id'] ?? 0);
$match = $matchRepo->getMatchById($id);

if ($match === null) {
    $_SESSION['flash'] = t('match.not_found');
    redirect(APP_URL . '/index.php?page=match');
}

$teamId = (int) $match['team_id'];

// Handle POST: save attendance and go to lineup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'add_guest') {
        $guestName   = trim($_POST['guest_name'] ?? '');
        $guestNumber = trim($_POST['guest_squad_number'] ?? '');
        if ($guestName !== '') {
            $matchService->addGuestPlayer($id, [
                'guest_name'         => $guestName,
                'guest_squad_number' => $guestNumber !== '' ? (int) $guestNumber : null,
            ]);
        }
        redirect(APP_URL . '/index.php?page=match&action=prepare&id=' . $id);
    }

    if ($postAction === 'remove_guest') {
        $mpId = (int) ($_POST['match_player_id'] ?? 0);
        $matchService->removeGuestPlayer($mpId);
        redirect(APP_URL . '/index.php?page=match&action=prepare&id=' . $id);
    }

    if ($postAction === 'save_attendance') {
        $rawAttendance = (array) ($_POST['att'] ?? []);

        $attendance = [];
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
            if ($status === 'absent' && $reason === null) {
                $errors[] = t('attendance.reason') . ' ' . t('error.required');
                break;
            }
            $attendance[(int) $playerId] = [
                'status' => $status,
                'absence_reason' => $reason,
                'injury_note' => $note,
            ];
        }

        if (empty($errors)) {
            foreach ($attendance as $playerId => $data) {
                $trainingRepo->saveAttendance($playerId, 'match', $id, $data);
            }
            redirect(APP_URL . '/index.php?page=match&action=lineup&id=' . $id);
        }
    }
}

$players    = $playerRepo->getPlayersByTeam($teamId);
$attRecords = $trainingRepo->getAttendanceBySession($id); // reused for match context via context_type

// Actually need to load match attendance, not training session attendance.
// TrainingRepository::getAttendanceBySession uses context_type='training_session'.
// Use a direct query here.
$pdo = Database::getInstance()->getConnection();
$attStmt = $pdo->prepare(
    "SELECT a.*, p.first_name, p.squad_number
     FROM attendance a
     JOIN player p ON p.id = a.player_id
     WHERE a.context_type = 'match'
       AND a.context_id = ?
       AND p.deleted_at IS NULL
     ORDER BY p.squad_number IS NULL, p.squad_number ASC, p.first_name ASC"
);
$attStmt->execute([$id]);
$attRecords = $attStmt->fetchAll();

$attendanceMap = [];
foreach ($attRecords as $att) {
    $attendanceMap[(int) $att['player_id']] = $att;
}

// Guest players already added
$matchPlayers = $matchRepo->getMatchPlayers($id);
$guests = array_filter($matchPlayers, fn($p) => (bool) $p['is_guest']);

// Count present (squad + guests)
$presentCount = count($guests);
foreach ($players as $p) {
    $pid    = (int) $p['id'];
    $status = $attendanceMap[$pid]['status'] ?? 'present';
    if ($status === 'present') {
        $presentCount++;
    }
}
$canProceed = $presentCount >= 11;

$dateLabel = date('j M', strtotime($match['date']));
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=match"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;"><?= e($match['opponent']) ?> · <?= e($dateLabel) ?></h1>
    <span></span>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);">
        <?php foreach ($errors as $error): ?>
            <p class="text-danger text-sm"><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Progress indicator -->
<div style="display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center;">
    <div style="flex:1; height:4px; background:var(--color-primary); border-radius:2px;"></div>
    <div style="flex:1; height:4px; background:var(--color-border); border-radius:2px;"></div>
    <span class="text-sm text-muted" style="white-space:nowrap;">
        <?= e(t('match.prepare.step', ['current' => '1', 'total' => '2'])) ?>
    </span>
</div>

<form id="attendance-form" method="POST"
      action="<?= e(APP_URL) ?>/index.php?page=match&action=prepare&id=<?= $id ?>">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="save_attendance">

    <div class="card">
        <strong><?= e(t('match.prepare.title')) ?></strong>

        <?php if (empty($players) && empty($guests)): ?>
            <p class="text-muted text-sm mt-2"><?= e(t('player.no_players')) ?></p>
        <?php else: ?>

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
                                    onclick="setMatchAttStatus(<?= $pid ?>, '<?= $s ?>')">
                                <?= e(t('attendance.' . $s)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="att-sub" id="att-reason-<?= $pid ?>"
                         style="display:<?= $status === 'absent' ? 'block' : 'none' ?>;">
                        <select name="att[<?= $pid ?>][absence_reason]" class="form-select"
                                style="margin-top:0.25rem;">
                            <option value=""><?= e(t('attendance.reason_placeholder')) ?></option>
                            <?php foreach (['sick', 'holiday', 'school', 'other'] as $r): ?>
                                <option value="<?= $r ?>"
                                    <?= ($att['absence_reason'] ?? '') === $r ? 'selected' : '' ?>>
                                    <?= e(t('attendance.reason.' . $r)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
        <?php endif; ?>
    </div>

    <button type="submit"
            class="btn btn--primary btn--full"
            id="next-btn"
            <?= $canProceed ? '' : 'disabled' ?>>
        <?= e(t('match.prepare.next')) ?>
    </button>
</form>

<?php if (!empty($guests)): ?>
<div class="card" style="margin-top:0.75rem;">
    <strong style="display:block; margin-bottom:0.5rem;"><?= e(t('match.guest')) ?></strong>
    <?php foreach ($guests as $guest): ?>
        <div class="attendance-row">
            <div class="flex-between">
                <span>
                    <?= e($guest['guest_name'] ?? '') ?>
                    <?php if ($guest['guest_squad_number'] !== null): ?>
                        <span class="text-muted text-sm">#<?= (int) $guest['guest_squad_number'] ?></span>
                    <?php endif; ?>
                    <span class="badge badge--accent" style="margin-left:0.3rem;"><?= e(t('match.guest')) ?></span>
                </span>
                <form method="POST" action="<?= e(APP_URL) ?>/index.php?page=match&action=prepare&id=<?= $id ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="remove_guest">
                    <input type="hidden" name="match_player_id" value="<?= (int) $guest['id'] ?>">
                    <button type="submit" class="btn btn--danger btn--sm">
                        <?= e(t('match.guest.remove')) ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add guest player -->
<div class="card" style="margin-top:0.75rem;">
    <strong style="display:block; margin-bottom:0.5rem;"><?= e(t('match.guest.add')) ?></strong>
    <form method="POST" action="<?= e(APP_URL) ?>/index.php?page=match&action=prepare&id=<?= $id ?>">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="add_guest">
        <div class="form-group">
            <input type="text" name="guest_name" class="form-input"
                   placeholder="<?= e(t('match.guest.name')) ?>" required>
        </div>
        <div class="form-group">
            <input type="number" name="guest_squad_number" class="form-input"
                   placeholder="<?= e(t('match.guest.number')) ?>" min="1" max="99">
        </div>
        <button type="submit" class="btn btn--secondary btn--sm">
            <?= e(t('action.add')) ?>
        </button>
    </form>
</div>

<script>
var presentCount = <?= $presentCount ?>;

function setMatchAttStatus(playerId, status) {
    var hiddenInput = document.getElementById('status-' + playerId);
    var prevStatus = hiddenInput.value;

    if (prevStatus === 'present' && status !== 'present') {
        presentCount--;
    } else if (prevStatus !== 'present' && status === 'present') {
        presentCount++;
    }

    hiddenInput.value = status;

    var row = document.getElementById('att-row-' + playerId);
    row.querySelectorAll('.att-btn').forEach(function(btn) {
        btn.classList.remove('att-btn--active');
    });
    row.querySelector('.att-btn--' + status).classList.add('att-btn--active');

    var reasonEl = document.getElementById('att-reason-' + playerId);
    var injuryEl = document.getElementById('att-injury-' + playerId);
    if (reasonEl) reasonEl.style.display = status === 'absent'  ? 'block' : 'none';
    if (injuryEl) injuryEl.style.display = status === 'injured' ? 'block' : 'none';

    document.getElementById('next-btn').disabled = presentCount < 11;
}
</script>
<?php

$content = ob_get_clean();
$title   = t('match.prepare.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
